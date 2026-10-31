<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\Csv;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Perimetre;
use App\Repositories\RondeRepository;
use DateTimeImmutable;

/**
 * Portail client (cahier des charges, § 3.13) : présence sur ses sites, rondes, anomalies
 * qui lui sont visibles et rapports. Tout objet hors de ses sites répond 404.
 */
final class PortailController extends Controller
{
    public function index(Request $request): Response
    {
        $perimetre = $this->perimetre($request);
        $options = $this->options($request);
        $sites = [];
        foreach ($this->factory->sites()->lister($perimetre) as $site) {
            $sites[] = [
                ...$site,
                'presents' => $this->presents((int) $site['id'], $options),
                'rondes' => array_slice($this->rondesRecentes((int) $site['id'], $options), 0, 3),
            ];
        }

        return $this->vue('client/tableau', [
            'sites' => $sites,
            'anomalies' => $this->factory->anomalies()->lister($perimetre, ['statut' => 'a_traiter'], true, 10),
            'nomsAgents' => $options['noms_agents'],
        ]);
    }

    public function site(Request $request): Response
    {
        $site = $this->siteAccessible($request, $request->intParam('id'));
        $options = $this->options($request);

        return $this->vue('client/site', [
            'site' => $site,
            'presents' => $this->presents((int) $site['id'], $options),
            'rondes' => $this->rondesRecentes((int) $site['id'], $options),
            'anomalies' => $this->factory->anomalies()->lister(Perimetre::tout(), ['site' => (int) $site['id']], true, 20),
            'nomsAgents' => $options['noms_agents'],
        ]);
    }

