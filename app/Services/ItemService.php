<?php

declare(strict_types=1);

namespace XetaSuite\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use XetaSuite\Models\Company;
use XetaSuite\Models\Item;
use XetaSuite\Models\ItemMovement;
use XetaSuite\Models\ItemPrice;
use XetaSuite\Models\Material;
use XetaSuite\Models\User;
use XetaSuite\Services\Concerns\HasSearchAndSort;

class ItemService
{
    use HasSearchAndSort;

    private const SEARCH_COLUMNS = ['name', 'reference', 'description', 'company_reference'];

    private const ALLOWED_SORTS = ['name', 'reference', 'current_price', 'created_at'];

    /**
     * Get a paginated list of items with optional search, filters and sorting.
     *
     * @param  array{search?: string, company_id?: int, stock_status?: string, sort_by?: string, sort_direction?: string, per_page?: int}  $filters
     */
    public function getPaginatedItems(array $filters = []): LengthAwarePaginator
    {
        $query = Item::query()
            ->with(['site', 'company', 'creator'])
            ->forCurrentSite();

        return $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->applySearchFilter($query, $search, self::SEARCH_COLUMNS))
            ->when($filters['company_id'] ?? null, fn (Builder $query, int $companyId) => $query->where('company_id', $companyId))
            ->when($filters['stock_status'] ?? null, fn (Builder $query, string $status) => $this->applyStockStatusFilter($query, $status))
            ->when(
                $filters['sort_by'] ?? null,
                fn (Builder $query, string $sortBy) => $this->applySortFilter($query, $sortBy, $filters['sort_direction'] ?? 'asc', self::ALLOWED_SORTS),
                fn (Builder $query) => $query->orderBy('name')
            )
            ->paginate($filters['per_page'] ?? 20);
    }


    /**
     * Get available companies for item creation (companies with item_provider type).
     *
     * @param  string|null  $search  Search term to filter companies by name
     * @param  int|null  $includeId  Always include this company ID (even if not in search results)
     */
    public function getAvailableCompanies(?string $search = null, ?int $includeId = null): EloquentCollection
    {
        $companies = Company::query()
            ->itemProviders()
            ->when($search, fn (Builder $query) => $query->where('name', 'ILIKE', "%{$search}%"))
            ->when($includeId, fn (Builder $query) => $query->where('id', '!=', $includeId))
            ->orderBy('name')
            ->limit($includeId ? 19 : 20)
            ->get(['id', 'name']);

        // If includeId is provided, fetch that company and prepend it to the list
        if ($includeId) {
            $includedCompany = Company::query()
                ->where('id', $includeId)
                ->first(['id', 'name']);

            if ($includedCompany) {
                $companies->prepend($includedCompany);
            }
        }

        return $companies;
    }

    /**
     * Get available materials for item assignment (materials on current site).
     * Limited to 20 results - use search parameter for more specific filtering.
     */
    public function getAvailableMaterials(?string $search = null): EloquentCollection
    {
        $currentSiteId = session('current_site_id');

        return Material::query()
            ->where('site_id', $currentSiteId)
            ->when($search, fn (Builder $query) => $query->where('name', 'ILIKE', "%{$search}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);
    }

    /**
     * Get available recipients for critical alerts (users with access to current site).
     * Limited to 20 results - use search parameter for more specific filtering.
     */
    public function getAvailableRecipients(?string $search = null): Collection
    {
        $currentSiteId = session('current_site_id');

        return User::query()
            ->whereHas('sites', fn (Builder $query) => $query->where('sites.id', $currentSiteId))
            ->when($search, fn (Builder $query) => $query->where(function (Builder $q) use ($search): void {
                $q->where('first_name', 'ILIKE', "%{$search}%")
                    ->orWhere('last_name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            }))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
            ]);
    }

    /**
     * Check if a reference is unique for the site.
     */
    public function isReferenceUnique(string $reference, int $siteId, ?int $excludeItemId = null): bool
    {
        $query = Item::query()
            ->where('reference', $reference)
            ->where('site_id', $siteId);

        if ($excludeItemId) {
            $query->where('id', '!=', $excludeItemId);
        }

        return ! $query->exists();
    }

    /**
     * Get monthly statistics for an item over the last 12 months.
     *
     * @return array{months: array<string>, entries: array<int>, exits: array<int>, prices: array<float>}
     */
    public function getMonthlyStats(Item $item): array
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

        // Query entries by month
        $entriesData = ItemMovement::query()
            ->where('item_id', $item->id)
            ->where('type', 'entry')
            ->whereBetween('movement_date', [$startDate, $endDate])
            ->select(
                DB::raw("to_char(movement_date, 'YYYY-MM') as month"),
                DB::raw('sum(quantity) as count')
            )
            ->groupBy('month')
            ->pluck('count', 'month');

        // Query exits by month
        $exitsData = ItemMovement::query()
            ->where('item_id', $item->id)
            ->where('type', 'exit')
            ->whereBetween('movement_date', [$startDate, $endDate])
            ->select(
                DB::raw("to_char(movement_date, 'YYYY-MM') as month"),
                DB::raw('sum(quantity) as count')
            )
            ->groupBy('month')
            ->pluck('count', 'month');

        // Query prices by month (get the latest price for each month)
        $pricesData = ItemPrice::query()
            ->where('item_id', $item->id)
            ->whereBetween('effective_date', [$startDate, $endDate])
            ->select(
                DB::raw("to_char(effective_date, 'YYYY-MM') as month"),
                DB::raw('max(price) as price')
            )
            ->groupBy('month')
            ->pluck('price', 'month');

        // Build response arrays with 0 for months without data
        $entries = [];
        $exits = [];
        $prices = [];
        $lastKnownPrice = (float) $item->current_price;

        // Get the last price before the start date
        $priorPrice = ItemPrice::query()
            ->where('item_id', $item->id)
            ->where('effective_date', '<', $startDate)
            ->orderBy('effective_date', 'desc')
            ->value('price');

        if ($priorPrice !== null) {
            $lastKnownPrice = (float) $priorPrice;
        }

        foreach ($months as $month) {
            $entries[] = (int) ($entriesData[$month] ?? 0);
            $exits[] = (int) ($exitsData[$month] ?? 0);

            // For prices, use the last known price if no new price for this month
            if (isset($pricesData[$month])) {
                $lastKnownPrice = (float) $pricesData[$month];
            }
            $prices[] = $lastKnownPrice;
        }

        // Build the response as an array of objects for frontend compatibility
        $result = [];
        foreach ($months as $index => $month) {
            $result[] = [
                'month' => $month,
                'entries' => $entries[$index],
                'exits' => $exits[$index],
                'price' => $prices[$index],
            ];
        }

        return $result;
    }

    /**
     * Apply stock status filter.
     */
    private function applyStockStatusFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            'empty' => $query->whereStockEmpty(),
            'critical' => $query->where('number_critical_enabled', true)
                ->whereStockPositive()
                ->whereStockAtMost('number_critical_minimum'),
            'warning' => $query->where('number_warning_enabled', true)
                ->whereStockAbove('number_critical_minimum')
                ->whereStockAtMost('number_warning_minimum'),
            'ok' => $query->whereStockPositive()
                ->where(function (Builder $q): void {
                    $q->where('number_warning_enabled', false)
                        ->orWhereStockAbove('number_warning_minimum');
                }),
            default => $query,
        };
    }
}
