<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Perimetre;
use App\Models\Role;
use App\Repositories\AgentRepository;
use App\Repositories\DocumentRepository;
use App\Services\Agents\Nir;
use App\Services\Stockage\FichierRefuseException;
use App\Services\Stockage\Stockage;
use App\Services\ValidationException;

final class AgentController extends Controller
{
    private const ONGLETS = ['identite', 'conformite', 'documents', 'activite', 'badge'];

    private const LIBELLES = [
        'role' => 'Le rôle',
        'civilite' => 'La civilité',
        'nom' => 'Le nom',
        'nom_naissance' => 'Le nom de naissance',
        'prenom' => 'Le prénom',
        'date_naissance' => 'La date de naissance',
        'lieu_naissance' => 'Le lieu de naissance',
        'nationalite' => 'La nationalité',
        'adresse' => 'L\'adresse',
        'code_postal' => 'Le code postal',
        'ville' => 'La ville',
        'telephone' => 'Le téléphone',
        'email' => 'L\'adresse e-mail',
        'nir' => 'Le numéro de sécurité sociale',
        'matricule' => 'Le matricule',
        'fonction' => 'La fonction',
        'date_embauche' => 'La date d\'embauche',
        'date_sortie' => 'La date de sortie',
        'type_contrat' => 'Le type de contrat',
        'temps_travail' => 'Le temps de travail',
        'heures_mensuelles' => 'Les heures mensuelles',
        'taux_horaire_base' => 'Le taux horaire',
        'equipe_id' => 'L\'équipe',
    ];

    public function index(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $perimetre = $this->factory->autorisation()->perimetre($utilisateur, 'agents.voir');
        $page = max(1, (int) ($request->query('page') ?? 1));
        $filtres = [
            'q' => (string) $request->query('q', ''),
            'site' => (int) $request->query('site', '0'),
            'statut' => (string) $request->query('statut', ''),
            'echeance' => (string) $request->query('echeance', ''),
        ];

        $resultat = $this->factory->agents()->lister($perimetre, $filtres, $page);

        return $this->vue('admin/agents/index', [
            'agents' => $resultat['lignes'],
            'total' => $resultat['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($resultat['total'] / AgentRepository::PAR_PAGE)),
            'filtres' => $filtres,
            'sites' => $this->factory->sites()->choix($perimetre),
            'peutGerer' => $utilisateur->peut('agents.gerer'),
        ]);
    }

    public function nouveau(Request $request): Response
    {
        return $this->vue('admin/agents/formulaire', $this->donneesFormulaire(null, [
            'matricule' => $this->factory->agents()->prochainMatricule(),
            'nationalite' => 'Française',
            'heures_mensuelles' => '151.67',
            'temps_travail' => 'plein',
            'type_contrat' => 'CDI',
            'date_embauche' => Clock::now()->setTimezone(Clock::local())->format('Y-m-d'),
        ]));
    }

    public function creer(Request $request): Response
    {
        $validation = $this->valider($request, true);
        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'admin/agents/formulaire', $validation->errors(), $this->donneesFormulaire(null, []));
        }

        $donnees = $validation->validated();
        try {
            $id = $this->factory->agentService()->creer(
                $this->extraire($donnees, AgentRepository::CHAMPS_IDENTITE),
                $this->extraire($donnees, AgentRepository::CHAMPS_CONTRAT),
                (string) $donnees['email'],
                Role::from((string) $donnees['role']),
                $donnees['nir'],
                $this->sitesChoisis($request),
                $this->sitePrincipal($request),
                $request->boolean('inviter'),
            );
        } catch (ValidationException $exception) {
            return $this->formulaireInvalide($request, 'admin/agents/formulaire', $exception->erreurs(), $this->donneesFormulaire(null, []));
        }