    public function rondes(Request $request): Response
    {
        $perimetre = $this->perimetre($request);
        $options = $this->options($request);
        $aujourdhui = Clock::now()->setTimezone(Clock::local());
        $du = self::date($request->query('du'), $aujourdhui->modify('-7 days')->format('Y-m-d'));
        $au = self::date($request->query('au'), $aujourdhui->format('Y-m-d'));
        $filtres = ['site' => (int) $request->query('site', '0'), 'statut' => (string) $request->query('statut', '')];
        $page = max(1, (int) $request->query('page', '1'));

        $resultat = $this->factory->rondes()->lister(
            $perimetre,
            (new DateTimeImmutable($du, Clock::local()))->setTimezone(Clock::utc()),
            (new DateTimeImmutable($au, Clock::local()))->modify('+1 day')->setTimezone(Clock::utc()),
            $filtres,
            $page,
        );

        return $this->vue('client/rondes', [
            'rondes' => array_map(fn (array $r): array => $this->masquer($r, $options), array_filter($resultat['lignes'], static fn (array $r): bool => $r['statut'] !== 'a_venir')),
            'total' => $resultat['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($resultat['total'] / RondeRepository::PAR_PAGE)),
            'filtres' => [...$filtres, 'du' => $du, 'au' => $au],
            'sites' => $this->factory->sites()->choix($perimetre),
            'nomsAgents' => $options['noms_agents'],
        ]);
    }

    public function ronde(Request $request): Response
    {
        $ronde = $this->factory->rondes()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        $this->siteAccessible($request, (int) $ronde['site_id']);
        if ($ronde['statut'] === 'a_venir') {
            throw HttpException::notFound();
        }
        $options = $this->options($request);

        return $this->vue('client/ronde', [
            'ronde' => $this->masquer($ronde, $options),
            'etat' => $ronde['points_prevus'] === null ? null : $this->factory->rondeService()->etat((int) $ronde['id']),
            'anomalies' => $this->factory->anomalies()->lister(Perimetre::tout(), ['ronde' => (int) $ronde['id']], true),
            'nomsAgents' => $options['noms_agents'],
        ]);
    }

    public function anomalies(Request $request): Response
    {
        $perimetre = $this->perimetre($request);
        $filtres = ['site' => (int) $request->query('site', '0'), 'statut' => (string) $request->query('statut', ''), 'gravite' => (string) $request->query('gravite', '')];

        return $this->vue('client/anomalies', [
            'anomalies' => $this->factory->anomalies()->lister($perimetre, $filtres, true),
            'filtres' => $filtres,
            'sites' => $this->factory->sites()->choix($perimetre),
        ]);
    }

    public function anomalie(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $anomalie = $this->factory->anomalies()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        $this->siteAccessible($request, (int) $anomalie['site_id']);
        if (!(bool) $anomalie['visible_client']) {
            throw HttpException::notFound();
        }
        if (!$this->options($request)['noms_agents']) {
            $anomalie['nom'] = null;
            $anomalie['prenom'] = null;
        }

        return $this->vue('client/anomalie', [
            'anomalie' => $anomalie,
            'photos' => array_map(fn (int $id): string => $this->factory->urlSignee()->pour($id, $utilisateur->id, true), $this->factory->anomalies()->photos((int) $anomalie['id'])),
        ]);
    }

    public function rapports(Request $request): Response
    {
        $perimetre = $this->perimetre($request);
        $siteId = (int) $request->query('site', '0');
        $format = (string) $request->query('format', '');
        if ($siteId === 0 || !in_array($format, ['pdf', 'csv'], true)) {
            $mois = Clock::now()->setTimezone(Clock::local())->modify('first day of last month');

            return $this->vue('client/rapports', [
                'sites' => $this->factory->sites()->choix($perimetre),
                'du' => $mois->format('Y-m-d'),
                'au' => $mois->modify('last day of this month')->format('Y-m-d'),
            ]);
        }

        $site = $this->siteAccessible($request, $siteId);
        $du = self::date($request->query('du'), Clock::now()->setTimezone(Clock::local())->format('Y-m-01'));
        $au = self::date($request->query('au'), Clock::now()->setTimezone(Clock::local())->format('Y-m-d'));
        $debut = (new DateTimeImmutable($du, Clock::local()))->setTimezone(Clock::utc());
        $fin = (new DateTimeImmutable($au, Clock::local()))->modify('+1 day')->setTimezone(Clock::utc());
        if ($fin <= $debut || $fin->getTimestamp() - $debut->getTimestamp() > 92 * 86400) {
            return $this->redirigerAvecErreur('/client/rapports', 'La période doit être comprise entre 1 et 92 jours.');
        }

        $donnees = $this->factory->rapportRondes()->donnees($site, $debut, $fin, true, $this->options($request)['noms_agents']);
        $this->factory->audit()->journaliser('client.rapport', 'site', $siteId, ['du' => $du, 'au' => $au, 'format' => $format]);
        $nom = sprintf('rondes-%s-%s-%s', $site['code'], $du, $au);

        return $format === 'csv'
            ? Csv::reponse($nom . '.csv', $this->factory->rapportRondes()->lignesCsv($donnees))
            : Response::download($this->factory->rapportRondes()->pdf($donnees), $nom . '.pdf', 'application/pdf');
    }

    private function perimetre(Request $request): Perimetre
    {
        return $this->factory->autorisation()->perimetre($this->utilisateur($request), 'client.portail');
    }

    /**
     * @return array<string, mixed>
     */
    private function siteAccessible(Request $request, int $siteId): array
    {
        $site = $this->factory->sites()->trouver($siteId);
        if ($site === null || !$this->perimetre($request)->couvreSite($siteId) || (int) $site['client_id'] !== $this->utilisateur($request)->clientId) {
            throw HttpException::notFound();
        }

        return $site;
    }

    /**
     * @return array{noms_agents: bool, validite_carte_pro: bool}
     */
    private function options(Request $request): array
    {
        return $this->factory->clients()->options((int) $this->utilisateur($request)->clientId);
    }

    /**
     * @param array{noms_agents: bool, validite_carte_pro: bool} $options
     * @return list<array<string, mixed>>
     */
    private function presents(int $siteId, array $options): array
    {
        return array_map(static fn (array $p): array => [
            'debut' => $p['debut'],
            'nom' => $options['noms_agents'] ? $p['prenom'] . ' ' . mb_substr((string) $p['nom'], 0, 1) . '.' : null,
        ], $this->factory->servicesTravail()->presentsSurSite($siteId));
    }

    /**
     * @param array{noms_agents: bool, validite_carte_pro: bool} $options
     * @return list<array<string, mixed>>
     */
    private function rondesRecentes(int $siteId, array $options): array
    {
        $rondes = $this->factory->rondes()->pourSiteEntre($siteId, Clock::now()->modify('-2 days'), Clock::now()->modify('+1 minute'));
        $rondes = array_values(array_filter($rondes, static fn (array $r): bool => $r['statut'] !== 'a_venir'));

        return array_map(fn (array $r): array => $this->masquer($r, $options), array_reverse($rondes));
    }

    /**
     * @param array<string, mixed> $ronde
     * @param array{noms_agents: bool, validite_carte_pro: bool} $options
     * @return array<string, mixed>
     */
    private function masquer(array $ronde, array $options): array
    {
        if (!$options['noms_agents']) {
            $ronde['nom'] = null;
            $ronde['prenom'] = null;
            $ronde['matricule'] = null;
        }

        return $ronde;
    }
}
