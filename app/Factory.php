<?php

declare(strict_types=1);

namespace App;

use App\Core\Database;
use App\Core\Env;
use App\Core\View;
use App\Repositories\AgentRepository;
use App\Repositories\AlerteRepository;
use App\Repositories\AnomalieRepository;
use App\Repositories\AppareilRepository;
use App\Repositories\BadgeRepository;
use App\Repositories\BorneRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ConformiteRepository;
use App\Repositories\ConsentementRepository;
use App\Repositories\CorrectionRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\DocumentSocieteRepository;
use App\Repositories\JetonCompteRepository;
use App\Repositories\MajorationRepository;
use App\Repositories\ModeleRondeRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\PaieRepository;
use App\Repositories\PerimetreRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\PointageRepository;
use App\Repositories\PointControleRepository;
use App\Repositories\RapportRepository;
use App\Repositories\RondeRepository;
use App\Repositories\ServiceTravailRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TentativeConnexionRepository;
use App\Repositories\UtilisateurRepository;
use App\Repositories\VacationRepository;
use App\Services\Agents\AgentService;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\AuthService;
use App\Services\Auth\Autorisation;
use App\Services\Auth\CompteService;
use App\Services\Auth\PolitiqueMotDePasse;
use App\Services\Auth\Totp;
use App\Services\Crypto\Chiffrement;
use App\Services\Crypto\Cles;
use App\Services\Destinataires;
use App\Services\Heures\HeuresService;
use App\Services\Installation\Installation;
use App\Services\Mail\EnvoiEmails;
use App\Services\Mail\FileEmails;
use App\Services\Notifications;
use App\Services\Paie\ImportPaie;
use App\Services\Parametres;
use App\Services\Pdf\GenerateurPdf;
use App\Services\Planning\PlanningService;
use App\Services\Pointage\BadgeService;
use App\Services\Pointage\CorrectionService;
use App\Services\Pointage\PointageService;
use App\Services\Qr\JetonQr;
use App\Services\Qr\QrImage;
use App\Services\Rapports\RapportMensuel;
use App\Services\Rapports\RapportRondes;
use App\Services\Rgpd\Rgpd;
use App\Services\Sauvegarde\SauvegardeBase;
use App\Services\Rondes\RondeService;
use App\Services\Securite\LimiteDebit;
use App\Services\Stockage\AccesDocuments;
use App\Services\Stockage\Stockage;
use App\Services\Stockage\UrlSignee;
use App\Services\Taches\AlertesEcheances;
use App\Services\Taches\SurveillancePointages;
use App\Services\Taches\TachesPlanifiees;
use PDO;

/**
 * Assemble les services de l'application. Chaque objet est créé à la première demande
 * puis réutilisé pendant la requête ; les dépendances restent visibles dans les constructeurs.
 */
final class Factory
{
    /** @var array<string, object> */
    private array $instances = [];

    public function __construct(private readonly string $racine)
    {
    }

    public function racine(): string
    {
        return $this->racine;
    }

    public function storage(): string
    {
        return rtrim(Env::get('STORAGE_PATH') ?? $this->racine . '/storage', '/');
    }

    public function appUrl(): string
    {
        return rtrim(Env::get('APP_URL', 'http://localhost:8000') ?? '', '/');
    }

    public function pdo(): PDO
    {
        return Database::connection();
    }

    public function view(): View
    {
        return $this->instance(View::class, fn (): View => new View($this->racine . '/app/Views'));
    }

    public function cles(): Cles
    {
        return $this->instance(Cles::class, static fn (): Cles => new Cles(Env::required('APP_KEY')));
    }

    public function chiffrement(string $usage): Chiffrement
    {
        return $this->instance('chiffrement:' . $usage, fn (): Chiffrement => new Chiffrement($this->cles(), $usage));
    }

    public function parametres(): Parametres
    {
        return $this->instance(Parametres::class, fn (): Parametres => new Parametres($this->pdo()));
    }

    public function audit(): AuditLogger
    {
        return $this->instance(AuditLogger::class, fn (): AuditLogger => new AuditLogger($this->pdo()));
    }

