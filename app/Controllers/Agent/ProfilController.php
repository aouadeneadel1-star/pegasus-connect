<?php

declare(strict_types=1);

namespace App\Controllers\Agent;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\DemandeModificationRepository;
use App\Services\Agents\Nir;
use App\Services\Stockage\FichierRefuseException;
use App\Services\Stockage\Stockage;
use App\Services\ValidationException;

final class ProfilController extends Controller
{
    /** Champs modifiables sur demande (cahier des charges, § 3.2.4). */
    private const CHAMPS_MODIFIABLES = [
        'adresse' => 'Adresse',
        'code_postal' => 'Code postal',
        'ville' => 'Ville',
        'telephone' => 'Téléphone',
        'email' => 'E-mail',
    ];

    public function index(Request $request): Response
    {
        $agentId = (int) $this->utilisateur($request)->agentId;
        $agent = $this->factory->agents()->trouver($agentId);

        return $this->vue('agent/profil', [
            'agent' => $agent,
            'nirMasque' => Nir::masquer($agent['nir_fin'] === null ? null : (string) $agent['nir_fin']),
            'carte' => $this->factory->conformite()->carteProCourante($agentId),
            'qualifications' => $this->factory->conformite()->qualifications($agentId),
            'affectations' => $this->factory->agents()->affectations($agentId),
            'demandes' => $this->factory->repository(DemandeModificationRepository::class)->pourAgent($agentId),
            'badge' => $this->factory->badges()->actifPourAgent($agentId),
            'champs' => self::CHAMPS_MODIFIABLES,
        ]);
    }

    public function demande(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $agentId = (int) $utilisateur->agentId;
        $agent = $this->factory->agents()->trouver($agentId);

        $validation = Validator::make($request->all(), [
            'adresse' => 'max:255',
            'code_postal' => 'max:10',
            'ville' => 'max:80',
            'telephone' => 'regex:/^[+0-9 .()-]{8,20}$/',
            'email' => 'email|max:190',
        ], self::CHAMPS_MODIFIABLES);
        if ($validation->fails()) {
            return $this->redirigerAvecErreur('/profil#modifier', self::premiereErreur($validation));
        }

        $changements = [];
        foreach ($validation->validated() as $champ => $valeur) {
            if ($valeur !== null && trim((string) $valeur) !== '' && trim((string) $valeur) !== (string) $agent[$champ]) {
                $changements[$champ] = ['avant' => (string) $agent[$champ], 'apres' => trim((string) $valeur)];
            }
        }
        if ($changements === []) {
            return $this->redirigerAvecErreur('/profil#modifier', 'Aucune modification à transmettre.');
        }

        $documentId = null;
        $justificatif = $request->file('justificatif');
        if ($justificatif !== null) {
            try {
                $documentId = $this->factory->stockage()->deposer(
                    $justificatif, [...Stockage::PDF, ...Stockage::IMAGES], 10 * 1048576, 'attestation', $agentId,
                    'Justificatif de modification de profil', $utilisateur->id, ['visible_agent' => true],
                );
            } catch (FichierRefuseException $exception) {
                return $this->redirigerAvecErreur('/profil#modifier', $exception->getMessage());
            }
        }

        $id = $this->factory->repository(DemandeModificationRepository::class)->creer($agentId, $changements, $documentId);
        $this->factory->audit()->journaliser('profil.demande', 'demande_profil', $id, ['champs' => array_keys($changements)]);
        $this->factory->notifications()->envoyer(
            $this->factory->destinataires()->rh(),
            'demande_profil',
            'Demande de modification de profil',
            $utilisateur->nomComplet . ' demande à modifier : ' . implode(', ', array_map(static fn (string $c): string => self::CHAMPS_MODIFIABLES[$c], array_keys($changements))) . '.',
            '/admin/demandes-profil',
        );

        return $this->rediriger('/profil', 'Votre demande a été transmise au service RH. Votre fiche sera mise à jour après validation.');
    }

    public function badge(Request $request): Response
    {
        $agentId = (int) $this->utilisateur($request)->agentId;

        return $this->vue('agent/badge', [
            'badge' => $this->factory->badges()->actifPourAgent($agentId),
            'historique' => $this->factory->badges()->historique($agentId),
        ]);
    }

    public function pin(Request $request): Response
    {
        try {
            $this->factory->badgeService()->definirPin(
                (int) $this->utilisateur($request)->agentId,
                (string) $request->input('pin', ''),
                (string) $request->input('pin_confirmation', ''),
            );
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur('/profil/badge', $exception->getMessage());
        }

        return $this->rediriger('/profil/badge', 'Votre code PIN est enregistré. Il vous sera demandé à chaque badgeage.');
    }

    public function perte(Request $request): Response
    {
        try {
            $this->factory->badgeService()->declarerPerte((int) $this->utilisateur($request)->agentId);
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur('/profil/badge', $exception->getMessage());
        }

        return $this->rediriger('/profil/badge', 'Votre badge est désactivé. Le service RH vous en remettra un nouveau.');
    }

    public function donnees(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $geoloc = $this->factory->consentements()->dernier($utilisateur->id, 'geolocalisation');

        return $this->vue('agent/donnees', [
            'consentements' => $this->factory->consentements()->historique($utilisateur->id),
            'geolocalisation' => $geoloc === null || (bool) $geoloc['accorde'],
            'retentionGeoloc' => $this->factory->parametres()->int('geoloc.retention_jours', 60),
            'versionNotice' => (string) $this->factory->parametres()->get('rgpd.version_notice', ''),
            'emailRh' => (string) $this->factory->parametres()->get('contact.email_rh', ''),
        ]);
    }

    public function geolocalisation(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $accorde = $request->boolean('accorde');
        $this->factory->consentements()->enregistrer(
            $utilisateur->id,
            'geolocalisation',
            (string) $this->factory->parametres()->get('rgpd.version_notice', ''),
            $accorde,
            $request->ip(),
        );
        $this->factory->audit()->journaliser('rgpd.geolocalisation', 'utilisateur', $utilisateur->id, ['accorde' => $accorde]);

        return $this->rediriger('/profil/donnees', $accorde
            ? 'La géolocalisation sera utilisée au moment de vos pointages et passages de ronde.'
            : 'Votre position ne sera plus transmise. Sur les sites à QR fixe, vos pointages seront vérifiés par votre chef d\'équipe.');
    }
}
