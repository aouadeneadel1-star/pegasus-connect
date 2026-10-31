<?php

declare(strict_types=1);

namespace App\Controllers\Agent;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\Request;
use App\Core\Response;
use App\Services\Planning\PlanningService;
use DateTimeImmutable;

final class PlanningController extends Controller
{
    public function index(Request $request): Response
    {
        $agentId = (int) $this->utilisateur($request)->agentId;
        $aujourdhui = Clock::now()->setTimezone(Clock::local())->format('Y-m-d');
        $date = self::date($request->query('date'), $aujourdhui);
        $lundi = PlanningService::debutSemaine(new DateTimeImmutable($date . ' 12:00', Clock::local()));
        $dimanche = $lundi->modify('+7 days');

        $vacations = $this->factory->vacations()->pourAgent($agentId, $lundi, $dimanche);
        $jours = [];
        for ($i = 0; $i < 7; ++$i) {
            $jour = $lundi->setTimezone(Clock::local())->modify('+' . $i . ' days');
            $jours[$jour->format('Y-m-d')] = ['date' => $jour, 'vacations' => []];
        }
        foreach ($vacations as $vacation) {
            $cle = Clock::fromSql((string) $vacation['debut'])->setTimezone(Clock::local())->format('Y-m-d');
            if (isset($jours[$cle])) {
                $jours[$cle]['vacations'][] = $vacation;
            }
        }

        $total = array_sum(array_map(
            static fn (array $v): int => intdiv(Clock::fromSql((string) $v['fin'])->getTimestamp() - Clock::fromSql((string) $v['debut'])->getTimestamp(), 60) - (int) $v['pause_minutes'],
            $vacations,
        ));

        return $this->vue('agent/planning', [
            'jours' => $jours,
            'aujourdhui' => $aujourdhui,
            'precedente' => $lundi->setTimezone(Clock::local())->modify('-7 days')->format('Y-m-d'),
            'suivante' => $lundi->setTimezone(Clock::local())->modify('+7 days')->format('Y-m-d'),
            'total' => $total,
        ]);
    }
}
