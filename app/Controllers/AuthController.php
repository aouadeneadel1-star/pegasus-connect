<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Role;
use App\Services\Auth\AuthException;
use App\Services\Auth\AuthService;
use App\Services\Auth\ResultatConnexion;
use App\Services\Qr\QrImage;

final class AuthController extends Controller
{
    public function afficherConnexion(Request $request): Response
    {
        if (is_array(Session::get(AuthService::SESSION_CLE))) {
            return Response::redirect('/');
        }

        return $this->vue('auth/connexion', ['retour' => self::retourSur($request->query('retour'))]);
    }

    public function connexion(Request $request): Response
    {
        $email = (string) $request->input('email');
        $resultat = $this->factory->auth()->tenterConnexion(
            $email,
            (string) $request->input('mot_de_passe'),
            $request->ip(),
            $request->cookie($this->nomCookieAppareil()),
            $request->userAgent(),
        );
        $retour = self::retourSur($request->input('retour'));

        return match ($resultat->statut) {
            ResultatConnexion::TROP_DE_TENTATIVES => $this->formulaireInvalide($request, 'auth/connexion', [
                'email' => 'Trop de tentatives depuis cette connexion. Réessayez dans 15 minutes.',
            ], ['retour' => $retour]),
            ResultatConnexion::ECHEC => $this->formulaireInvalide($request, 'auth/connexion', [
                'email' => 'Identifiants incorrects ou compte temporairement verrouillé.',
            ], ['retour' => $retour]),
            ResultatConnexion::SECOND_FACTEUR => Response::redirect('/connexion/2fa' . ($retour === null ? '' : '?retour=' . rawurlencode($retour))),
            default => $this->apresConnexion($request->boolean('rester_connecte'), $retour),
        };
    }

    public function afficherDeuxFacteurs(Request $request): Response
    {
        if ($this->factory->auth()->utilisateurEnAttente() === null) {
            return $this->redirigerAvecErreur('/connexion', 'Votre session a expiré. Reconnectez-vous.');
        }

        return $this->vue('auth/deux-facteurs', ['retour' => self::retourSur($request->query('retour'))]);
    }

