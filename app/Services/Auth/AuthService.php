<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Session;
use App\Models\Role;
use App\Repositories\JetonCompteRepository;
use App\Repositories\TentativeConnexionRepository;
use App\Repositories\UtilisateurRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Crypto\Chiffrement;
use App\Services\Mail\FileEmails;
use App\Services\Parametres;
use App\Services\Securite\LimiteDebit;

final class AuthService
{
    public const SESSION_CLE = 'auth';
    private const SESSION_EN_ATTENTE = 'auth_en_attente';
    private const ECHECS_AVANT_VERROU = 5;
    private const ECHECS_IP_MAX = 20;
    private const FENETRE_MINUTES = 15;
    private const VERROU_MINUTES = 15;
    private const DUREE_APPAREIL_CONFIANCE = 30 * 86400;

    // Hachage de référence pour garder un temps de réponse constant quand le compte n'existe pas.
    private const HASH_LEURRE = '$2y$12$qnkgcg.I4nuhoRHS8YU7f.m7Qa.6lYh3htY3yprvJG0DaqHjgsIo.';

    public function __construct(
        private readonly UtilisateurRepository $utilisateurs,
        private readonly TentativeConnexionRepository $tentatives,
        private readonly JetonCompteRepository $jetons,
        private readonly LimiteDebit $limites,
        private readonly Totp $totp,
        private readonly Chiffrement $chiffrementTotp,
        private readonly Parametres $parametres,
        private readonly AuditLogger $audit,
        private readonly FileEmails $emails,
        private readonly string $appUrl,
    ) {
    }

    public function tenterConnexion(string $email, string $motDePasse, string $ip, ?string $jetonAppareil, string $userAgent): ResultatConnexion
    {
        $depuis = Clock::now()->modify('-' . self::FENETRE_MINUTES . ' minutes');
        if ($this->tentatives->echecsParIp($ip, $depuis) >= self::ECHECS_IP_MAX) {
            return ResultatConnexion::tropDeTentatives();
        }

        $utilisateur = $this->utilisateurs->trouverParEmail($email);
        $hash = $utilisateur['password_hash'] ?? null;
        $valide = password_verify($motDePasse, is_string($hash) ? $hash : self::HASH_LEURRE);

        if ($utilisateur === null || !is_string($hash) || $utilisateur['statut'] !== 'actif') {
            $this->tentatives->enregistrer($email, $ip, false);

            return ResultatConnexion::echec();
        }

        $id = (int) $utilisateur['id'];
        $verrouJusqua = Clock::fromSql($utilisateur['verrouille_jusqu_a']);
        if ($verrouJusqua !== null && $verrouJusqua > Clock::now()) {
            $this->tentatives->enregistrer($email, $ip, false);

            return ResultatConnexion::echec();
        }

        if (!$valide) {
            $this->tentatives->enregistrer($email, $ip, false);
            $this->enregistrerEchec($id, (string) $utilisateur['email'], $this->tentatives->echecsParEmail($email, $depuis));

            return ResultatConnexion::echec();
        }

        $this->tentatives->enregistrer($email, $ip, true);
        if (PolitiqueMotDePasse::doitRehacher($hash)) {
            $this->utilisateurs->definirMotDePasse($id, PolitiqueMotDePasse::hacher($motDePasse));
        }

        if ((bool) $utilisateur['totp_actif'] && !$this->appareilDeConfiance($id, $jetonAppareil, $userAgent)) {
            Session::regenerate();
            Session::set(self::SESSION_EN_ATTENTE, ['id' => $id, 'depuis' => time()]);

            return ResultatConnexion::secondFacteur($id);
        }

        $this->ouvrirSession($utilisateur);

        return ResultatConnexion::connecte($id);
    }

    public function utilisateurEnAttente(): ?int
    {
        $attente = Session::get(self::SESSION_EN_ATTENTE);
        if (!is_array($attente) || time() - (int) $attente['depuis'] > 600) {
            return null;
        }

        return (int) $attente['id'];
    }

