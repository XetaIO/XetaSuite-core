<?php

declare(strict_types=1);

namespace XetaSuite\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use XetaSuite\Enums\Incidents\IncidentSeverity;
use XetaSuite\Enums\Incidents\IncidentStatus;
use XetaSuite\Enums\Maintenances\MaintenanceStatus;
use XetaSuite\Models\Cleaning;
use XetaSuite\Models\Incident;
use XetaSuite\Models\Item;
use XetaSuite\Models\ItemMovement;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\User;

/**
 * Aggregates dashboard data (stats + charts) for the authenticated user.
 *
 * Honors the multi-tenant boundary: on HQ all data is aggregated, on a regular
 * site the result is scoped to `$user->current_site_id`.
 */
class DashboardService
{
    private const LOW_STOCK_FALLBACK_LIMIT = 10;

    private const LOW_STOCK_RESULTS_LIMIT = 10;

    private const UPCOMING_LIMIT = 5;

    private const RECENT_PER_SOURCE = 5;

    private const RECENT_TOTAL = 10;

    private const CHART_MONTHS = 12;

    /**
     * Build the full dashboard payload exposed by `stats` endpoint.
     *
     * @return array<string, mixed>
     */
    public function stats(User $user): array
    {
        $isHq = isOnHeadquarters();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $maintenanceStats = $this->getMaintenanceStats($user, $isHq, $startOfMonth, $endOfMonth, $lastMonthStart, $lastMonthEnd);
        $incidentStats = $this->getIncidentStats($user, $isHq);
        $cleaningStats = $this->getCleaningStats($user, $isHq, $startOfMonth, $endOfMonth, $lastMonthStart, $lastMonthEnd);
        $itemsInStock = $this->getItemsInStock($user, $isHq);
        $incidentsSummary = $this->getIncidentsSummary($user, $isHq);

        return [
            'stats' => [
                'maintenances_this_month' => $maintenanceStats['count'],
                'maintenances_trend' => $maintenanceStats['trend'],
                'open_incidents' => $incidentStats['open'],
                'incidents_trend' => $incidentStats['trend'],
                'items_in_stock' => $itemsInStock,
                'cleanings_this_month' => $cleaningStats['count'],
                'cleanings_trend' => $cleaningStats['trend'],
            ],
            'incidents_summary' => $incidentsSummary,
            'low_stock_items' => $this->getLowStockItems($user, $isHq),
            'upcoming_maintenances' => $this->getUpcomingMaintenances($user, $isHq),
            'recent_activities' => $this->getRecentActivities($user, $isHq),
            'is_headquarters' => $isHq,
        ];
    }

    /**
     * Build chart payload (12-month maintenances/incidents evolution).
     *
     * @return array<string, mixed>
     */
    public function chartsData(User $user): array
    {
        $isHq = isOnHeadquarters();
        $months = $this->buildMonthsRange();
        $monthLabels = $months->map(fn (string $month) => Carbon::parse($month.'-01')->format('M'))->values()->toArray();

        $maintenancesByTypeAndMonth = $this->groupedCountsByMonth(
            'maintenances',
            'type',
            $user,
            $isHq,
        );

        $incidentsBySeverityAndMonth = $this->groupedCountsByMonth(
            'incidents',
            'severity',
            $user,
            $isHq,
        );

        $maintenancesEvolution = ['months' => $monthLabels];
        foreach (['corrective', 'preventive', 'inspection', 'improvement'] as $type) {
            $maintenancesEvolution[$type] = $months
                ->map(fn (string $month) => $maintenancesByTypeAndMonth[$type][$month] ?? 0)
                ->values()->toArray();
        }

        $incidentsEvolution = ['months' => $monthLabels];
        foreach (['low', 'medium', 'high', 'critical'] as $severity) {
            $incidentsEvolution[$severity] = $months
                ->map(fn (string $month) => $incidentsBySeverityAndMonth[$severity][$month] ?? 0)
                ->values()->toArray();
        }

        return [
            'maintenances_evolution' => $maintenancesEvolution,
            'incidents_evolution' => $incidentsEvolution,
        ];
    }

    /**
     * Apply the multi-tenant filter on a query when the user is not on HQ.
     */
    private function applySiteScope(Builder $query, User $user, bool $isHq): Builder
    {
        return $isHq ? $query : $query->where('site_id', $user->current_site_id);
    }

    /**
     * Compute month-over-month percent variation.
     */
    private function trend(int $current, int $previous): int
    {
        if ($previous > 0) {
            return (int) round((($current - $previous) / $previous) * 100);
        }

        return $current > 0 ? 100 : 0;
    }

