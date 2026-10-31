<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

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
 * Suivi des rondes et rapports de rondes. Le plan temps réel est prévu dans un lot ultérieur :
 * la fiche d'une ronde liste ses passages dans l'ordre.
 */
final class RondeController extends Controller
{
    public function index(Request $request): Response
    {
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'rondes.suivre');
        $aujourdhui = Clock::now()->setTimezone(Clock::local())->format('Y-m-d');
        $du = self::date($request->query('du'), $aujourdhui);
        $au = self::date($request->query('au'), $du);
        $filtres = ['site' => (int) $request->query('site', '0'), 'statut' => (string) $request->query('statut', ''), 'agent' => (int) $request->query('agent', '0')];
        $page = max(1, (int) $request->query('page', '1'));

        $resultat = $this->factory->rondes()->lister(
            $perimetre,
            (new DateTimeImmutable($du, Clock::local()))->setTimezone(Clock::utc()),
            (new DateTimeImmutable($au, Clock::local()))->modify('+1 day')->setTimezone(Clock::utc()),
            $filtres,
            $page,
        );

        return $this->vue('admin/rondes/index', [
            'rondes' => $resultat['lignes'],
            'total' => $resultat['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($resultat['total'] / RondeRepository::PAR_PAGE)),
            'filtres' => [...$filtres, 'du' => $du, 'au' => $au],
            'sites' => $this->factory->sites()->choix($perimetre),
        ]);
    }

    public function voir(Request $request): Response
    {
        $ronde = $this->factory->rondes()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        if (!$this->factory->autorisation()->perimetre($this->utilisateur($request), 'rondes.suivre')->couvreSite((int) $ronde['site_id'])) {
            throw HttpException::notFound();
        }

        return $this->vue('admin/rondes/fiche', [
            'ronde' => $ronde,
            'etat' => $ronde['points_prevus'] === null ? null : $this->factory->rondeService()->etat((int) $ronde['id']),
            'passages' => $this->factory->rondes()->passages((int) $ronde['id']),
            'anomalies' => $this->factory->anomalies()->lister(Perimetre::tout(), ['ronde' => (int) $ronde['id']]),
        ]);
    }

    /**
     * Rapport de rondes d'un site sur une période, au format PDF ou CSV.
     */
    public function rapport(Request $request): Response
    {
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'rapports_rondes.exporter');
        $sites = $this->factory->sites()->choix($perimetre);
        $siteId = (int) $request->query('site', '0');
        $format = (string) $request->query('format', '');

        if ($siteId === 0 || !in_array($format, ['pdf', 'csv'], true)) {
            return $this->vue('admin/rondes/rapport', [
                'sites' => $sites,
                'du' => Clock::now()->setTimezone(Clock::local())->modify('first day of last month')->format('Y-m-d'),
                'au' => Clock::now()->setTimezone(Clock::local())->modify('last day of last month')->format('Y-m-d'),
            ]);
        }

        if (!$perimetre->couvreSite($siteId)) {
            throw HttpException::notFound();
        }
        $site = $this->factory->sites()->trouver($siteId) ?? throw HttpException::notFound();
        $du = self::date($request->query('du'), Clock::now()->setTimezone(Clock::local())->format('Y-m-01'));
        $au = self::date($request->query('au'), Clock::now()->setTimezone(Clock::local())->format('Y-m-d'));
        $debut = (new DateTimeImmutable($du, Clock::local()))->setTimezone(Clock::utc());
        $fin = (new DateTimeImmutable($au, Clock::local()))->modify('+1 day')->setTimezone(Clock::utc());
        if ($fin <= $debut || $fin->getTimestamp() - $debut->getTimestamp() > 92 * 86400) {
            return $this->redirigerAvecErreur('/admin/rapports-rondes', 'La période doit être comprise entre 1 et 92 jours.');
        }

        $donnees = $this->factory->rapportRondes()->donnees($site, $debut, $fin, false, true);
        $this->factory->audit()->journaliser('rapport_rondes.exporter', 'site', $siteId, ['du' => $du, 'au' => $au, 'format' => $format]);
        $nom = sprintf('rondes-%s-%s-%s', $site['code'], $du, $au);

        return $format === 'csv'
            ? Csv::reponse($nom . '.csv', $this->factory->rapportRondes()->lignesCsv($donnees))
            : Response::download($this->factory->rapportRondes()->pdf($donnees), $nom . '.pdf', 'application/pdf');
    }
}
