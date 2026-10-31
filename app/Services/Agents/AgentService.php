<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Core\Clock;
use App\Models\Role;
use App\Repositories\AgentRepository;
use App\Repositories\ConformiteRepository;
use App\Repositories\JetonCompteRepository;
use App\Repositories\UtilisateurRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Crypto\Chiffrement;
use App\Services\Crypto\Cles;
use App\Services\Mail\FileEmails;
use App\Services\Stockage\Stockage;
use App\Services\ValidationException;
use PDO;
use Throwable;

final class AgentService
{
    private const DUREE_INVITATION = 72 * 3600;

    public function __construct(
        private readonly PDO $pdo,
        private readonly AgentRepository $agents,
        private readonly UtilisateurRepository $utilisateurs,
        private readonly ConformiteRepository $conformite,
        private readonly JetonCompteRepository $jetons,
        private readonly Chiffrement $chiffrementNir,
        private readonly Cles $cles,
        private readonly Stockage $stockage,
        private readonly AuditLogger $audit,
        private readonly FileEmails $emails,
        private readonly string $appUrl,
    ) {
    }

    /**
     * @param array<string, ?string> $identite champs de AgentRepository::CHAMPS_IDENTITE
     * @param array<string, ?string> $contrat champs de AgentRepository::CHAMPS_CONTRAT
     * @param list<int> $sites
     */
    public function creer(
        array $identite,
        array $contrat,
        string $email,
        Role $role,
        ?string $nir,
        array $sites,
        ?int $sitePrincipal,
        bool $inviter,
    ): int {
        $this->verifierUnicite($email, (string) $contrat['matricule'], $nir, null, null);

        $this->pdo->beginTransaction();
        try {
            $utilisateurId = $this->utilisateurs->creer($role, $email, trim($identite['prenom'] . ' ' . $identite['nom']));
            $agentId = $this->agents->creer($utilisateurId, [...$this->normaliser($identite), ...$this->normaliser($contrat)]);
            if ($nir !== null) {
                $this->enregistrerNir($agentId, $nir);
            }
            $this->agents->definirAffectations($agentId, $sites, $sitePrincipal);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $this->audit->journaliser('agent.creer', 'agent', $agentId, ['matricule' => $contrat['matricule'], 'role' => $role->value]);
        if ($inviter) {
            $this->inviter($agentId);
        }

        return $agentId;
    }

    /**
     * @param array<string, ?string> $identite
     * @param array<string, ?string> $contrat
     * @param list<int> $sites
     */
    public function modifier(
        int $agentId,
        array $identite,
        array $contrat,
        string $email,
        Role $role,
        ?string $nir,
        array $sites,
        ?int $sitePrincipal,
    ): void {
        $agent = $this->agents->trouver($agentId) ?? throw ValidationException::champ('agent', 'Agent introuvable.');
        $utilisateurId = (int) $agent['utilisateur_id'];
        $this->verifierUnicite($email, (string) $contrat['matricule'], $nir, $agentId, $utilisateurId);

        $this->pdo->beginTransaction();
        try {
            $this->agents->modifier($agentId, [...$this->normaliser($identite), ...$this->normaliser($contrat)]);
            $this->utilisateurs->modifierIdentite($utilisateurId, $email, trim($identite['prenom'] . ' ' . $identite['nom']));
            if (Role::fromId((int) $agent['role_id']) !== $role) {
                $this->utilisateurs->changerRole($utilisateurId, $role, null);
                $this->utilisateurs->invaliderSessions($utilisateurId);
            }
            if ($nir !== null) {
                $this->enregistrerNir($agentId, $nir);
            }
            $this->agents->definirAffectations($agentId, $sites, $sitePrincipal);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $this->audit->journaliser('agent.modifier', 'agent', $agentId, [
            'role' => $role->value,
            'nir_modifie' => $nir !== null,
        ]);
    }

    public function changerStatut(int $agentId, string $statut): void
    {
        $utilisateurId = $this->agents->utilisateurDe($agentId) ?? throw ValidationException::champ('agent', 'Agent introuvable.');
        $this->utilisateurs->changerStatut($utilisateurId, $statut);
        $this->utilisateurs->invaliderSessions($utilisateurId);

        if ($statut === 'desactive') {
            $this->jetons->invaliderPour($utilisateurId, 'appareil_confiance');
            $this->agents->modifier($agentId, ['date_sortie' => Clock::now()->setTimezone(Clock::local())->format('Y-m-d')]);
        }

        $this->audit->journaliser('agent.statut', 'agent', $agentId, ['statut' => $statut]);
    }

    public function inviter(int $agentId): void
    {
        $agent = $this->agents->trouver($agentId) ?? throw ValidationException::champ('agent', 'Agent introuvable.');
        $utilisateurId = (int) $agent['utilisateur_id'];

        $this->jetons->invaliderPour($utilisateurId, 'invitation');
        $jeton = $this->jetons->creer($utilisateurId, 'invitation', self::DUREE_INVITATION);

        $this->emails->ajouter((string) $agent['email'], 'Votre accès à Pegasus Connect', 'invitation', [
            'nom' => $agent['prenom'] . ' ' . $agent['nom'],
            'lien' => $this->appUrl . '/invitation/' . $jeton,
            'heures' => intdiv(self::DUREE_INVITATION, 3600),
        ]);
        $this->audit->journaliser('agent.inviter', 'agent', $agentId);
    }

    public function revelerNir(int $agentId): ?string
    {
        $chiffre = $this->agents->nirChiffre($agentId);
        $this->audit->journaliser('agent.nir.reveler', 'agent', $agentId);

        return $chiffre === null ? null : Nir::formater($this->chiffrementNir->dechiffrer($chiffre));
    }

    /**
     * @param array{numero: string, type_activite: string, date_delivrance: string, date_expiration: string} $carte
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int}|null $recto
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int}|null $verso
     */
    public function enregistrerCartePro(int $agentId, array $carte, ?array $recto, ?array $verso, int $auteurId, bool $nouvelle): void
    {
        $types = [...Stockage::IMAGES, ...Stockage::PDF];
        $documents = [];
        foreach (['recto' => $recto, 'verso' => $verso] as $face => $fichier) {
            if ($fichier !== null) {
                $documents[$face . '_document_id'] = $this->stockage->deposer(
                    $fichier, $types, 10 * 1048576, 'carte_pro', $agentId, 'Carte professionnelle (' . $face . ')', $auteurId,
                    ['date_validite' => $carte['date_expiration']],
                );
            }
        }

        $courante = $this->conformite->carteProCourante($agentId);
        if ($nouvelle || $courante === null) {
            $id = $this->conformite->enregistrerCartePro($agentId, [...$carte, ...$documents]);
        } else {
            $id = (int) $courante['id'];
            $this->conformite->modifierCartePro($id, [...$carte, ...$documents]);
        }

        $this->audit->journaliser('agent.carte_pro', 'carte_pro', $id, ['agent' => $agentId, 'nouvelle' => $nouvelle]);
    }

    /**
     * @param array{type_qualification_id: int, intitule: ?string, numero: ?string, organisme: ?string, date_obtention: string, date_echeance: ?string} $donnees
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int}|null $justificatif
     */
    public function ajouterQualification(int $agentId, array $donnees, ?array $justificatif, int $auteurId): void
    {
        $type = $this->conformite->typeQualification($donnees['type_qualification_id'])
            ?? throw ValidationException::champ('type_qualification_id', 'Type de qualification inconnu.');

        if ($donnees['date_echeance'] === null && $type['validite_mois'] !== null) {
            $donnees['date_echeance'] = (new \DateTimeImmutable($donnees['date_obtention']))
                ->modify('+' . (int) $type['validite_mois'] . ' months')->format('Y-m-d');
        }

        if ($justificatif !== null) {
            $donnees['document_id'] = $this->stockage->deposer(
                $justificatif, [...Stockage::PDF, ...Stockage::IMAGES], 10 * 1048576, 'diplome', $agentId,
                (string) $type['libelle'], $auteurId, ['date_document' => $donnees['date_obtention'], 'date_validite' => $donnees['date_echeance']],
            );
        }

        $id = $this->conformite->ajouterQualification($agentId, $donnees);
        $this->audit->journaliser('agent.qualification.ajouter', 'qualification', $id, ['agent' => $agentId, 'type' => $type['code']]);
    }

    private function enregistrerNir(int $agentId, string $nir): void
    {
        $nir = Nir::normaliser($nir);
        $this->agents->definirNir(
            $agentId,
            $this->chiffrementNir->chiffrer($nir),
            $this->cles->indexAveugle('nir', $nir),
            substr($nir, -2),
        );
    }

    private function verifierUnicite(string $email, string $matricule, ?string $nir, ?int $agentId, ?int $utilisateurId): void
    {
        $erreurs = [];
        if ($this->utilisateurs->emailUtilise($email, $utilisateurId)) {
            $erreurs['email'] = 'Cette adresse e-mail est déjà utilisée par un autre compte.';
        }
        if ($this->agents->matriculeUtilise($matricule, $agentId)) {
            $erreurs['matricule'] = 'Ce matricule est déjà attribué.';
        }
        if ($nir !== null && $this->agents->nirUtilise($this->cles->indexAveugle('nir', Nir::normaliser($nir)), $agentId)) {
            $erreurs['nir'] = 'Ce numéro de sécurité sociale est déjà enregistré pour un autre agent.';
        }

        if ($erreurs !== []) {
            throw new ValidationException($erreurs);
        }
    }

    /**
     * @param array<string, ?string> $donnees
     * @return array<string, scalar|null>
     */
    private function normaliser(array $donnees): array
    {
        $resultat = [];
        foreach ($donnees as $champ => $valeur) {
            $resultat[$champ] = match ($champ) {
                'nom', 'nom_naissance' => $valeur === null ? null : mb_strtoupper($valeur),
                'heures_mensuelles', 'taux_horaire_base' => $valeur === null ? null : str_replace(',', '.', $valeur),
                'equipe_id' => $valeur === null ? null : (int) $valeur,
                default => $valeur,
            };
        }

        return $resultat;
    }
}