    /**
     * @return string|null jeton d'appareil de confiance à poser en cookie, si demandé
     */
    public function validerSecondFacteur(int $utilisateurId, string $code, bool $faireConfiance, string $userAgent): ?string
    {
        $cle = '2fa:' . $utilisateurId;
        if ($this->limites->estBloque($cle, self::ECHECS_AVANT_VERROU)) {
            throw new AuthException('Trop de codes erronés. Réessayez dans quelques minutes.');
        }

        $utilisateur = $this->utilisateurs->trouver($utilisateurId);
        if ($utilisateur === null || $utilisateur['statut'] !== 'actif' || !(bool) $utilisateur['totp_actif']) {
            throw new AuthException('Session expirée. Reconnectez-vous.');
        }

        if (!$this->verifierCodeOuSecours($utilisateur, $code)) {
            if (!$this->limites->essayer($cle, self::ECHECS_AVANT_VERROU - 1, self::VERROU_MINUTES * 60)) {
                $this->verrouiller($utilisateurId, (string) $utilisateur['email']);
            }
            throw new AuthException('Code incorrect.');
        }

        $this->limites->reinitialiser($cle);
        Session::remove(self::SESSION_EN_ATTENTE);
        $this->ouvrirSession($utilisateur);

        return $faireConfiance ? $this->creerAppareilDeConfiance($utilisateurId, $userAgent) : null;
    }

    /**
     * @return array{secret: string, url: string}
     */
    public function preparerEnrolement(int $utilisateurId, string $email): array
    {
        $secret = Session::get('totp_en_attente');
        if (!is_string($secret)) {
            $secret = $this->totp->genererSecret();
            Session::set('totp_en_attente', $secret);
        }

        return ['secret' => $secret, 'url' => $this->totp->urlProvisionnement($email, $secret)];
    }

    /**
     * @return list<string> codes de secours en clair, affichés une seule fois
     */
    public function confirmerEnrolement(int $utilisateurId, string $code): array
    {
        $secret = Session::get('totp_en_attente');
        if (!is_string($secret)) {
            throw new AuthException('Le code QR a expiré. Rechargez la page.');
        }

        $horodatage = $this->totp->verifier($secret, $code, null);
        if ($horodatage === null) {
            throw new AuthException('Code incorrect. Vérifiez l\'heure de votre téléphone et réessayez.');
        }

        $codes = [];
        $hashes = [];
        for ($i = 0; $i < 10; $i++) {
            $brut = strtoupper(bin2hex(random_bytes(5)));
            $codes[] = substr($brut, 0, 5) . '-' . substr($brut, 5);
            $hashes[] = password_hash($brut, PASSWORD_DEFAULT);
        }

        $this->utilisateurs->definirTotp(
            $utilisateurId,
            $this->chiffrementTotp->chiffrer($secret),
            true,
            json_encode($hashes, JSON_THROW_ON_ERROR),
        );
        $this->utilisateurs->definirDernierTotp($utilisateurId, $horodatage);
        Session::remove('totp_en_attente');

        $auth = Session::get(self::SESSION_CLE);
        if (is_array($auth)) {
            $auth['enrolement'] = false;
            Session::set(self::SESSION_CLE, $auth);
        }

        $this->audit->journaliser('compte.2fa.activer', 'utilisateur', $utilisateurId);

        return $codes;
    }

    public function reauthentifier(int $utilisateurId, string $secret): bool
    {
        $cle = 'reauth:' . $utilisateurId;
        if ($this->limites->estBloque($cle, self::ECHECS_AVANT_VERROU)) {
            throw new AuthException('Trop d\'essais. Réessayez dans quelques minutes.');
        }

        $utilisateur = $this->utilisateurs->trouver($utilisateurId);
        if ($utilisateur === null) {
            return false;
        }

        $valide = is_string($utilisateur['password_hash']) && password_verify($secret, $utilisateur['password_hash']);
        if (!$valide && (bool) $utilisateur['totp_actif']) {
            $valide = $this->verifierCodeTotp($utilisateur, $secret);
        }

        if (!$valide) {
            $this->limites->essayer($cle, self::ECHECS_AVANT_VERROU, self::VERROU_MINUTES * 60);

            return false;
        }

        $this->limites->reinitialiser($cle);
        $this->utilisateurs->enregistrerAuthForte($utilisateurId);

        return true;
    }

    public function demanderReinitialisation(string $email, string $ip): void
    {
        if (!$this->limites->essayer('oubli:' . $ip, 5, 3600)) {
            return;
        }

        $utilisateur = $this->utilisateurs->trouverParEmail($email);
        if ($utilisateur === null || $utilisateur['statut'] !== 'actif') {
            return;
        }

        $id = (int) $utilisateur['id'];
        $this->jetons->invaliderPour($id, 'reinitialisation');
        $jeton = $this->jetons->creer($id, 'reinitialisation', 30 * 60);

        $this->emails->ajouter((string) $utilisateur['email'], 'Réinitialisation de votre mot de passe', 'reinitialisation', [
            'nom' => (string) $utilisateur['nom_complet'],
            'lien' => $this->appUrl . '/mot-de-passe/reinitialiser/' . $jeton,
        ]);
        $this->audit->journaliser('compte.mdp.demande', 'utilisateur', $id, [], $id);
    }

