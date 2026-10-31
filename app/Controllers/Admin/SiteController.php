<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\Rondes\Planification;

/**
 * Sites clients, bornes et QR fixe (cahier des charges, § 3.12).
 */
final class SiteController extends Controller
{
    private const ONGLETS = ['infos', 'bornes', 'points', 'modeles'];

    private const LIBELLES = [
        'client_id' => 'Le client',
        'code' => 'Le code',
        'nom' => 'Le nom',
        'adresse' => 'L\'adresse',
        'code_postal' => 'Le code postal',
        'ville' => 'La ville',
        'latitude' => 'La latitude',
        'longitude' => 'La longitude',
        'rayon_metres' => 'Le rayon',
        'mode_pointage' => 'Le mode de pointage',
        'telephone_poste' => 'Le téléphone du poste',
        'consignes' => 'Les consignes',
    ];

    /** Alphabet sans caractères ambigus (0/O, 1/I/L) pour un code saisi à la main. */
    private const ALPHABET_CODE = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function index(Request $request): Response
    {
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'sites.voir');
        $filtres = [
            'q' => (string) $request->query('q', ''),
            'client' => (int) $request->query('client', '0'),
            'actif' => (string) $request->query('actif', ''),
        ];

        return $this->vue('admin/sites/index', [
            'sites' => $this->factory->sites()->lister($perimetre, $filtres),
            'clients' => $this->factory->clients()->choix(),
            'filtres' => $filtres,
            'peutGerer' => $this->utilisateur($request)->peut('sites.gerer'),
        ]);
    }

    public function nouveau(Request $request): Response
    {
        return $this->vue('admin/sites/formulaire', $this->donneesFormulaire(null, (int) $request->query('client', '0')));
    }

    public function creer(Request $request): Response
    {
        $validation = $this->valider($request, null);
        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'admin/sites/formulaire', $validation->errors(), $this->donneesFormulaire(null, 0));
        }

        $id = $this->factory->sites()->creer($this->donnees($request, $validation));
        $this->factory->audit()->journaliser('site.creer', 'site', $id);

        return $this->rediriger('/admin/sites/' . $id, 'Site créé. Ajoutez maintenant ses bornes et ses points de contrôle.');
    }

    public function voir(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $site = $this->siteAccessible($request);
        $siteId = (int) $site['id'];
        $onglet = in_array($request->query('onglet'), self::ONGLETS, true) ? (string) $request->query('onglet') : 'infos';

        $donnees = [
            'site' => $site,
            'onglet' => $onglet,
            'peutGerer' => $utilisateur->peut('sites.gerer'),
            'codeEnrolement' => Session::getFlash('code_enrolement'),
        ];

        $donnees += match ($onglet) {
            'infos' => [
                'responsables' => $this->factory->sites()->responsables($siteId),
                'chefs' => $this->factory->sites()->chefsDisponibles(),
                'agents' => $this->factory->sites()->agentsAffectes($siteId),
                'presents' => $this->factory->servicesTravail()->presentsSurSite($siteId),
                'qualificationsRequises' => $this->libellesQualifications($site),
            ],
            'bornes' => [
                'bornes' => $this->factory->bornes()->pourSite($siteId),
                'urlBorne' => $this->factory->appUrl() . '/borne/' . $site['code'],
                'limiteSilence' => Clock::now()->modify('-' . $this->factory->parametres()->int('borne.alerte_silence_min', 30) . ' minutes'),
            ],
            'points' => ['points' => $this->factory->pointsControle()->pourSite($siteId, false)],
            'modeles' => ['modeles' => array_map(static function (array $modele): array {
                $modele['jours'] = Planification::libelleJours((int) $modele['jours_semaine']);

                return $modele;
            }, $this->factory->modelesRonde()->pourSite($siteId))],
        };

        return $this->vue('admin/sites/fiche', $donnees);
    }

    public function editer(Request $request): Response
    {
        $site = $this->siteAccessible($request);

        return $this->vue('admin/sites/formulaire', $this->donneesFormulaire($site, (int) $site['client_id']));
    }

    public function enregistrer(Request $request): Response
    {
        $site = $this->siteAccessible($request);
        $validation = $this->valider($request, (int) $site['id']);
        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'admin/sites/formulaire', $validation->errors(), $this->donneesFormulaire($site, (int) $site['client_id']));
        }

        $this->factory->sites()->modifier((int) $site['id'], $this->donnees($request, $validation));
        $this->factory->audit()->journaliser('site.modifier', 'site', (int) $site['id']);

        return $this->rediriger('/admin/sites/' . $site['id'], 'Site enregistré.');
    }

    public function responsables(Request $request): Response
    {
        $site = $this->siteAccessible($request);
        $choisis = $request->raw('responsables');
        $ids = is_array($choisis) ? array_values(array_unique(array_filter(array_map('intval', $choisis)))) : [];
        $chefs = array_map(static fn (array $c): int => (int) $c['id'], $this->factory->sites()->chefsDisponibles());

        $this->factory->sites()->definirResponsables((int) $site['id'], array_values(array_intersect($ids, $chefs)));
        $this->factory->audit()->journaliser('site.responsables', 'site', (int) $site['id'], ['chefs' => $ids]);

        return $this->rediriger('/admin/sites/' . $site['id'], 'Chefs d\'équipe responsables enregistrés.');
    }

    /**
     * Étiquette à imprimer et à apposer au poste, pour un site sans borne.
     */
    public function qrStatique(Request $request): Response
    {
        $site = $this->siteAccessible($request);
        $contenu = $this->factory->jetonsQr()->statique((int) $site['id'], (int) $site['qr_statique_version']);
        $pdf = $this->factory->pdf()->rendre('qr-statique', [
            'site' => $site,
            'qr' => $this->factory->qrImage()->dataUri($contenu, 600),
        ]);
        $this->factory->audit()->journaliser('site.qr_statique.imprimer', 'site', (int) $site['id']);

        return Response::download($pdf, 'qr-' . $site['code'] . '-v' . $site['qr_statique_version'] . '.pdf', 'application/pdf');
    }

    public function regenererQrStatique(Request $request): Response
    {
        $site = $this->siteAccessible($request);
        $version = $this->factory->sites()->incrementerVersionQrStatique((int) $site['id']);
        $this->factory->audit()->journaliser('site.qr_statique.regenerer', 'site', (int) $site['id'], ['version' => $version]);

        return $this->rediriger('/admin/sites/' . $site['id'], 'Nouveau QR fixe généré (version ' . $version . '). L\'ancienne étiquette est refusée dès maintenant : imprimez et posez la nouvelle.');
    }

    public function creerBorne(Request $request): Response
    {
        $site = $this->siteAccessible($request);
        $libelle = trim((string) $request->input('libelle', ''));
        $lecteur = (string) $request->input('lecteur_nfc', 'aucun');
        if ($libelle === '' || mb_strlen($libelle) > 80 || !in_array($lecteur, ['aucun', 'usb', 'web_nfc'], true)) {
            return $this->redirigerAvecErreur('/admin/sites/' . $site['id'] . '?onglet=bornes', 'Indiquez un libellé (80 caractères au plus) et le type de lecteur.');
        }

        $code = self::codeEnrolement();
        $id = $this->factory->bornes()->creer((int) $site['id'], $libelle, $lecteur, $code, Clock::now()->modify('+15 minutes'));
        $this->factory->audit()->journaliser('borne.creer', 'borne', $id, ['site' => (int) $site['id']]);
        Session::flash('code_enrolement', ['borne' => $libelle, 'code' => self::formaterCode($code)]);

        return $this->rediriger('/admin/sites/' . $site['id'] . '?onglet=bornes');
    }

    public function codeBorne(Request $request): Response
    {
        $borne = $this->borneAccessible($request);
        $code = self::codeEnrolement();
        $this->factory->bornes()->nouveauCodeEnrolement((int) $borne['id'], $code, Clock::now()->modify('+15 minutes'));
        $this->factory->audit()->journaliser('borne.code', 'borne', (int) $borne['id']);
        Session::flash('code_enrolement', ['borne' => $borne['libelle'], 'code' => self::formaterCode($code)]);

        return $this->rediriger('/admin/sites/' . $borne['site_id'] . '?onglet=bornes');
    }

    public function modifierBorne(Request $request): Response
    {
        $borne = $this->borneAccessible($request);
        $libelle = trim((string) $request->input('libelle', ''));
        $lecteur = (string) $request->input('lecteur_nfc', 'aucun');
        if ($libelle === '' || mb_strlen($libelle) > 80 || !in_array($lecteur, ['aucun', 'usb', 'web_nfc'], true)) {
            return $this->redirigerAvecErreur('/admin/sites/' . $borne['site_id'] . '?onglet=bornes', 'Libellé ou lecteur invalide.');
        }

        $this->factory->bornes()->modifier((int) $borne['id'], $libelle, $lecteur);

        return $this->rediriger('/admin/sites/' . $borne['site_id'] . '?onglet=bornes', 'Borne enregistrée.');
    }

    public function revoquerBorne(Request $request): Response
    {
        $borne = $this->borneAccessible($request);
        $this->factory->bornes()->revoquer((int) $borne['id']);
        $this->factory->audit()->journaliser('borne.revoquer', 'borne', (int) $borne['id']);

        return $this->rediriger('/admin/sites/' . $borne['site_id'] . '?onglet=bornes', 'Borne révoquée : ses QR codes et ses badgeages sont refusés immédiatement.');
    }

    /**
     * @return array<string, mixed>
     */
    private function siteAccessible(Request $request): array
    {
        $site = $this->factory->sites()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'sites.voir');
        if (!$perimetre->couvreSite((int) $site['id'])) {
            throw HttpException::notFound();
        }

        return $site;
    }

    /**
     * @return array<string, mixed>
     */
    private function borneAccessible(Request $request): array
    {
        $borne = $this->factory->bornes()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'sites.voir');
        if (!$perimetre->couvreSite((int) $borne['site_id'])) {
            throw HttpException::notFound();
        }

        return $borne;
    }

    private function valider(Request $request, ?int $siteId): Validator
    {
        $entree = $request->all();
        $entree['code'] = strtolower(trim((string) ($entree['code'] ?? '')));

        $validation = Validator::make($entree, [
            'client_id' => 'required|int',
            'code' => 'required|max:20|regex:/^[a-z0-9-]{3,20}$/',
            'nom' => 'required|max:120',
            'adresse' => 'required|max:255',
            'code_postal' => 'required|max:10',
            'ville' => 'required|max:80',
            'latitude' => 'required|decimal|between:-90,90',
            'longitude' => 'required|decimal|between:-180,180',
            'rayon_metres' => 'required|int|between:20,2000',
            'mode_pointage' => 'required|in:dynamique,statique',
            'telephone_poste' => 'regex:/^[+0-9 .()-]{8,20}$/',
            'consignes' => 'max:5000',
        ], self::LIBELLES);

        if (!$validation->fails()) {
            if ($this->factory->sites()->codeUtilise((string) $entree['code'], $siteId)) {
                $validation->addError('code', 'Ce code est déjà utilisé par un autre site.');
            }
            if ($this->factory->clients()->trouver((int) $entree['client_id']) === null) {
                $validation->addError('client_id', 'Client inconnu.');
            }
        }

        return $validation;
    }

    /**
     * @return array<string, scalar|null>
     */
    private function donnees(Request $request, Validator $validation): array
    {
        $donnees = $validation->validated();
        $qualifications = $request->raw('qualifications_requises');
        $types = array_map(static fn (array $t): int => (int) $t['id'], $this->factory->conformite()->typesQualification());

        return [
            'client_id' => (int) $donnees['client_id'],
            'code' => (string) $donnees['code'],
            'nom' => (string) $donnees['nom'],
            'adresse' => (string) $donnees['adresse'],
            'code_postal' => (string) $donnees['code_postal'],
            'ville' => (string) $donnees['ville'],
            'latitude' => str_replace(',', '.', (string) $donnees['latitude']),
            'longitude' => str_replace(',', '.', (string) $donnees['longitude']),
            'rayon_metres' => (int) $donnees['rayon_metres'],
            'mode_pointage' => (string) $donnees['mode_pointage'],
            'badge_nfc_actif' => $request->boolean('badge_nfc_actif') ? 1 : 0,
            'badge_pin_requis' => $request->boolean('badge_pin_requis') ? 1 : 0,
            'telephone_poste' => $donnees['telephone_poste'],
            'consignes' => $donnees['consignes'],
            'actif' => $request->boolean('actif') ? 1 : 0,
            'qualifications_requises' => json_encode(array_values(array_intersect(
                is_array($qualifications) ? array_map('intval', $qualifications) : [],
                $types,
            )), JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param array<string, mixed>|null $site
     * @return array<string, mixed>
     */
    private function donneesFormulaire(?array $site, int $clientId): array
    {
        return [
            'site' => $site,
            'defauts' => [
                'client_id' => (string) $clientId,
                'rayon_metres' => (string) $this->factory->parametres()->int('pointage.rayon_defaut_m', 150),
                'mode_pointage' => 'dynamique',
                'badge_pin_requis' => '1',
                'actif' => '1',
            ],
            'clients' => $this->factory->clients()->choix(),
            'types' => $this->factory->conformite()->typesQualification(),
            'qualificationsRequises' => $site === null ? [] : array_map('intval', json_decode((string) ($site['qualifications_requises'] ?? '[]'), true) ?: []),
        ];
    }

    /**
     * @param array<string, mixed> $site
     * @return list<string>
     */
    private function libellesQualifications(array $site): array
    {
        $requises = array_map('intval', json_decode((string) ($site['qualifications_requises'] ?? '[]'), true) ?: []);

        return array_values(array_map(
            static fn (array $t): string => (string) $t['libelle'],
            array_filter($this->factory->conformite()->typesQualification(), static fn (array $t): bool => in_array((int) $t['id'], $requises, true)),
        ));
    }

    private static function codeEnrolement(): string
    {
        $code = '';
        for ($i = 0; $i < 8; ++$i) {
            $code .= self::ALPHABET_CODE[random_int(0, strlen(self::ALPHABET_CODE) - 1)];
        }

        return $code;
    }

    private static function formaterCode(string $code): string
    {
        return substr($code, 0, 4) . '-' . substr($code, 4);
    }
}
