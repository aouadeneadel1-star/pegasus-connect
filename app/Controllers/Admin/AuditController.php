<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\Csv;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditRepository;
use App\Services\Audit\VerificationAudit;
use DateTimeImmutable;

final class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        [$du, $au, $filtres] = $this->criteres($request);
        $page = max(1, (int) $request->query('page', '1'));
        $resultat = $this->depot()->lister($du, $au, $filtres, $page);

        return $this->vue('admin/audit', [
            'lignes' => $resultat['lignes'],
            'total' => $resultat['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($resultat['total'] / AuditRepository::PAR_PAGE)),
            'filtres' => [...$filtres, 'du' => $du->setTimezone(Clock::local())->format('Y-m-d'), 'au' => $au->modify('-1 day')->setTimezone(Clock::local())->format('Y-m-d')],
            'actions' => $this->depot()->actions(),
        ]);
    }

    public function exporter(Request $request): Response
    {
        [$du, $au, $filtres] = $this->criteres($request);
        $this->factory->audit()->journaliser('audit.exporter', null, null, ['filtres' => $filtres]);

        $lignes = (function () use ($du, $au, $filtres): iterable {
            yield ['Id', 'Date', 'Action', 'Objet', 'Objet id', 'Rôle', 'Utilisateur', 'Détails', 'IP', 'Empreinte'];
            foreach ($this->depot()->exporter($du, $au, $filtres) as $ligne) {
                yield [
                    $ligne['id'],
                    Clock::fromSql((string) $ligne['created_at'])?->setTimezone(Clock::local())->format('d/m/Y H:i:s'),
                    $ligne['action'], $ligne['objet_type'], $ligne['objet_id'], $ligne['role_code'], $ligne['nom_complet'],
                    $ligne['details'], $ligne['ip'], $ligne['hash'],
                ];
            }
        })();

        return Csv::reponse('audit-' . $du->setTimezone(Clock::local())->format('Ymd') . '.csv', $lignes);
    }

    public function verifier(Request $request): Response
    {
        $resultat = (new VerificationAudit($this->factory->pdo()))->verifier();
        $this->factory->audit()->journaliser('audit.verifier', null, null, $resultat);

        return $resultat['rupture'] === null
            ? $this->rediriger('/admin/audit', sprintf('Chaîne intègre : %d entrée(s) vérifiée(s).', $resultat['lignes']))
            : $this->redirigerAvecErreur('/admin/audit', sprintf('Rupture de la chaîne à l\'entrée n° %d : le journal a pu être modifié hors de l\'application.', $resultat['rupture']));
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: array{action: string, utilisateur: int, objet_type: string, objet_id: int}}
     */
    private function criteres(Request $request): array
    {
        $aujourdhui = Clock::now()->setTimezone(Clock::local());
        $du = self::date($request->query('du'), $aujourdhui->modify('-7 days')->format('Y-m-d'));
        $au = self::date($request->query('au'), $aujourdhui->format('Y-m-d'));

        return [
            (new DateTimeImmutable($du, Clock::local()))->setTimezone(Clock::utc()),
            (new DateTimeImmutable($au, Clock::local()))->modify('+1 day')->setTimezone(Clock::utc()),
            [
                'action' => (string) $request->query('action', ''),
                'utilisateur' => (int) $request->query('utilisateur', '0'),
                'objet_type' => (string) $request->query('objet_type', ''),
                'objet_id' => (int) $request->query('objet_id', '0'),
            ],
        ];
    }

    private function depot(): AuditRepository
    {
        return $this->factory->repository(AuditRepository::class);
    }
}
