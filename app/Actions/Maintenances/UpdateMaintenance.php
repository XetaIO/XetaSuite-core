<?php

declare(strict_types=1);

namespace XetaSuite\Actions\Maintenances;

use Illuminate\Support\Facades\DB;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\User;

class UpdateMaintenance
{
    public function __construct(private readonly SyncMaintenanceRelationships $syncRelationships)
    {
    }

    /**
     * Update an existing maintenance.
     *
     * @param  Maintenance  $maintenance  The maintenance to update.
     * @param  User  $user  The user updating the maintenance.
     * @param  array  $data  The data to update.
     */
    public function handle(Maintenance $maintenance, User $user, array $data): Maintenance
    {
        return DB::transaction(function () use ($maintenance, $user, $data) {
            $siteId = $user->current_site_id;

            // Update maintenance fields
            $maintenance->update([
                'edited_by_id' => $user->id,
                'description' => $data['description'] ?? $maintenance->description,
                'type' => $data['type'] ?? $maintenance->type,
                'realization' => $data['realization'] ?? $maintenance->realization,
                'status' => $data['status'] ?? $maintenance->status,
                'started_at' => array_key_exists('started_at', $data) ? $data['started_at'] : $maintenance->started_at,
                'resolved_at' => array_key_exists('resolved_at', $data) ? $data['resolved_at'] : $maintenance->resolved_at,
            ]);

            // Note: material_id cannot be changed after creation

            // Sync incidents, operators and external companies
            $this->syncRelationships->handle($maintenance, $siteId, $data);

            return $maintenance->fresh([
                'material',
                'incidents',
                'operators',
                'companies',
                'creator',
                'editor',
                'itemMovements.item',
            ]);
        });
    }
}