    /**
     * @return array{count: int, trend: int}
     */
    private function getMaintenanceStats(User $user, bool $isHq, Carbon $startOfMonth, Carbon $endOfMonth, Carbon $lastMonthStart, Carbon $lastMonthEnd): array
    {
        $thisMonth = $this->applySiteScope(Maintenance::query(), $user, $isHq)
            ->whereBetween('started_at', [$startOfMonth, $endOfMonth])
            ->count();

        $lastMonth = $this->applySiteScope(Maintenance::query(), $user, $isHq)
            ->whereBetween('started_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        return ['count' => $thisMonth, 'trend' => $this->trend($thisMonth, $lastMonth)];
    }

    /**
     * @return array{open: int, trend: int}
     */
    private function getIncidentStats(User $user, bool $isHq): array
    {
        $openStatuses = [IncidentStatus::OPEN->value, IncidentStatus::IN_PROGRESS->value];

        $open = $this->applySiteScope(Incident::query(), $user, $isHq)
            ->whereIn('status', $openStatuses)
            ->count();

        $openLastWeek = $this->applySiteScope(Incident::query(), $user, $isHq)
            ->whereIn('status', $openStatuses)
            ->where('created_at', '<=', Carbon::now()->subWeek())
            ->count();

        return ['open' => $open, 'trend' => $this->trend($open, $openLastWeek)];
    }

    /**
     * @return array{count: int, trend: int}
     */
    private function getCleaningStats(User $user, bool $isHq, Carbon $startOfMonth, Carbon $endOfMonth, Carbon $lastMonthStart, Carbon $lastMonthEnd): array
    {
        $thisMonth = $this->applySiteScope(Cleaning::query(), $user, $isHq)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        $lastMonth = $this->applySiteScope(Cleaning::query(), $user, $isHq)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        return ['count' => $thisMonth, 'trend' => $this->trend($thisMonth, $lastMonth)];
    }

    private function getItemsInStock(User $user, bool $isHq): int
    {
        return (int) ($this->applySiteScope(Item::query(), $user, $isHq)
            ->selectRaw('SUM('.Item::stockExpression().') as total')
            ->value('total') ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function getIncidentsSummary(User $user, bool $isHq): array
    {
        $byStatus = $this->applySiteScope(Incident::query(), $user, $isHq)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $bySeverity = $this->applySiteScope(Incident::query(), $user, $isHq)
            ->select('severity', DB::raw('count(*) as count'))
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        return [
            'total' => array_sum($byStatus),
            'open' => $byStatus[IncidentStatus::OPEN->value] ?? 0,
            'in_progress' => $byStatus[IncidentStatus::IN_PROGRESS->value] ?? 0,
            'resolved' => ($byStatus[IncidentStatus::RESOLVED->value] ?? 0)
                + ($byStatus[IncidentStatus::CLOSED->value] ?? 0),
            'by_severity' => [
                'critical' => $bySeverity[IncidentSeverity::CRITICAL->value] ?? 0,
                'high' => $bySeverity[IncidentSeverity::HIGH->value] ?? 0,
                'medium' => $bySeverity[IncidentSeverity::MEDIUM->value] ?? 0,
                'low' => $bySeverity[IncidentSeverity::LOW->value] ?? 0,
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function getLowStockItems(User $user, bool $isHq): Collection
    {
        $expression = Item::stockExpression();

        return $this->applySiteScope(Item::query(), $user, $isHq)
            ->withCalculatedStock()
            ->where(function (Builder $query) use ($expression): void {
                $query->where(function (Builder $q): void {
                    $q->whereStockBelowCritical();
                })->orWhere(function (Builder $q): void {
                    $q->whereStockBelowWarning();
                })->orWhereRaw($expression.' < '.self::LOW_STOCK_FALLBACK_LIMIT.' AND '.$expression.' >= 0');
            })
            ->orderByRaw(sprintf(
                'CASE
                    WHEN number_critical_enabled = true AND %1$s < number_critical_minimum THEN 1
                    WHEN number_warning_enabled = true AND %1$s < number_warning_minimum THEN 2
                    ELSE 3
                END ASC, %1$s ASC',
                $expression
            ))
            ->limit(self::LOW_STOCK_RESULTS_LIMIT)
            ->get(['id', 'name', 'reference', 'item_entry_total', 'item_exit_total', 'number_warning_minimum', 'number_warning_enabled', 'number_critical_minimum', 'number_critical_enabled'])
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'reference' => $item->reference,
                'current_stock' => $item->current_stock,
                'min_stock' => $item->number_warning_enabled ? $item->number_warning_minimum : self::LOW_STOCK_FALLBACK_LIMIT,
                'stock_status' => $item->stock_status,
                'stock_status_color' => $item->stock_status_color,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function getUpcomingMaintenances(User $user, bool $isHq): Collection
    {
        $base = fn () => $this->applySiteScope(Maintenance::query(), $user, $isHq)
            ->with('material:id,name')
            ->where('status', MaintenanceStatus::PLANNED->value);

        $upcoming = $base()
            ->where('started_at', '>', Carbon::now())
            ->orderBy('started_at')
            ->limit(self::UPCOMING_LIMIT)
            ->get(['id', 'material_id', 'material_name', 'description', 'type', 'started_at']);

        if ($upcoming->isEmpty()) {
            $upcoming = $base()
                ->orderBy('started_at', 'desc')
                ->limit(self::UPCOMING_LIMIT)
                ->get(['id', 'material_id', 'material_name', 'description', 'type', 'started_at']);
        }

        return $upcoming->map(fn (Maintenance $m) => [
            'id' => $m->id,
            'title' => $m->description,
            'location' => $m->material?->name ?? $m->material_name ?? '-',
            'date' => $m->started_at?->format('d M Y - H:i'),
            'priority' => 'medium',
            'type' => $m->type?->value ?? 'preventive',
        ])->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function getRecentActivities(User $user, bool $isHq): Collection
    {
        $maintenances = $this->applySiteScope(Maintenance::query(), $user, $isHq)
            ->with('material:id,name')
            ->latest('updated_at')
            ->limit(self::RECENT_PER_SOURCE)
            ->get(['id', 'description', 'material_id', 'status', 'updated_at'])
            ->map(fn (Maintenance $m) => [
                'id' => 'm_'.$m->id,
                'type' => 'maintenance',
                'title' => $m->description,
                'description' => $m->material?->name ?? '',
                'time' => $m->updated_at,
                'status' => $m->status?->value === 'completed' ? 'completed' : 'in_progress',
            ]);

        $incidents = $this->applySiteScope(Incident::query(), $user, $isHq)
            ->with('material:id,name')
            ->latest('updated_at')
            ->limit(self::RECENT_PER_SOURCE)
            ->get(['id', 'description', 'material_id', 'status', 'updated_at'])
            ->map(fn (Incident $i) => [
                'id' => 'i_'.$i->id,
                'type' => 'incident',
                'title' => $i->description,
                'description' => $i->material?->name ?? '',
                'time' => $i->updated_at,
                'status' => match ($i->status?->value) {
                    'resolved', 'closed' => 'completed',
                    'in_progress' => 'in_progress',
                    default => 'pending',
                },
            ]);

        $cleanings = $this->applySiteScope(Cleaning::query(), $user, $isHq)
            ->with('material:id,name')
            ->latest('updated_at')
            ->limit(self::RECENT_PER_SOURCE)
            ->get(['id', 'description', 'material_id', 'type', 'updated_at'])
            ->map(fn (Cleaning $c) => [
                'id' => 'c_'.$c->id,
                'type' => 'cleaning',
                'title' => $c->description ?? __('Nettoyage'),
                'description' => $c->material?->name ?? '',
                'time' => $c->updated_at,
                'status' => 'completed',
            ]);

        $movements = ItemMovement::query()
            ->with('item:id,name,site_id')
            ->whereHas('item', fn (Builder $q) => $this->applySiteScope($q, $user, $isHq))
            ->latest('movement_date')
            ->limit(self::RECENT_PER_SOURCE)
            ->get(['id', 'item_id', 'type', 'quantity', 'movement_date', 'updated_at'])
            ->map(fn (ItemMovement $im) => [
                'id' => 'im_'.$im->item?->id,
                'type' => 'item_movement',
                'title' => $im->type === 'entry'
                    ? __(':qty entrée(s)', ['qty' => $im->quantity])
                    : __(':qty sortie(s)', ['qty' => $im->quantity]),
                'description' => $im->item?->name ?? '',
                'time' => $im->movement_date ?? $im->updated_at,
                'status' => $im->type,
            ]);

        return $maintenances
            ->concat($incidents)
            ->concat($cleanings)
            ->concat($movements)
            ->sortByDesc('time')
            ->take(self::RECENT_TOTAL)
            ->map(fn (array $activity) => [
                ...$activity,
                'time' => Carbon::parse($activity['time'])->diffForHumans(),
            ])
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function buildMonthsRange(): Collection
    {
        $months = collect();
        for ($i = self::CHART_MONTHS - 1; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i)->format('Y-m'));
        }

        return $months;
    }

    /**
     * Aggregate row counts by `(YYYY-MM, $groupColumn)` for the last 12 months.
     *
     * Uses the query builder (not Eloquent) to avoid enum casting interfering
     * with `groupBy`.
     *
     * @return array<string, array<string, int>>
     */
    private function groupedCountsByMonth(string $table, string $groupColumn, User $user, bool $isHq): array
    {
        $query = DB::table($table)
            ->where('started_at', '>=', Carbon::now()->subMonths(self::CHART_MONTHS)->startOfMonth())
            ->select(
                DB::raw("TO_CHAR(started_at, 'YYYY-MM') as month"),
                $groupColumn,
                DB::raw('count(*) as count')
            )
            ->groupBy('month', $groupColumn);

        if (! $isHq) {
            $query->where('site_id', $user->current_site_id);
        }

        return $query->get()
            ->groupBy($groupColumn)
            ->map(fn ($items) => $items->pluck('count', 'month')->toArray())
            ->toArray();
    }
}
