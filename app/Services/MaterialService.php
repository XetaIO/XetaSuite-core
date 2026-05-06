<?php

declare(strict_types=1);

namespace XetaSuite\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use XetaSuite\Models\Cleaning;
use XetaSuite\Models\Incident;
use XetaSuite\Models\ItemMovement;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\Material;
use XetaSuite\Models\User;
use XetaSuite\Models\Zone;
use XetaSuite\Services\Concerns\HasSearchAndSort;

class MaterialService
{
    use HasSearchAndSort;

    private const SEARCH_COLUMNS = ['name', 'description'];

    private const ALLOWED_SORTS = ['name', 'created_at', 'last_cleaning_at'];

    /**
     * Get a paginated list of materials with optional search and sorting.
     *
     * @param  array{zone_id?: int, search?: string, sort_by?: string, sort_direction?: string, per_page?: int}  $filters
     */
    public function getPaginatedMaterials(array $filters = []): LengthAwarePaginator
    {
        return Material::query()
            ->with(['site', 'zone', 'creator'])
            ->forCurrentSite()
            ->when($filters['zone_id'] ?? null, fn (Builder $query, int $zoneId) => $query->where('zone_id', $zoneId))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->applySearchFilter($query, $search, self::SEARCH_COLUMNS))
            ->when(
                $filters['sort_by'] ?? null,
                fn (Builder $query, string $sortBy) => $this->applySortFilter($query, $sortBy, $filters['sort_direction'] ?? 'asc', self::ALLOWED_SORTS, 'name'),
                fn (Builder $query) => $query->orderBy('name')
            )
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get materials for a specific zone.
     */
    public function getMaterialsForZone(Zone $zone): Collection
    {
        return $zone->materials()
            ->with(['creator'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get available zones for a material (zones that allow materials on current site).
     */
    public function getAvailableZones(): Collection
    {
        return Zone::query()
            ->forCurrentSite()
            ->where('allow_material', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get available recipients for cleaning alerts (users with access to current site).
     */
    public function getAvailableRecipients(): Collection
    {
        $currentSiteId = session('current_site_id');

        return User::query()
            ->whereHas('sites', fn (Builder $query) => $query->where('site_id', $currentSiteId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);
    }

    /**
     * Get monthly statistics for a material over the last 12 months.
     *
     * @return array{months: array<string>, incidents: array<int>, maintenances: array<int>, cleanings: array<int>, item_movements: array<int>}
     */
    public function getMonthlyStats(Material $material): array
    {
        $startDate = Carbon::now()->subMonths(11)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        // Generate months labels
        $months = [];
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $months[] = $currentDate->format('Y-m');
            $currentDate->addMonth();
        }

        // Query incidents by month
        $incidentsData = Incident::query()
            ->where('material_id', $material->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw("to_char(created_at, 'YYYY-MM') as month"),
                DB::raw('count(*) as count')
            )
            ->groupBy('month')
            ->pluck('count', 'month');

        // Query maintenances by month
        $maintenancesData = Maintenance::query()
            ->where('material_id', $material->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw("to_char(created_at, 'YYYY-MM') as month"),
                DB::raw('count(*) as count')
            )
            ->groupBy('month')
            ->pluck('count', 'month');

        // Query cleanings by month
        $cleaningsData = Cleaning::query()
            ->where('material_id', $material->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw("to_char(created_at, 'YYYY-MM') as month"),
                DB::raw('count(*) as count')
            )
            ->groupBy('month')
            ->pluck('count', 'month');

        // Query item movements (exits) via maintenances for this material
        $itemMovementsData = ItemMovement::query()
            ->where('type', 'exit')
            ->where('movable_type', Maintenance::class)
            ->whereIn('movable_id', Maintenance::where('material_id', $material->id)->pluck('id'))
            ->whereBetween('movement_date', [$startDate, $endDate])
            ->select(
                DB::raw("to_char(movement_date, 'YYYY-MM') as month"),
                DB::raw('sum(quantity) as count')
            )
            ->groupBy('month')
            ->pluck('count', 'month');

        // Build response arrays with 0 for months without data
        $incidents = [];
        $maintenances = [];
        $cleanings = [];
        $itemMovements = [];

        foreach ($months as $month) {
            $incidents[] = (int) ($incidentsData[$month] ?? 0);
            $maintenances[] = (int) ($maintenancesData[$month] ?? 0);
            $cleanings[] = (int) ($cleaningsData[$month] ?? 0);
            $itemMovements[] = (int) ($itemMovementsData[$month] ?? 0);
        }

        return [
            'months' => $months,
            'incidents' => $incidents,
            'maintenances' => $maintenances,
            'cleanings' => $cleanings,
            'item_movements' => $itemMovements,
        ];
    }

    /**
     * Get material incidents with pagination and search.
     *
     * @param  array{page?: int, per_page?: int, search?: string}  $filters
     */
    public function getMaterialIncidents(Material $material, array $filters = []): LengthAwarePaginator
    {
        return $material->incidents()
            ->with(['reporter', 'maintenance'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('description', 'ILIKE', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Get material maintenances with pagination and search.
     *
     * @param  array{page?: int, per_page?: int, search?: string}  $filters
     */
    public function getMaterialMaintenances(Material $material, array $filters = []): LengthAwarePaginator
    {
        return $material->maintenances()
            ->with(['creator', 'operators', 'companies', 'material'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('description', 'ILIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Get material cleanings with pagination and search.
     *
     * @param  array{page?: int, per_page?: int, search?: string}  $filters
     */
    public function getMaterialCleanings(Material $material, array $filters = []): LengthAwarePaginator
    {
        return $material->cleanings()
            ->with(['site', 'creator', 'material'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('description', 'ILIKE', "%{$search}%")
                    ->orWhere('id', 'ILIKE', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Get material items with pagination and search.
     *
     * @param  array{page?: int, per_page?: int, search?: string}  $filters
     */
    public function getMaterialItems(Material $material, array $filters = []): LengthAwarePaginator
    {
        return $material->items()
            ->with(['company'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('items.name', 'ILIKE', "%{$search}%")
                        ->orWhere('items.reference', 'ILIKE', "%{$search}%");
                });
            })
            ->orderBy('items.name')
            ->paginate($filters['per_page'] ?? 10);
    }
}