        return $this->rediriger('/admin/agents/' . $id, $request->boolean('inviter')
            ? 'Agent créé. L\'invitation part par e-mail dans quelques minutes.'
            : 'Agent créé. Vous pourrez envoyer l\'invitation depuis sa fiche.');
    }

    public function voir(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $id = $request->intParam('id');
        $agent = $this->agentAccessible($request, $id);
        $onglet = in_array($request->query('onglet'), self::ONGLETS, true) ? (string) $request->query('onglet') : 'identite';

        $donnees = [
            'agent' => $agent,
            'onglet' => $onglet,
            'nirMasque' => Nir::masquer($agent['nir_fin'] === null ? null : (string) $agent['nir_fin']),
            'peutGerer' => $utilisateur->peut('agents.gerer'),
            'peutRevelerNir' => $utilisateur->peut('agents.nir.reveler'),
            'affectations' => $this->factory->agents()->affectations($id),
            'carte' => $this->factory->conformite()->carteProCourante($id),
        ];

        if ($onglet === 'conformite') {
            $donnees['qualifications'] = $this->factory->conformite()->qualifications($id);
            $donnees['typesQualification'] = $this->factory->conformite()->typesQualification();
        }

        if ($onglet === 'documents') {
            $peutVoirDocuments = $utilisateur->peut('agents.documents.gerer');
            $documents = $peutVoirDocuments ? $this->factory->documents()->pourAgent($id, false) : [];
            foreach ($documents as &$document) {
                $document['url'] = $this->factory->urlSignee()->pour((int) $document['id'], $utilisateur->id);
            }
            $donnees['documents'] = $documents;
            $donnees['peutDeposer'] = $peutVoirDocuments;
            $donnees['categories'] = DocumentRepository::CATEGORIES_AGENT;
        }

        if ($onglet === 'activite') {
            $donnees['services'] = $this->factory->servicesTravail()->recentsPourAgent($id, 20);
            $donnees['rondes'] = $this->factory->rondes()->recentesPourAgent($id, 10);
        }

        if ($onglet === 'badge') {
            $donnees['badge'] = $this->factory->badges()->actifPourAgent($id);
            $donnees['historiqueBadges'] = $this->factory->badges()->historique($id);
            $donnees['peutGererBadges'] = $utilisateur->peut('badges.gerer');
            $donnees['bornes'] = $utilisateur->peut('badges.gerer') ? $this->factory->bornes()->avecLecteurPourSites($this->factory->agents()->sitesAffectes($id)) : [];
        }

        return $this->vue('admin/agents/fiche', $donnees);
    }

    public function editer(Request $request): Response
    {
        $id = $request->intParam('id');
        $agent = $this->agentAccessible($request, $id);

        return $this->vue('admin/agents/formulaire', $this->donneesFormulaire($agent, []));
    }

    public function enregistrer(Request $request): Response
    {
        $id = $request->intParam('id');
        $agent = $this->agentAccessible($request, $id);

        $validation = $this->valider($request, false);
        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'admin/agents/formulaire', $validation->errors(), $this->donneesFormulaire($agent, []));
        }

        $donnees = $validation->validated();
        try {
            $this->factory->agentService()->modifier(
                $id,
                $this->extraire($donnees, AgentRepository::CHAMPS_IDENTITE),
                $this->extraire($donnees, AgentRepository::CHAMPS_CONTRAT),
                (string) $donnees['email'],
                Role::from((string) $donnees['role']),
                $donnees['nir'],
                $this->sitesChoisis($request),
                $this->sitePrincipal($request),
            );
        } catch (ValidationException $exception) {
            return $this->formulaireInvalide($request, 'admin/agents/formulaire', $exception->erreurs(), $this->donneesFormulaire($agent, []));
        }

        return $this->rediriger('/admin/agents/' . $id, 'Fiche enregistrée.');
    }

    public function statut(Request $request): Response
    {
        $id = $request->intParam('id');
        $this->agentAccessible($request, $id);
        $statut = (string) $request->input('statut');
        if (!in_array($statut, ['actif', 'suspendu', 'desactive'], true)) {
            throw HttpException::badRequest();
        }

        $this->factory->agentService()->changerStatut($id, $statut);
        $messages = [
            'actif' => 'Le compte est réactivé.',
            'suspendu' => 'Le compte est suspendu : l\'agent ne peut plus se connecter.',
            'desactive' => 'Le compte est désactivé. Ses données restent conservées selon les durées légales.',
        ];

        return $this->rediriger('/admin/agents/' . $id, $messages[$statut]);
    }

    public function inviter(Request $request): Response
    {
        $id = $request->intParam('id');
        $agent = $this->agentAccessible($request, $id);
        if ($agent['statut'] !== 'invite') {
            return $this->redirigerAvecErreur('/admin/agents/' . $id, 'Ce compte est déjà activé.');
        }

        $this->factory->agentService()->inviter($id);

        return $this->rediriger('/admin/agents/' . $id, 'Une nouvelle invitation, valable 72 heures, va être envoyée.');
    }

    /**
     * Révélation du NIR : exige une authentification forte récente (moins de 5 minutes).
     */
    public function nir(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $id = $request->intParam('id');
        $this->agentAccessible($request, $id);

        $ligne = $this->factory->utilisateurs()->trouver($utilisateur->id);
        $authForte = Clock::fromSql($ligne['auth_forte_le'] ?? null);
        if ($authForte === null || $authForte < Clock::now()->modify('-5 minutes')) {
            return $this->json([
                'reauthentification' => '/compte/reauthentifier?retour=' . rawurlencode('/admin/agents/' . $id),
            ], 401);
        }

        $nir = $this->factory->agentService()->revelerNir($id);

        return $this->json(['nir' => $nir ?? 'Non renseigné', 'duree' => 30]);
    }

    public function deposerDocument(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $id = $request->intParam('id');
        $this->agentAccessible($request, $id);
        $retour = '/admin/agents/' . $id . '?onglet=documents';

        $validation = Validator::make($request->all(), [
            'categorie' => 'required|in:' . implode(',', array_keys(DocumentRepository::CATEGORIES_AGENT)),
            'libelle' => 'required|max:150',
            'date_document' => 'date',
            'date_validite' => 'date',
        ], ['categorie' => 'La catégorie', 'libelle' => 'Le libellé', 'date_document' => 'La date du document', 'date_validite' => 'La date de validité']);

        $fichier = $request->file('fichier');
        if ($validation->fails() || $fichier === null) {
            return $this->redirigerAvecErreur($retour, $fichier === null ? 'Choisissez un fichier.' : self::premiereErreur($validation));
        }

        $donnees = $validation->validated();
        try {
            $documentId = $this->factory->stockage()->deposer(
                $fichier,
                [...Stockage::PDF, ...Stockage::IMAGES],
                10 * 1048576,
                (string) $donnees['categorie'],
                $id,
                (string) $donnees['libelle'],
                $utilisateur->id,
                [
                    'date_document' => $donnees['date_document'],
                    'date_validite' => $donnees['date_validite'],
                    'visible_agent' => $request->boolean('visible_agent'),
                ],
            );
        } catch (FichierRefuseException $exception) {
            return $this->redirigerAvecErreur($retour, $exception->getMessage());
        }

        $this->factory->audit()->journaliser('agent.document.deposer', 'document', $documentId, ['agent' => $id, 'categorie' => $donnees['categorie']]);

        return $this->rediriger($retour, 'Document ajouté.');
    }

    public function supprimerDocument(Request $request): Response
    {
        $id = $request->intParam('id');
        $documentId = $request->intParam('document');
        $this->agentAccessible($request, $id);

        $document = $this->factory->documents()->trouver($documentId);
        if ($document === null || (int) $document['agent_id'] !== $id) {
            throw HttpException::notFound();
        }

        $this->factory->documents()->supprimer($documentId);
        $this->factory->audit()->journaliser('agent.document.supprimer', 'document', $documentId, ['agent' => $id]);

        return $this->rediriger('/admin/agents/' . $id . '?onglet=documents', 'Document retiré.');
    }

    public function cartePro(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $id = $request->intParam('id');
        $this->agentAccessible($request, $id);
        $retour = '/admin/agents/' . $id . '?onglet=conformite';

        $validation = Validator::make($request->all(), [
            'numero' => 'required|max:40',
            'type_activite' => 'required|max:100',
            'date_delivrance' => 'required|date',
            'date_expiration' => 'required|date',
        ], ['numero' => 'Le numéro', 'type_activite' => 'L\'activité', 'date_delivrance' => 'La date de délivrance', 'date_expiration' => 'La date d\'expiration']);

        if ($validation->fails()) {
            return $this->redirigerAvecErreur($retour, self::premiereErreur($validation));
        }

        $donnees = $validation->validated();
        if ($donnees['date_expiration'] <= $donnees['date_delivrance']) {
            return $this->redirigerAvecErreur($retour, 'La date d\'expiration doit suivre la date de délivrance.');
        }

        try {
            $this->factory->agentService()->enregistrerCartePro(
                $id,
                [
                    'numero' => mb_strtoupper((string) $donnees['numero']),
                    'type_activite' => (string) $donnees['type_activite'],
                    'date_delivrance' => (string) $donnees['date_delivrance'],
                    'date_expiration' => (string) $donnees['date_expiration'],
                ],
                $request->file('recto'),
                $request->file('verso'),
                $utilisateur->id,
                $request->boolean('nouvelle'),
            );
        } catch (FichierRefuseException $exception) {
            return $this->redirigerAvecErreur($retour, $exception->getMessage());
        }

        return $this->rediriger($retour, 'Carte professionnelle enregistrée.');
    }

    public function ajouterQualification(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $id = $request->intParam('id');
        $this->agentAccessible($request, $id);
        $retour = '/admin/agents/' . $id . '?onglet=conformite';

        $validation = Validator::make($request->all(), [
            'type_qualification_id' => 'required|int',
            'intitule' => 'max:150',
            'numero' => 'max:60',
            'organisme' => 'max:150',
            'date_obtention' => 'required|date',
            'date_echeance' => 'date',
        ], ['type_qualification_id' => 'Le type', 'date_obtention' => 'La date d\'obtention', 'date_echeance' => 'La date d\'échéance']);

        if ($validation->fails()) {
            return $this->redirigerAvecErreur($retour, self::premiereErreur($validation));
        }

        $donnees = $validation->validated();
        try {
            $this->factory->agentService()->ajouterQualification($id, [
                'type_qualification_id' => (int) $donnees['type_qualification_id'],
                'intitule' => $donnees['intitule'],
                'numero' => $donnees['numero'],
                'organisme' => $donnees['organisme'],
                'date_obtention' => (string) $donnees['date_obtention'],
                'date_echeance' => $donnees['date_echeance'],
            ], $request->file('justificatif'), $utilisateur->id);
        } catch (ValidationException | FichierRefuseException $exception) {
            return $this->redirigerAvecErreur($retour, $exception->getMessage());
        }

        return $this->rediriger($retour, 'Qualification ajoutée.');
    }

    public function supprimerQualification(Request $request): Response
    {
        $id = $request->intParam('id');
        $qualificationId = $request->intParam('qualification');
        $this->agentAccessible($request, $id);

        $qualification = $this->factory->conformite()->qualification($qualificationId);
        if ($qualification === null || (int) $qualification['agent_id'] !== $id) {
            throw HttpException::notFound();
        }

        $this->factory->conformite()->supprimerQualification($qualificationId);
        $this->factory->audit()->journaliser('agent.qualification.supprimer', 'qualification', $qualificationId, ['agent' => $id]);

        return $this->rediriger('/admin/agents/' . $id . '?onglet=conformite', 'Qualification supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function agentAccessible(Request $request, int $id): array
    {
        $agent = $this->factory->agents()->trouver($id) ?? throw HttpException::notFound();
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'agents.voir');
        if (!$perimetre->couvreAgent($id)) {
            throw HttpException::notFound();
        }

        return $agent;
    }

    private function valider(Request $request, bool $creation): Validator
    {
        $regles = [
            'role' => 'required|in:agent,chef',
            'civilite' => 'in:M,Mme',
            'nom' => 'required|max:80',
            'nom_naissance' => 'max:80',
            'prenom' => 'required|max:80',
            'date_naissance' => 'required|date',
            'lieu_naissance' => 'required|max:120',
            'nationalite' => 'required|max:60',
            'adresse' => 'required|max:255',
            'code_postal' => 'required|max:10',
            'ville' => 'required|max:80',
            'telephone' => 'required|regex:/^[+0-9 .()-]{8,20}$/',
            'email' => 'required|email|max:190',
            'nir' => ($creation ? 'required|' : '') . 'nir',
            'matricule' => 'required|max:20|regex:/^[A-Z0-9-]+$/',
            'fonction' => 'required|max:80',
            'date_embauche' => 'required|date',
            'date_sortie' => 'date',
            'type_contrat' => 'required|in:CDI,CDD,interim,apprentissage,autre',
            'temps_travail' => 'required|in:plein,partiel',
            'heures_mensuelles' => 'required|decimal',
            'taux_horaire_base' => 'decimal',
            'equipe_id' => 'int',
        ];

        $entree = $request->all();
        $entree['matricule'] = mb_strtoupper(trim((string) ($entree['matricule'] ?? '')));

        return Validator::make($entree, $regles, self::LIBELLES, [
            'nir' => [static fn (string $valeur): bool => Nir::estValide($valeur), 'Le numéro de sécurité sociale est invalide (format ou clé de contrôle).'],
        ]);
    }

    /**
     * @param array<string, ?string> $donnees
     * @param list<string> $champs
     * @return array<string, ?string>
     */
    private function extraire(array $donnees, array $champs): array
    {
        return array_intersect_key($donnees, array_flip($champs));
    }

    /**
     * @return list<int>
     */
    private function sitesChoisis(Request $request): array
    {
        $sites = $request->raw('sites');

        return is_array($sites) ? array_values(array_unique(array_filter(array_map('intval', $sites)))) : [];
    }

    private function sitePrincipal(Request $request): ?int
    {
        $principal = (int) $request->input('site_principal', '0');

        return $principal > 0 && in_array($principal, $this->sitesChoisis($request), true) ? $principal : null;
    }

    /**
     * @param array<string, mixed>|null $agent
     * @param array<string, string> $defauts
     * @return array<string, mixed>
     */
    private function donneesFormulaire(?array $agent, array $defauts): array
    {
        $affectations = $agent === null ? [] : $this->factory->agents()->affectations((int) $agent['id']);
        $aujourdhui = Clock::now()->setTimezone(Clock::local())->format('Y-m-d');
        $actives = array_filter($affectations, static fn (array $a): bool => $a['date_fin'] === null || $a['date_fin'] >= $aujourdhui);

        return [
            'agent' => $agent,
            'defauts' => $defauts,
            'sites' => $this->factory->sites()->choix(Perimetre::tout()),
            'equipes' => $this->factory->agents()->equipes(),
            'sitesAffectes' => array_map(static fn (array $a): int => (int) $a['site_id'], $actives),
            'sitePrincipal' => (int) (array_values(array_filter($actives, static fn (array $a): bool => (bool) $a['principal']))[0]['site_id'] ?? 0),
        ];
    }
}