    /**
     * @return array<string, mixed>|null utilisateur associé au jeton
     */
    public function utilisateurDuJeton(string $jeton, string $type): ?array
    {
        $ligne = $this->jetons->trouverValide($jeton, $type);

        return $ligne === null ? null : $this->utilisateurs->trouver((int) $ligne['utilisateur_id']);
    }

    /**
     * Fin d'invitation ou de réinitialisation : le mot de passe est défini, le jeton consommé.
     */
    public function definirMotDePasse(string $jeton, string $type, string $motDePasse, PolitiqueMotDePasse $politique): int
    {
        $ligne = $this->jetons->trouverValide($jeton, $type) ?? throw new AuthException('Ce lien a expiré ou a déjà été utilisé.');
        $utilisateur = $this->utilisateurs->trouver((int) $ligne['utilisateur_id']) ?? throw new AuthException('Compte introuvable.');

        $erreur = $politique->erreur($motDePasse, [(string) $utilisateur['email'], (string) $utilisateur['nom_complet']]);
        if ($erreur !== null) {
            throw new AuthException($erreur);
        }

        $id = (int) $utilisateur['id'];
        $this->jetons->consommer((int) $ligne['id']);
        $this->utilisateurs->definirMotDePasse($id, PolitiqueMotDePasse::hacher($motDePasse));
        $this->utilisateurs->invaliderSessions($id);

        if ($type === 'invitation') {
            $this->utilisateurs->activer($id);
            $this->audit->journaliser('compte.activer', 'utilisateur', $id, [], $id);
        } else {
            $this->jetons->invaliderPour($id, 'appareil_confiance');
            $this->audit->journaliser('compte.mdp.reinitialiser', 'utilisateur', $id, [], $id);
        }

        return $id;
    }

    public function connecterApresActivation(int $utilisateurId): void
    {
        $utilisateur = $this->utilisateurs->trouver($utilisateurId);
        if ($utilisateur !== null && $utilisateur['statut'] === 'actif') {
            $this->ouvrirSession($utilisateur);
        }
    }

    public function changerMotDePasse(int $utilisateurId, string $actuel, string $nouveau, PolitiqueMotDePasse $politique): void
    {
        $utilisateur = $this->utilisateurs->trouver($utilisateurId) ?? throw new AuthException('Compte introuvable.');
        if (!is_string($utilisateur['password_hash']) || !password_verify($actuel, $utilisateur['password_hash'])) {
            throw new AuthException('Le mot de passe actuel est incorrect.');
        }

        $erreur = $politique->erreur($nouveau, [(string) $utilisateur['email'], (string) $utilisateur['nom_complet']]);
        if ($erreur !== null) {
            throw new AuthException($erreur);
        }

        $this->utilisateurs->definirMotDePasse($utilisateurId, PolitiqueMotDePasse::hacher($nouveau));
        $this->utilisateurs->invaliderSessions($utilisateurId);
        $this->audit->journaliser('compte.mdp.modifier', 'utilisateur', $utilisateurId);

        $this->ouvrirSession($this->utilisateurs->trouver($utilisateurId) ?? $utilisateur);
    }

    public function deconnecter(): void
    {
        $auth = Session::get(self::SESSION_CLE);
        if (is_array($auth)) {
            $this->audit->journaliser('connexion.fin', 'utilisateur', (int) $auth['id'], [], (int) $auth['id']);
        }
        Session::clear();
    }

    public function revoquerAppareilsDeConfiance(int $utilisateurId): void
    {
        $this->jetons->invaliderPour($utilisateurId, 'appareil_confiance');
    }

    /**
     * @param array<string, mixed> $utilisateur
     */
    private function ouvrirSession(array $utilisateur): void
    {
        $id = (int) $utilisateur['id'];
        $role = Role::fromId((int) $utilisateur['role_id']);

        Session::regenerate();
        Csrf::rotate();
        Session::set(self::SESSION_CLE, [
            'id' => $id,
            'version' => (int) $utilisateur['session_version'],
            'debut' => time(),
            'activite' => time(),
            'enrolement' => !(bool) $utilisateur['totp_actif'] && $this->exigeDeuxFacteurs($role),
        ]);

        $this->utilisateurs->enregistrerConnexion($id);
        $this->audit->journaliser('connexion.reussie', 'utilisateur', $id, [], $id);
    }

