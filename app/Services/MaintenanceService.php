<?php

declare(strict_types=1);

namespace XetaSuite\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use XetaSuite\Services\Concerns\HasSearchAndSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use XetaSuite\Enums\Maintenances\MaintenanceRealization;
use XetaSuite\Enums\Maintenances\MaintenanceStatus;
use XetaSuite\Enums\Maintenances\MaintenanceType;
use XetaSuite\Models\Company;
use XetaSuite\Models\Incident;
use XetaSuite\Models\Item;
use XetaSuite\Models\ItemMovement;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\Material;
use XetaSuite\Models\User;

class MaintenanceService
{
    use HasSearchAndSort;

    private const ALLOWED_SORTS = ['started_at', 'resolved_at', 'status', 'type', 'created_at'];

    /**
     * Get a paginated list of maintenances with optional search and sorting.
     *
     * @param  array{material_id?: int, status?: string, type?: string, realization?: string, search?: string, sort_by?: string, sort_direction?: string, per_page?: int}  $filters
     */
    public function getPaginatedMaintenances(array $filters = []): LengthAwarePaginator
    {
        return Maintenance::query()
            ->with(['material', 'creator', 'operators', 'companies', 'site'])
            ->withCount('incidents')
            ->forCurrentSite()
            ->when($filters['material_id'] ?? null, fn (Builder $query, int $materialId) => $query->where('material_id', $materialId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['realization'] ?? null, fn (Builder $query, string $realization) => $query->where('realization', $realization))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->applySearch($query, $search))
            ->when(
                $filters['sort_by'] ?? null,
                fn (Builder $query, string $sortBy) => $this->applySortFilter($query, $sortBy, $filters['sort_direction'] ?? 'desc', self::ALLOWED_SORTS, 'created_at', 'desc'),
                fn (Builder $query) => $query->orderByDesc('created_at')
            )
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get incidents for a maintenance (paginated).
     */
    public function getMaintenanceIncidents(Maintenance $maintenance, int $perPage = 10): LengthAwarePaginator
    {
        return Incident::query()
            ->with(['material', 'reporter'])
            ->where('maintenance_id', $maintenance->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get item movements for a maintenance (paginated).
     */
    public function getMaintenanceItemMovements(Maintenance $maintenance, int $perPage = 10): LengthAwarePaginator
    {
        return ItemMovement::query()
            ->with(['item', 'creator'])
            ->where('movable_type', Maintenance::class)
            ->where('movable_id', $maintenance->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get total spare parts cost for a maintenance.
     */
    public function getMaintenanceTotalCost(Maintenance $maintenance): float
    {
        return (float) ItemMovement::query()
            ->where('movable_type', Maintenance::class)
            ->where('movable_id', $maintenance->id)
            ->sum('total_price');
    }

    /**
     * Get available materials for maintenance creation (materials on current site).
     */
    public function getAvailableMaterials(?string $search = null): Collection
    {
        return Material::query()
            ->forCurrentSite()
            ->when($search, fn (Builder $query, string $s) => $query->where('name', 'ILIKE', "%{$s}%"))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    /**
     * Get available incidents for maintenance (unlinked or linked to this maintenance).
     */
    public function getAvailableIncidents(?int $maintenanceId = null, ?string $search = null): Collection
    {
        return Incident::query()
            ->forCurrentSite()
            ->where(function (Builder $query) use ($maintenanceId): void {
                $query->whereNull('maintenance_id');
                if ($maintenanceId) {
                    $query->orWhere('maintenance_id', $maintenanceId);
                }
            })
            ->when($search, fn (Builder $query, string $s) => $query->where(function (Builder $q) use ($s): void {
                $q->where('id', 'like', "%{$s}%")
                    ->orWhere('description', 'ILIKE', "%{$s}%");
            }))
            ->orderByDesc('created_at')
            ->limit(15)
            ->get(['id', 'description', 'severity', 'status']);
    }

    /**
     * Get available operators for maintenance (users on current site).
     */
    public function getAvailableOperators(?string $search = null): Collection
    {
        return User::query()
            ->whereHas('sites', fn (Builder $query) => $query->where('site_id', session('current_site_id')))
            ->when($search, fn (Builder $query, string $s) => $query->where(function (Builder $q) use ($s): void {
                $q->where('first_name', 'ILIKE', "%{$s}%")
                    ->orWhere('last_name', 'ILIKE', "%{$s}%")
                    ->orWhere('email', 'ILIKE', "%{$s}%");
            }))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(15)
            ->get(['id', 'first_name', 'last_name', 'email']);
    }

    /**
     * Get available companies for maintenance.
     */
    public function getAvailableCompanies(?string $search = null): Collection
    {
        return Company::query()
            ->when($search, fn (Builder $query, string $s) => $query->where('name', 'ILIKE', "%{$s}%"))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    /**
     * Get available items for spare parts (items on current site with stock > 0).
     *
     * @return SupportCollection<int, array{id: int, name: string, reference: ?string, current_stock: int, current_price: float}>
     */
    public function getAvailableItems(?string $search = null): SupportCollection
    {
        return Item::query()
            ->forCurrentSite()
            ->whereStockPositive()
            ->when($search, fn (Builder $query, string $s) => $query->where(function (Builder $q) use ($s): void {
                $q->where('name', 'ILIKE', "%{$s}%")
                    ->orWhere('reference', 'ILIKE', "%{$s}%");
            }))
            ->orderBy('name')
            ->limit(15)
            ->selectRaw('id, name, reference, '.Item::stockExpression().' as stock, current_price')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'reference' => $item->reference,
                'current_stock' => (int) $item->stock,
                'current_price' => (float) $item->current_price,
            ]);
    }

    /**
     * Get all type options.
     *
     * @return array<array{value: string, label: string}>
     */
    public function getTypeOptions(): array
    {
        return array_map(fn (MaintenanceType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ], MaintenanceType::cases());
    }

    /**
     * Get all status options.
     *
     * @return array<array{value: string, label: string}>
     */
    public function getStatusOptions(): array
    {
        return array_map(fn (MaintenanceStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ], MaintenanceStatus::cases());
    }

    /**
     * Get all realization options.
     *
     * @return array<array{value: string, label: string}>
     */
    public function getRealizationOptions(): array
    {
        return array_map(fn (MaintenanceRealization $realization) => [
            'value' => $realization->value,
            'label' => $realization->label(),
        ], MaintenanceRealization::cases());
    }

    /**
     * Apply search filter to query.
     */
    private function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search): void {
            $q->where('description', 'ILIKE', "%{$search}%")
                ->orWhereHas('material', fn (Builder $mq) => $mq->where('name', 'ILIKE', "%{$search}%"));
        });
    }

}