    public function fileEmails(): FileEmails
    {
        return $this->instance(FileEmails::class, fn (): FileEmails => new FileEmails($this->pdo()));
    }

    public function limiteDebit(): LimiteDebit
    {
        return $this->instance(LimiteDebit::class, fn (): LimiteDebit => new LimiteDebit($this->pdo()));
    }

    public function politiqueMotDePasse(): PolitiqueMotDePasse
    {
        return $this->instance(
            PolitiqueMotDePasse::class,
            fn (): PolitiqueMotDePasse => new PolitiqueMotDePasse($this->racine . '/app/config/mots-de-passe-courants.txt'),
        );
    }

    public function utilisateurs(): UtilisateurRepository
    {
        return $this->repository(UtilisateurRepository::class);
    }

    public function consentements(): ConsentementRepository
    {
        return $this->repository(ConsentementRepository::class);
    }

    public function jetons(): JetonCompteRepository
    {
        return $this->repository(JetonCompteRepository::class);
    }

    public function autorisation(): Autorisation
    {
        return $this->instance(Autorisation::class, fn (): Autorisation => new Autorisation(
            $this->utilisateurs(),
            $this->repository(PermissionRepository::class),
            $this->repository(PerimetreRepository::class),
        ));
    }

    public function permissions(): PermissionRepository
    {
        return $this->repository(PermissionRepository::class);
    }

    public function auth(): AuthService
    {
        return $this->instance(AuthService::class, fn (): AuthService => new AuthService(
            $this->utilisateurs(),
            $this->repository(TentativeConnexionRepository::class),
            $this->jetons(),
            $this->limiteDebit(),
            new Totp('Pegasus Insure'),
            $this->chiffrement('totp'),
            $this->parametres(),
            $this->audit(),
            $this->fileEmails(),
            $this->appUrl(),
        ));
    }

    // Dépôts --------------------------------------------------------------------------------

    public function agents(): AgentRepository
    {
        return $this->repository(AgentRepository::class);
    }

    public function sites(): SiteRepository
    {
        return $this->repository(SiteRepository::class);
    }

    public function clients(): ClientRepository
    {
        return $this->repository(ClientRepository::class);
    }

    public function bornes(): BorneRepository
    {
        return $this->repository(BorneRepository::class);
    }

    public function conformite(): ConformiteRepository
    {
        return $this->repository(ConformiteRepository::class);
    }

    public function documents(): DocumentRepository
    {
        return $this->repository(DocumentRepository::class);
    }

    public function documentsSociete(): DocumentSocieteRepository
    {
        return $this->repository(DocumentSocieteRepository::class);
    }

    public function pointages(): PointageRepository
    {
        return $this->repository(PointageRepository::class);
    }

    public function servicesTravail(): ServiceTravailRepository
    {
        return $this->repository(ServiceTravailRepository::class);
    }

    public function vacations(): VacationRepository
    {
        return $this->repository(VacationRepository::class);
    }

    public function badges(): BadgeRepository
    {
        return $this->repository(BadgeRepository::class);
    }

    public function rondes(): RondeRepository
    {
        return $this->repository(RondeRepository::class);
    }

    public function pointsControle(): PointControleRepository
    {
        return $this->repository(PointControleRepository::class);
    }

    public function modelesRonde(): ModeleRondeRepository
    {
        return $this->repository(ModeleRondeRepository::class);
    }

    public function anomalies(): AnomalieRepository
    {
        return $this->repository(AnomalieRepository::class);
    }

    public function majorations(): MajorationRepository
    {
        return $this->repository(MajorationRepository::class);
    }

    public function paie(): PaieRepository
    {
        return $this->repository(PaieRepository::class);
    }

    public function rapports(): RapportRepository
    {
        return $this->repository(RapportRepository::class);
    }

    public function corrections(): CorrectionRepository
    {
        return $this->repository(CorrectionRepository::class);
    }

    public function notificationsRepository(): NotificationRepository
    {
        return $this->repository(NotificationRepository::class);
    }

    // Services métier -----------------------------------------------------------------------

    public function stockage(): Stockage
    {
        return $this->instance(Stockage::class, fn (): Stockage => new Stockage($this->storage() . '/fichiers', $this->documents()));
    }

