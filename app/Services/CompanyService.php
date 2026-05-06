<?php

declare(strict_types=1);

namespace XetaSuite\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use XetaSuite\Enums\Companies\CompanyType;
use XetaSuite\Models\Company;
use XetaSuite\Models\Maintenance;
use XetaSuite\Services\Concerns\HasSearchAndSort;

class CompanyService
{
    use HasSearchAndSort;

    private const SEARCH_COLUMNS = ['name', 'description', 'email', 'phone'];

    private const ALLOWED_SORTS = ['name', 'maintenances_count', 'items_count', 'created_at'];

    private const MAINTENANCE_SEARCH_COLUMNS = ['description'];

    private const MAINTENANCE_ALLOWED_SORTS = ['type', 'status', 'started_at', 'resolved_at', 'created_at'];

    private const MAINTENANCE_SEARCH_RELATIONS = [
        'material' => 'name',
        'site' => 'name',
    ];

    private const ITEM_SEARCH_COLUMNS = ['name', 'reference', 'description'];

    private const ITEM_ALLOWED_SORTS = ['name', 'reference', 'current_price', 'created_at'];

    private const ITEM_SEARCH_RELATIONS = [
        'site' => 'name',
    ];

    /**
     * Get a paginated list of companies with optional search and sorting.
     *
     * @param  array{search?: string, sort_by?: string, sort_direction?: string, type?: string, per_page?: int}  $filters
     */
    public function getPaginatedCompanies(array $filters = []): LengthAwarePaginator
    {
        return Company::query()
            ->with('creator')
            ->withCount(['maintenances', 'items'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->applySearchFilter($query, $search, self::SEARCH_COLUMNS))
            ->when($filters['type'] ?? null, function (Builder $query, string $type) {
                if ($type === CompanyType::ITEM_PROVIDER->value) {
                    return $query->itemProviders();
                }
                if ($type === CompanyType::MAINTENANCE_PROVIDER->value) {
                    return $query->maintenanceProviders();
                }

                return $query;
            })
            ->when(
                $filters['sort_by'] ?? null,
                fn (Builder $query, string $sortBy) => $this->applySortFilter($query, $sortBy, $filters['sort_direction'] ?? 'asc', self::ALLOWED_SORTS),
                fn (Builder $query) => $query->orderBy('name')
            )
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get paginated items for a company with optional search and sorting.
     *
     * @param  array{search?: string, sort_by?: string, sort_direction?: string, per_page?: int}  $filters
     */
    public function getPaginatedItems(Company $company, array $filters = []): LengthAwarePaginator
    {
        return $company->items()
            ->with(['site', 'creator'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->applySearchFilter($query, $search, self::ITEM_SEARCH_COLUMNS, self::ITEM_SEARCH_RELATIONS))
            ->when(
                $filters['sort_by'] ?? null,
                fn (Builder $query, string $sortBy) => $this->applySortFilter($query, $sortBy, $filters['sort_direction'] ?? 'asc', self::ITEM_ALLOWED_SORTS),
                fn (Builder $query) => $query->orderByDesc('created_at')
            )
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get paginated maintenances for a company with optional search and sorting.
     *
     * @param  array{search?: string, sort_by?: string, sort_direction?: string, per_page?: int}  $filters
     */
    public function getPaginatedMaintenances(Company $company, array $filters = []): LengthAwarePaginator
    {
        return $company->maintenances()
            ->with(['material', 'site', 'creator'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->applySearchFilter($query, $search, self::MAINTENANCE_SEARCH_COLUMNS, self::MAINTENANCE_SEARCH_RELATIONS))
            ->when(
                $filters['sort_by'] ?? null,
                fn (Builder $query, string $sortBy) => $this->applySortFilter($query, $sortBy, $filters['sort_direction'] ?? 'asc', self::MAINTENANCE_ALLOWED_SORTS),
                fn (Builder $query) => $query->orderByDesc('created_at')
            )
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get statistics for a company.
     *
     * @return array<string, mixed>
     */
    public function getCompanyStats(Company $company): array
    {
        $maintenanceIds = $company->maintenances()->pluck('maintenances.id');

        // Maintenances by site
        $maintenancesBySite = Maintenance::query()
            ->whereIn('id', $maintenanceIds)
            ->select('site_id', DB::raw('count(*) as count'))
            ->with('site:id,name')
            ->groupBy('site_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'site_id' => $item->site_id,
                'site_name' => $item->site?->name ?? 'Unknown',
                'count' => $item->count,
            ]);

        // Maintenances by type
        $maintenancesByType = Maintenance::query()
            ->whereIn('id', $maintenanceIds)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($item) => [
                'type' => $item->type->value,
                'type_label' => $item->type->label(),
                'count' => $item->count,
            ]);

        // Maintenances by status
        $maintenancesByStatus = Maintenance::query()
            ->whereIn('id', $maintenanceIds)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($item) => [
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'count' => $item->count,
            ]);

        // Maintenances by realization
        $maintenancesByRealization = Maintenance::query()
            ->whereIn('id', $maintenanceIds)
            ->select('realization', DB::raw('count(*) as count'))
            ->groupBy('realization')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($item) => [
                'realization' => $item->realization->value,
                'realization_label' => $item->realization->label(),
                'count' => $item->count,
            ]);

        // Maintenances by month (last 12 months)
        $maintenancesByMonth = Maintenance::query()
            ->whereIn('id', $maintenanceIds)
            ->where('started_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw("to_char(started_at, 'YYYY-MM') as month"),
                DB::raw('count(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($item) => [
                'month' => $item->month,
                'count' => $item->count,
            ]);

        return [
            'total_maintenances' => $maintenanceIds->count(),
            'maintenances_by_site' => $maintenancesBySite,
            'maintenances_by_type' => $maintenancesByType,
            'maintenances_by_status' => $maintenancesByStatus,
            'maintenances_by_realization' => $maintenancesByRealization,
            'maintenances_by_month' => $maintenancesByMonth,
        ];
    }
}
