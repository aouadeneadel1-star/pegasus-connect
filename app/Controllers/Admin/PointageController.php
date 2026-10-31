<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\Csv;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PointageRepository;
use App\Services\Pointage\CorrectionService;
use App\Services\Pointage\PointageService;
use App\Services\ValidationException;
use DateTimeImmutable;

/**
 * Suivi des pointages (cahier des charges, § 3.11) : journée, file « à vérifier »,
 * services ouverts, corrections proposées.
 */
final class PointageController extends Controller
{
    private const VUES = ['jour', 'a_verifier', 'services', 'corrections'];

    public function index(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $perimetre = $this->factory->autorisation()->perimetre($utilisateur, 'pointages.suivre');
        $vue = in_array($request->query('vue'), self::VUES, true) ? (string) $request->query('vue') : ($request->query('statut') === 'a_verifier' ? 'a_verifier' : 'jour');
        $jour = self::date($request->query('date'), Clock::now()->setTimezone(Clock::local())->format('Y-m-d'));
        $filtres = [
            'site' => (int) $request->query('site', '0'),
            'statut' => (string) $request->query('statut', ''),
            'agent' => (int) $request->query('agent', '0'),
        ];
        $page = max(1, (int) $request->query('page', '1'));

        $donnees = [
            'vue' => $vue,
            'jour' => $jour,
            'filtres' => $filtres,
            'sites' => $this->factory->sites()->choix($perimetre),
            'nbAVerifier' => $this->factory->pointages()->nombreAVerifier($perimetre),
            'peutCorriger' => $utilisateur->peut('pointages.corriger.proposer') || $utilisateur->peut('pointages.corriger.valider'),
            'peutValider' => $utilisateur->peut('pointages.corriger.valider'),
            'appliqueDirectement' => $this->factory->correctionService()->peutAppliquer($utilisateur),
            'motifs' => CorrectionService::MOTIFS,
        ];

        if ($vue === 'jour') {
            $du = (new DateTimeImmutable($jour, Clock::local()))->setTimezone(Clock::utc());
            $resultat = $this->factory->pointages()->lister($perimetre, $du, $du->modify('+1 day'), $filtres, $page);
            $donnees['pointages'] = $resultat['lignes'];
            $donnees['total'] = $resultat['total'];
            $donnees['page'] = $page;
            $donnees['pages'] = max(1, (int) ceil($resultat['total'] / PointageRepository::PAR_PAGE));
        } elseif ($vue === 'a_verifier') {
            $donnees['pointages'] = $this->factory->pointages()->aVerifier($perimetre);
        } elseif ($vue === 'services') {
            $donnees['services'] = $this->factory->servicesTravail()->enCours($perimetre);
            $donnees['agentsPerimetre'] = $this->factory->agents()->choix($this->factory->autorisation()->perimetre($utilisateur, 'agents.voir'));
        } else {
            $donnees['corrections'] = array_map(static function (array $c): array {
                $c['apres'] = json_decode((string) $c['apres'], true) ?: [];
                $c['avant'] = json_decode((string) ($c['avant'] ?? 'null'), true);

                return $c;
            }, $utilisateur->peut('pointages.corriger.valider')
                ? $this->factory->corrections()->enAttente($this->factory->autorisation()->perimetre($utilisateur, 'pointages.corriger.valider'))
                : []);
        }

        return $this->vue('admin/pointages', $donnees);
    }

    public function exporter(Request $request): Response
    {
        $perimetre = $this->factory->autorisation()->perimetre($this->utilisateur($request), 'pointages.suivre');
        $du = self::date($request->query('du'), Clock::now()->setTimezone(Clock::local())->modify('first day of this month')->format('Y-m-d'));
        $au = self::date($request->query('au'), Clock::now()->setTimezone(Clock::local())->format('Y-m-d'));
        $siteId = (int) $request->query('site', '0');

        $lignes = (function () use ($perimetre, $du, $au, $siteId): iterable {
            yield ['Date', 'Heure', 'Matricule', 'Nom', 'Prénom', 'Site', 'Type', 'Mode', 'Statut', 'Indicateurs', 'Hors ligne', 'Distance au site (m)', 'Précision (m)', 'Géolocalisation'];
            foreach ($this->factory->pointages()->exporter(
                $perimetre,
                (new DateTimeImmutable($du, Clock::local()))->setTimezone(Clock::utc()),
                (new DateTimeImmutable($au, Clock::local()))->modify('+1 day')->setTimezone(Clock::utc()),
                $siteId > 0 ? $siteId : null,
            ) as $ligne) {
                $instant = Clock::fromSql((string) $ligne['horodatage'])->setTimezone(Clock::local());
                yield [
                    $instant->format('d/m/Y'), $instant->format('H:i:s'), $ligne['matricule'], $ligne['nom'], $ligne['prenom'], $ligne['site'],
                    $ligne['type'], $ligne['mode'], $ligne['statut'],
                    implode(', ', array_map(PointageService::libelleIndicateur(...), PointageService::indicateurs($ligne['indicateurs']))),
                    $ligne['hors_ligne'] ? 'oui' : 'non', $ligne['distance_site_m'], $ligne['precision_m'], $ligne['geoloc_statut'],
                ];
            }
        })();

        $this->factory->audit()->journaliser('pointages.exporter', null, null, ['du' => $du, 'au' => $au, 'site' => $siteId]);

        return Csv::reponse('pointages-' . $du . '-' . $au . '.csv', $lignes);
    }