    public function deuxFacteurs(Request $request): Response
    {
        $utilisateurId = $this->factory->auth()->utilisateurEnAttente();
        if ($utilisateurId === null) {
            return $this->redirigerAvecErreur('/connexion', 'Votre session a expiré. Reconnectez-vous.');
        }

        $retour = self::retourSur($request->input('retour'));
        try {
            $jetonAppareil = $this->factory->auth()->validerSecondFacteur(
                $utilisateurId,
                (string) $request->input('code'),
                $request->boolean('appareil_confiance'),
                $request->userAgent(),
            );
        } catch (AuthException $exception) {
            return $this->formulaireInvalide($request, 'auth/deux-facteurs', ['code' => $exception->getMessage()], ['retour' => $retour]);
        }

        $response = $this->apresConnexion(false, $retour);
        if ($jetonAppareil !== null) {
            $response->withCookie($this->nomCookieAppareil(), $jetonAppareil, [
                'expires' => time() + AuthService::dureeAppareilConfiance(),
                'path' => '/',
                'secure' => $this->https(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        return $response;
    }

    public function deconnexion(Request $request): Response
    {
        $this->factory->auth()->deconnecter();
        Session::flash('info', 'Vous êtes déconnecté.');

        return Response::redirect('/connexion');
    }

    public function afficherEnrolement(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        if ($utilisateur->totpActif) {
            return $this->rediriger($utilisateur->role->accueil(), 'La double authentification est déjà active sur votre compte.');
        }

        $enrolement = $this->factory->auth()->preparerEnrolement($utilisateur->id, $utilisateur->email);

        return $this->vue('auth/enrolement-2fa', [
            'secret' => $enrolement['secret'],
            'qr' => (new QrImage())->dataUri($enrolement['url'], 240),
            'obligatoire' => $this->factory->auth()->exigeDeuxFacteurs($utilisateur->role),
        ]);
    }

    public function enrolement(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);

        try {
            $codes = $this->factory->auth()->confirmerEnrolement($utilisateur->id, (string) $request->input('code'));
        } catch (AuthException $exception) {
            $enrolement = $this->factory->auth()->preparerEnrolement($utilisateur->id, $utilisateur->email);

            return $this->formulaireInvalide($request, 'auth/enrolement-2fa', ['code' => $exception->getMessage()], [
                'secret' => $enrolement['secret'],
                'qr' => (new QrImage())->dataUri($enrolement['url'], 240),
                'obligatoire' => $this->factory->auth()->exigeDeuxFacteurs($utilisateur->role),
            ]);
        }

        return $this->vue('auth/codes-secours', ['codes' => $codes, 'suite' => $utilisateur->role->accueil()]);
    }

    public function afficherOubli(Request $request): Response
    {
        return $this->vue('auth/oubli');
    }

    public function oubli(Request $request): Response
    {
        $email = (string) $request->input('email');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $this->factory->auth()->demanderReinitialisation($email, $request->ip());
        }

        // Réponse identique que le compte existe ou non.
        return $this->rediriger('/connexion', 'Si un compte correspond à cette adresse, un lien de réinitialisation vient d\'être envoyé. Il est valable 30 minutes.');
    }

    public function afficherReinitialisation(Request $request): Response
    {
        return $this->afficherDefinition($request, 'reinitialisation');
    }

    public function reinitialisation(Request $request): Response
    {
        return $this->definir($request, 'reinitialisation');
    }

    public function afficherInvitation(Request $request): Response
    {
        return $this->afficherDefinition($request, 'invitation');
    }

    public function invitation(Request $request): Response
    {
        return $this->definir($request, 'invitation');
    }

    public function afficherReauthentification(Request $request): Response
    {
        return $this->vue('auth/reauthentifier', ['retour' => self::retourSur($request->query('retour')) ?? '/']);
    }

    public function reauthentification(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $retour = self::retourSur($request->input('retour')) ?? $utilisateur->role->accueil();

        try {
            $valide = $this->factory->auth()->reauthentifier($utilisateur->id, (string) $request->input('secret'));
        } catch (AuthException $exception) {
            return $this->formulaireInvalide($request, 'auth/reauthentifier', ['secret' => $exception->getMessage()], ['retour' => $retour]);
        }

        if (!$valide) {
            return $this->formulaireInvalide($request, 'auth/reauthentifier', ['secret' => 'Mot de passe ou code incorrect.'], ['retour' => $retour]);
        }

        return Response::redirect($retour);
    }

    public function afficherMotDePasse(Request $request): Response
    {
        return $this->vue('auth/mot-de-passe');
    }

    public function motDePasse(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $validation = Validator::make($request->all(), [
            'actuel' => 'required',
            'mot_de_passe' => 'required|same:mot_de_passe_confirmation',
        ], ['actuel' => 'Le mot de passe actuel', 'mot_de_passe' => 'Le nouveau mot de passe']);

        if ($validation->fails()) {
            return $this->formulaireInvalide($request, 'auth/mot-de-passe', $validation->errors());
        }

        try {
            $this->factory->auth()->changerMotDePasse(
                $utilisateur->id,
                (string) $request->input('actuel'),
                (string) $request->input('mot_de_passe'),
                $this->factory->politiqueMotDePasse(),
            );
        } catch (AuthException $exception) {
            return $this->formulaireInvalide($request, 'auth/mot-de-passe', ['mot_de_passe' => $exception->getMessage()]);
        }

        return $this->rediriger($utilisateur->role->accueil(), 'Votre mot de passe a été modifié.');
    }

    public function afficherConsentements(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $geoloc = $this->factory->consentements()->dernier($utilisateur->id, 'geolocalisation');

        return $this->vue('auth/consentements', [
            'geolocalisation' => $geoloc === null ? null : (bool) $geoloc['accorde'],
            'telephone' => (string) $this->factory->parametres()->get('contact.telephone_permanence', ''),
        ]);
    }

    public function consentements(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        if (!$request->boolean('information_lue')) {
            return $this->formulaireInvalide($request, 'auth/consentements', [
                'information_lue' => 'Confirmez avoir pris connaissance de la notice pour continuer.',
            ], ['geolocalisation' => null, 'telephone' => (string) $this->factory->parametres()->get('contact.telephone_permanence', '')]);
        }

        $version = (string) $this->factory->parametres()->get('rgpd.version_notice', '2026-10');
        $consentements = $this->factory->consentements();
        $consentements->enregistrer($utilisateur->id, 'information_salarie', $version, true, $request->ip());
        $consentements->enregistrer($utilisateur->id, 'geolocalisation', $version, $request->input('geolocalisation') === 'oui', $request->ip());

        return $this->rediriger($utilisateur->role->accueil(), 'Vos choix sont enregistrés. Vous pouvez les modifier à tout moment dans « Mes données ».');
    }

    private function afficherDefinition(Request $request, string $type): Response
    {
        $jeton = $request->param('jeton');
        $utilisateur = $this->factory->auth()->utilisateurDuJeton($jeton, $type);
        if ($utilisateur === null) {
            return $this->vue('auth/lien-expire', ['type' => $type], 410);
        }

        return $this->vue('auth/definir-mot-de-passe', ['type' => $type, 'jeton' => $jeton, 'nom' => $utilisateur['nom_complet']]);
    }

    private function definir(Request $request, string $type): Response
    {
        $jeton = $request->param('jeton');
        $motDePasse = (string) $request->input('mot_de_passe');
        $donnees = ['type' => $type, 'jeton' => $jeton, 'nom' => ''];

        if ($motDePasse !== (string) $request->input('mot_de_passe_confirmation')) {
            return $this->formulaireInvalide($request, 'auth/definir-mot-de-passe', ['mot_de_passe' => 'Les deux mots de passe ne correspondent pas.'], $donnees);
        }

        try {
            $id = $this->factory->auth()->definirMotDePasse($jeton, $type, $motDePasse, $this->factory->politiqueMotDePasse());
        } catch (AuthException $exception) {
            return $this->formulaireInvalide($request, 'auth/definir-mot-de-passe', ['mot_de_passe' => $exception->getMessage()], $donnees);
        }

        if ($type === 'invitation') {
            $this->factory->auth()->connecterApresActivation($id);

            return $this->rediriger('/', 'Votre compte est activé. Bienvenue sur Pegasus Connect.');
        }

        return $this->rediriger('/connexion', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');
    }

    private function apresConnexion(bool $resterConnecte, ?string $retour): Response
    {
        $auth = Session::get(AuthService::SESSION_CLE);
        $utilisateur = is_array($auth) ? $this->factory->utilisateurs()->trouver((int) $auth['id']) : null;
        $role = $utilisateur === null ? Role::Agent : Role::fromId((int) $utilisateur['role_id']);

        $response = Response::redirect($retour ?? $role->accueil());

        // « Rester connecté » prolonge le cookie de session sur la durée d'une vacation (agents uniquement).
        if ($resterConnecte && $role->estTerrain()) {
            $response->withCookie(session_name() ?: 'pgs', session_id() ?: '', [
                'expires' => time() + $role->dureeSession()['maximum'],
                'path' => '/',
                'secure' => $this->https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        return $response;
    }

    private function nomCookieAppareil(): string
    {
        return $this->https() ? '__Host-pgs_ac' : 'pgs_ac';
    }

    private function https(): bool
    {
        return str_starts_with($this->factory->appUrl(), 'https://');
    }

    /**
     * N'accepte qu'un chemin interne : jamais de redirection vers un autre site.
     */
    private static function retourSur(?string $retour): ?string
    {
        if ($retour === null || $retour === '' || !str_starts_with($retour, '/') || str_starts_with($retour, '//') || str_contains($retour, '\\')) {
            return null;
        }

        return $retour;
    }
}