    public function exigeDeuxFacteurs(Role $role): bool
    {
        $roles = $this->parametres->get('2fa.obligatoire_roles', ['chef', 'rh', 'admin', 'client']);

        return is_array($roles) && in_array($role->value, $roles, true);
    }

    private function enregistrerEchec(int $id, string $email, int $echecsRecents): void
    {
        $verrou = $echecsRecents >= self::ECHECS_AVANT_VERROU
            ? Clock::now()->modify('+' . self::VERROU_MINUTES . ' minutes')
            : null;
        $this->utilisateurs->enregistrerEchec($id, $verrou);

        if ($verrou !== null) {
            $this->notifierVerrouillage($id, $email);
        }
    }

    private function verrouiller(int $id, string $email): void
    {
        $this->utilisateurs->enregistrerEchec($id, Clock::now()->modify('+' . self::VERROU_MINUTES . ' minutes'));
        $this->notifierVerrouillage($id, $email);
        Session::remove(self::SESSION_EN_ATTENTE);
    }

    private function notifierVerrouillage(int $id, string $email): void
    {
        $this->audit->journaliser('connexion.verrouillage', 'utilisateur', $id, [], $id);
        $this->emails->ajouter($email, 'Votre compte a été temporairement verrouillé', 'compte-verrouille', [
            'minutes' => self::VERROU_MINUTES,
            'lien' => $this->appUrl . '/mot-de-passe/oubli',
        ]);
    }

    /**
     * @param array<string, mixed> $utilisateur
     */
    private function verifierCodeOuSecours(array $utilisateur, string $code): bool
    {
        $code = strtoupper(trim($code));
        if (preg_match('/^\d{6}$/', preg_replace('/\s/', '', $code) ?? '') === 1) {
            return $this->verifierCodeTotp($utilisateur, $code);
        }

        return $this->consommerCodeSecours($utilisateur, $code);
    }

    /**
     * @param array<string, mixed> $utilisateur
     */
    private function verifierCodeTotp(array $utilisateur, string $code): bool
    {
        if (!is_string($utilisateur['totp_secret_chiffre'] ?? null)) {
            return false;
        }

        $secret = $this->chiffrementTotp->dechiffrer($utilisateur['totp_secret_chiffre']);
        $dernier = $utilisateur['totp_dernier_horodatage'] === null ? null : (int) $utilisateur['totp_dernier_horodatage'];
        $horodatage = $this->totp->verifier($secret, $code, $dernier);

        if ($horodatage === null) {
            return false;
        }

        $this->utilisateurs->definirDernierTotp((int) $utilisateur['id'], $horodatage);

        return true;
    }

    /**
     * @param array<string, mixed> $utilisateur
     */
    private function consommerCodeSecours(array $utilisateur, string $code): bool
    {
        $brut = str_replace(['-', ' '], '', $code);
        $hashes = json_decode((string) ($utilisateur['codes_secours'] ?? '[]'), true);
        if (!is_array($hashes) || strlen($brut) !== 10) {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && password_verify($brut, $hash)) {
                unset($hashes[$index]);
                $this->utilisateurs->definirCodesSecours((int) $utilisateur['id'], json_encode(array_values($hashes), JSON_THROW_ON_ERROR));
                $this->audit->journaliser('compte.2fa.code_secours', 'utilisateur', (int) $utilisateur['id'], ['restants' => count($hashes)], (int) $utilisateur['id']);

                return true;
            }
        }

        return false;
    }

    private function appareilDeConfiance(int $utilisateurId, ?string $jeton, string $userAgent): bool
    {
        if ($jeton === null || $jeton === '') {
            return false;
        }

        $ligne = $this->jetons->trouverValide($jeton, 'appareil_confiance');
        if ($ligne === null || (int) $ligne['utilisateur_id'] !== $utilisateurId) {
            return false;
        }

        $meta = json_decode((string) $ligne['meta'], true);

        return is_array($meta) && hash_equals((string) ($meta['navigateur'] ?? ''), hash('sha256', $userAgent));
    }

    private function creerAppareilDeConfiance(int $utilisateurId, string $userAgent): string
    {
        return $this->jetons->creer($utilisateurId, 'appareil_confiance', self::DUREE_APPAREIL_CONFIANCE, [
            'navigateur' => hash('sha256', $userAgent),
        ]);
    }

    public static function dureeAppareilConfiance(): int
    {
        return self::DUREE_APPAREIL_CONFIANCE;
    }
}