    public function statuer(Request $request): Response
    {
        $pointage = $this->pointageAccessible($request);

        return $this->corriger($request, fn (): string => $this->factory->correctionService()->statuer(
            $this->utilisateur($request),
            (int) $pointage['id'],
            $request->input('decision') === 'valider',
            (string) $request->input('motif_code', 'verification'),
            (string) $request->input('motif_texte', ''),
        ));
    }

    public function modifierHeure(Request $request): Response
    {
        $pointage = $this->pointageAccessible($request);
        $instant = self::instant((string) $request->input('horodatage', ''));
        if ($instant === null) {
            return $this->redirigerAvecErreur($this->retour($request), 'Heure invalide.');
        }

        return $this->corriger($request, fn (): string => $this->factory->correctionService()->modifierHeure(
            $this->utilisateur($request),
            (int) $pointage['id'],
            $instant,
            (string) $request->input('motif_code', ''),
            (string) $request->input('motif_texte', ''),
        ));
    }

    public function ajouterDepart(Request $request): Response
    {
        $service = $this->factory->servicesTravail()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        $this->siteAccessible($request, (int) $service['site_id']);
        $instant = self::instant((string) $request->input('horodatage', ''));
        if ($instant === null) {
            return $this->redirigerAvecErreur($this->retour($request), 'Heure invalide.');
        }

        return $this->corriger($request, fn (): string => $this->factory->correctionService()->ajouterDepart(
            $this->utilisateur($request),
            (int) $service['id'],
            $instant,
            (string) $request->input('motif_code', ''),
            (string) $request->input('motif_texte', ''),
        ));
    }

    public function ajouterService(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $siteId = (int) $request->input('site_id', '0');
        $agentId = (int) $request->input('agent_id', '0');
        $this->siteAccessible($request, $siteId);
        if (!$this->factory->autorisation()->perimetre($utilisateur, 'agents.voir')->couvreAgent($agentId) || $this->factory->agents()->trouver($agentId) === null) {
            throw HttpException::notFound();
        }
        $debut = self::instant((string) $request->input('debut', ''));
        $fin = self::instant((string) $request->input('fin', ''));
        if ($debut === null || $fin === null) {
            return $this->redirigerAvecErreur($this->retour($request), 'Dates invalides.');
        }

        return $this->corriger($request, fn (): string => $this->factory->correctionService()->ajouterService(
            $utilisateur, $agentId, $siteId, $debut, $fin,
            (string) $request->input('motif_code', ''),
            (string) $request->input('motif_texte', ''),
        ));
    }

    public function validerCorrection(Request $request): Response
    {
        try {
            $this->factory->correctionService()->valider($this->utilisateur($request), $request->intParam('id'));
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur('/admin/pointages?vue=corrections', $exception->getMessage());
        }

        return $this->rediriger('/admin/pointages?vue=corrections', 'Correction appliquée.');
    }

    public function refuserCorrection(Request $request): Response
    {
        try {
            $this->factory->correctionService()->refuser($this->utilisateur($request), $request->intParam('id'));
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur('/admin/pointages?vue=corrections', $exception->getMessage());
        }

        return $this->rediriger('/admin/pointages?vue=corrections', 'Correction refusée.');
    }

    /**
     * @param callable(): string $action
     */
    private function corriger(Request $request, callable $action): Response
    {
        try {
            $resultat = $action();
        } catch (ValidationException $exception) {
            return $this->redirigerAvecErreur($this->retour($request), $exception->getMessage());
        }

        return $this->rediriger($this->retour($request), $resultat === 'appliquee'
            ? 'Correction appliquée.'
            : 'Correction proposée : elle sera appliquée après validation par le service RH.');
    }

    private function retour(Request $request): string
    {
        $retour = (string) $request->input('retour', '/admin/pointages');

        return str_starts_with($retour, '/admin/pointages') ? $retour : '/admin/pointages';
    }

    /**
     * @return array<string, mixed>
     */
    private function pointageAccessible(Request $request): array
    {
        $pointage = $this->factory->pointages()->trouver($request->intParam('id')) ?? throw HttpException::notFound();
        $this->siteAccessible($request, (int) $pointage['site_id']);

        return $pointage;
    }

    private function siteAccessible(Request $request, int $siteId): void
    {
        if (!$this->factory->autorisation()->perimetre($this->utilisateur($request), 'pointages.suivre')->couvreSite($siteId)) {
            throw HttpException::notFound();
        }
    }

    private static function instant(string $valeur): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $valeur) !== 1) {
            return null;
        }
        $instant = Clock::fromLocal(str_replace('T', ' ', $valeur));

        return $instant > Clock::now()->modify('+5 minutes') ? null : $instant;
    }
}
