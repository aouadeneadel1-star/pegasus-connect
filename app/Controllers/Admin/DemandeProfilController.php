<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\DemandeModificationRepository;
use App\Services\ValidationException;
use Throwable;

/**
 * Validation par le service RH des modifications de coordonnées demandées par les agents.
 */
final class DemandeProfilController extends Controller
{
    public function index(Request $request): Response
    {
        $demandes = $this->factory->repository(DemandeModificationRepository::class)->enAttente();
        foreach ($demandes as &$demande) {
            $demande['changements'] = json_decode((string) $demande['changements'], true) ?: [];
            $demande['url'] = $demande['document_id'] === null ? null : $this->factory->urlSignee()->pour((int) $demande['document_id'], $this->utilisateur($request)->id, true);
        }
        unset($demande);

        return $this->vue('admin/demandes-profil', ['demandes' => $demandes]);
    }

    public function traiter(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $depot = $this->factory->repository(DemandeModificationRepository::class);
        $demande = $depot->trouver($request->intParam('id'));
        if ($demande === null || $demande['statut'] !== 'en_attente') {
            throw HttpException::notFound();
        }

        $decision = (string) $request->input('decision');
        $agentId = (int) $demande['agent_id'];
        $utilisateurAgent = $this->factory->agents()->utilisateurDe($agentId);

        if ($decision === 'refuser') {
            $motif = trim((string) $request->input('motif', ''));
            if ($motif === '') {
                return $this->redirigerAvecErreur('/admin/demandes-profil', 'Indiquez le motif du refus.');
            }
            $depot->traiter((int) $demande['id'], 'refusee', $utilisateur->id, mb_substr($motif, 0, 255));
            $this->factory->audit()->journaliser('profil.demande.refuser', 'demande_profil', (int) $demande['id']);
            $this->factory->notifications()->envoyer([(int) $utilisateurAgent], 'demande_profil_refusee', 'Demande de modification refusée', $motif, '/profil');

            return $this->rediriger('/admin/demandes-profil', 'Demande refusée.');
        }

        $changements = json_decode((string) $demande['changements'], true) ?: [];
        $agent = $this->factory->agents()->trouver($agentId);
        $pdo = $this->factory->pdo();
        $pdo->beginTransaction();
        try {
            $champsAgent = array_intersect_key(array_map(static fn (array $c): string => (string) $c['apres'], $changements), array_flip(['adresse', 'code_postal', 'ville', 'telephone']));
            if ($champsAgent !== []) {
                $this->factory->agents()->modifier($agentId, $champsAgent);
            }
            if (isset($changements['email'])) {
                $email = mb_strtolower((string) $changements['email']['apres']);
                if ($this->factory->utilisateurs()->emailUtilise($email, (int) $agent['utilisateur_id'])) {
                    throw ValidationException::champ('email', 'Cette adresse e-mail est déjà utilisée par un autre compte.');
                }
                $this->factory->utilisateurs()->modifierIdentite((int) $agent['utilisateur_id'], $email, trim($agent['prenom'] . ' ' . $agent['nom']));
            }
            $depot->traiter((int) $demande['id'], 'validee', $utilisateur->id, null);
            $this->factory->audit()->journaliser('profil.demande.valider', 'demande_profil', (int) $demande['id'], ['champs' => array_keys($changements)]);
            $pdo->commit();
        } catch (ValidationException $exception) {
            $pdo->rollBack();

            return $this->redirigerAvecErreur('/admin/demandes-profil', $exception->getMessage());
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        $this->factory->notifications()->envoyer([(int) $utilisateurAgent], 'demande_profil_validee', 'Vos coordonnées ont été mises à jour', null, '/profil');

        return $this->rediriger('/admin/demandes-profil', 'Fiche mise à jour.');
    }
}