    public function urlSignee(): UrlSignee
    {
        return $this->instance(UrlSignee::class, fn (): UrlSignee => new UrlSignee($this->cles()));
    }

    public function accesDocuments(): AccesDocuments
    {
        return $this->instance(AccesDocuments::class, fn (): AccesDocuments => new AccesDocuments(
            $this->autorisation(),
            $this->documents(),
            $this->documentsSociete(),
        ));
    }

    public function jetonsQr(): JetonQr
    {
        return $this->instance(JetonQr::class, fn (): JetonQr => new JetonQr($this->cles()));
    }

    public function qrImage(): QrImage
    {
        return $this->instance(QrImage::class, static fn (): QrImage => new QrImage());
    }

    public function notifications(): Notifications
    {
        return $this->instance(Notifications::class, fn (): Notifications => new Notifications(
            $this->notificationsRepository(),
            $this->utilisateurs(),
            $this->fileEmails(),
            $this->appUrl(),
        ));
    }

    public function destinataires(): Destinataires
    {
        return $this->instance(Destinataires::class, fn (): Destinataires => new Destinataires($this->sites(), $this->utilisateurs()));
    }

    public function comptes(): CompteService
    {
        return $this->instance(CompteService::class, fn (): CompteService => new CompteService(
            $this->utilisateurs(),
            $this->jetons(),
            $this->fileEmails(),
            $this->audit(),
            $this->appUrl(),
        ));
    }

    public function agentService(): AgentService
    {
        return $this->instance(AgentService::class, fn (): AgentService => new AgentService(
            $this->pdo(),
            $this->agents(),
            $this->utilisateurs(),
            $this->conformite(),
            $this->jetons(),
            $this->chiffrement('nir'),
            $this->cles(),
            $this->stockage(),
            $this->audit(),
            $this->fileEmails(),
            $this->appUrl(),
        ));
    }

    public function pointage(): PointageService
    {
        return $this->instance(PointageService::class, fn (): PointageService => new PointageService(
            $this->pdo(),
            $this->pointages(),
            $this->servicesTravail(),
            $this->vacations(),
            $this->sites(),
            $this->bornes(),
            $this->agents(),
            $this->conformite(),
            $this->repository(AppareilRepository::class),
            $this->jetonsQr(),
            $this->parametres(),
            $this->audit(),
            $this->notifications(),
            $this->destinataires(),
        ));
    }

    public function badgeService(): BadgeService
    {
        return $this->instance(BadgeService::class, fn (): BadgeService => new BadgeService(
            $this->badges(),
            $this->agents(),
            $this->conformite(),
            $this->cles(),
            $this->pointage(),
            $this->parametres(),
            $this->audit(),
            $this->notifications(),
            $this->destinataires(),
        ));
    }

    public function correctionService(): CorrectionService
    {
        return $this->instance(CorrectionService::class, fn (): CorrectionService => new CorrectionService(
            $this->pdo(),
            $this->corrections(),
            $this->pointages(),
            $this->servicesTravail(),
            $this->majorations(),
            $this->parametres(),
            $this->audit(),
            $this->notifications(),
            $this->destinataires(),
        ));
    }

    public function rondeService(): RondeService
    {
        return $this->instance(RondeService::class, fn (): RondeService => new RondeService(
            $this->pdo(),
            $this->rondes(),
            $this->modelesRonde(),
            $this->pointsControle(),
            $this->servicesTravail(),
            $this->sites(),
            $this->anomalies(),
            $this->jetonsQr(),
            $this->stockage(),
            $this->parametres(),
            $this->audit(),
            $this->notifications(),
            $this->destinataires(),
        ));
    }

    public function planning(): PlanningService
    {
        return $this->instance(PlanningService::class, fn (): PlanningService => new PlanningService(
            $this->vacations(),
            $this->agents(),
            $this->sites(),
            $this->conformite(),
            $this->parametres(),
            $this->audit(),
            $this->notifications(),
        ));
    }

    public function heures(): HeuresService
    {
        return $this->instance(HeuresService::class, fn (): HeuresService => new HeuresService(
            $this->servicesTravail(),
            $this->vacations(),
            $this->majorations(),
            $this->parametres(),
        ));
    }

