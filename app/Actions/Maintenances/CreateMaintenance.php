<?php

declare(strict_types=1);

namespace XetaSuite\Actions\Maintenances;

use Illuminate\Support\Facades\DB;
use XetaSuite\Actions\ItemMovements\CreateItemMovement;
use XetaSuite\Enums\Maintenances\MaintenanceRealization;
use XetaSuite\Enums\Maintenances\MaintenanceStatus;
use XetaSuite\Enums\Maintenances\MaintenanceType;
use XetaSuite\Models\Item;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\Material;
use XetaSuite\Models\User;

class CreateMaintenance
{
    public function __construct(
        private CreateItemMovement $createItemMovement,
        private SyncMaintenanceRelationships $syncRelationships,
    ) {
    }

    /**
     * Create a new maintenance.
     *
     * @param  User  $user  The user creating the maintenance.
     * @param  array  $data  The data for the new maintenance.
     */
    public function handle(User $user, array $data): Maintenance
    {
        return DB::transaction(function () use ($user, $data) {
            $siteId = $user->current_site_id;
            $material = isset($data['material_id']) ? Material::findOrFail($data['material_id']) : null;

            // Create the maintenance
            $maintenance = Maintenance::create([
                'site_id' => $siteId,
                'material_id' => $material?->id,
                'material_name' => $material?->name,
                'created_by_id' => $user->id,
                'created_by_name' => $user->full_name,
                'description' => $data['description'],
                'type' => $data['type'] ?? MaintenanceType::CORRECTIVE,
                'realization' => $data['realization'] ?? MaintenanceRealization::INTERNAL,
                'status' => $data['status'] ?? MaintenanceStatus::PLANNED,
                'started_at' => $data['started_at'] ?? null,
                'resolved_at' => $data['resolved_at'] ?? null,
            ]);

            // Sync incidents, operators and external companies
            $this->syncRelationships->handle($maintenance, $siteId, $data);

            // Create item movements (spare parts exit)
            if (isset($data['item_movements']) && is_array($data['item_movements'])) {
                $this->createItemMovements($maintenance, $user, $data['item_movements']);
            }

            return $maintenance->load([
                'material',
                'incidents',
                'operators',
                'companies',
                'creator',
                'itemMovements.item',
            ]);
        });
    }

    /**
     * Create item movements for items.
     */
    private function createItemMovements(Maintenance $maintenance, User $user, array $spareParts): void
    {
        foreach ($spareParts as $part) {
            $item = Item::findOrFail($part['item_id']);
            $this->createItemMovement->handle($item, $user, [
                'type' => 'exit',
                'quantity' => $part['quantity'],
                'movable_type' => Maintenance::class,
                'movable_id' => $maintenance->id,
                'created_by_id' => $user->id,
                'notes' => __('maintenances.spare_parts') . ' - Maintenance #' . $maintenance->id,
            ]);
        }
    }
}