    public function importPaie(): ImportPaie
    {
        return $this->instance(ImportPaie::class, fn (): ImportPaie => new ImportPaie(
            $this->pdo(),
            $this->paie(),
            $this->agents(),
            $this->documents(),
            $this->stockage(),
            $this->audit(),
            $this->notifications(),
        ));
    }

    public function pdf(): GenerateurPdf
    {
        return $this->instance(GenerateurPdf::class, fn (): GenerateurPdf => new GenerateurPdf(
            new View($this->racine . '/app/Views'),
            $this->racine . '/public',
            $this->storage() . '/cache',
        ));
    }

    public function rapportMensuel(): RapportMensuel
    {
        return $this->instance(RapportMensuel::class, fn (): RapportMensuel => new RapportMensuel(
            $this->pdo(),
            $this->rapports(),
            $this->agents(),
            $this->conformite(),
            $this->servicesTravail(),
            $this->pointages(),
            $this->rondes(),
            $this->heures(),
            $this->pdf(),
            $this->stockage(),
            $this->parametres(),
            $this->audit(),
            $this->notifications(),
        ));
    }

    public function rapportRondes(): RapportRondes
    {
        return $this->instance(RapportRondes::class, fn (): RapportRondes => new RapportRondes(
            $this->rondes(),
            $this->anomalies(),
            $this->pdf(),
            $this->parametres(),
        ));
    }

    public function installation(): Installation
    {
        return $this->instance(Installation::class, fn (): Installation => new Installation(
            $this->pdo(),
            $this->racine,
            $this->storage() . '/locks/installation',
        ));
    }

    public function tachesPlanifiees(): TachesPlanifiees
    {
        return $this->instance(TachesPlanifiees::class, fn (): TachesPlanifiees => new TachesPlanifiees($this));
    }

    public function sauvegardeBase(): SauvegardeBase
    {
        return $this->instance(SauvegardeBase::class, fn (): SauvegardeBase => new SauvegardeBase(
            [
                'host' => Env::required('DB_HOST'),
                'port' => Env::int('DB_PORT', 3306),
                'name' => Env::required('DB_NAME'),
                'user' => Env::required('DB_USER'),
                'password' => Env::get('DB_PASSWORD', '') ?? '',
            ],
            $this->cles()->pour('sauvegarde:v1'),
            $this->storage() . '/backups',
            Env::get('MYSQLDUMP_PATH', 'mysqldump') ?? 'mysqldump',
        ));
    }

    public function rgpd(): Rgpd
    {
        return $this->instance(Rgpd::class, fn (): Rgpd => new Rgpd(
            $this->pdo(),
            $this->agents(),
            $this->documents(),
            $this->stockage(),
            $this->audit(),
            $this->storage() . '/tmp',
        ));
    }

    public function envoiEmails(): EnvoiEmails
    {
        return $this->instance(EnvoiEmails::class, fn (): EnvoiEmails => new EnvoiEmails(
            $this->fileEmails(),
            new View($this->racine . '/app/Views'),
            $this->appUrl(),
        ));
    }

    public function surveillancePointages(): SurveillancePointages
    {
        return $this->instance(SurveillancePointages::class, fn (): SurveillancePointages => new SurveillancePointages(
            $this->pointages(),
            $this->servicesTravail(),
            $this->bornes(),
            $this->repository(AlerteRepository::class),
            $this->parametres(),
            $this->notifications(),
            $this->destinataires(),
        ));
    }

    public function alertesEcheances(): AlertesEcheances
    {
        return $this->instance(AlertesEcheances::class, fn (): AlertesEcheances => new AlertesEcheances(
            $this->conformite(),
            $this->agents(),
            $this->parametres(),
            $this->notifications(),
            $this->destinataires(),
        ));
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function repository(string $class): object
    {
        return $this->instance($class, fn (): object => new $class($this->pdo()));
    }

    /**
     * @template T of object
     * @param callable(): T $create
     * @return T
     */
    private function instance(string $key, callable $create): object
    {
        return $this->instances[$key] ??= $create();
    }
}
