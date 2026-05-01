<?php

declare(strict_types=1);

namespace XetaSuite\Actions\Maintenances;

use XetaSuite\Models\Incident;
use XetaSuite\Models\Maintenance;

/**
 * Synchronizes the relationships of a `Maintenance` from the action payload:
 * linked incidents (one-to-many via `maintenance_id`), assigned operators and
 * external companies (many-to-many).
 *
 * Used by both {@see CreateMaintenance} and {@see UpdateMaintenance} to keep
 * the relationship handling in a single place.
 */
class SyncMaintenanceRelationships
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Maintenance $maintenance, int $siteId, array $data): void
    {
        if (isset($data['incident_ids']) && is_array($data['incident_ids'])) {
            $this->syncIncidents($maintenance, $siteId, $data['incident_ids']);
        }

        if (isset($data['operator_ids']) && is_array($data['operator_ids'])) {
            $maintenance->operators()->sync($data['operator_ids']);
        }

        if (isset($data['company_ids']) && is_array($data['company_ids'])) {
            $maintenance->companies()->sync($data['company_ids']);
        }
    }

    /**
     * Re-bind incidents to the maintenance: detach previous links, attach the
     * new selection (constrained to the same site) and refresh the cached count.
     *
     * @param  array<int, int>  $incidentIds
     */
    private function syncIncidents(Maintenance $maintenance, int $siteId, array $incidentIds): void
    {
        $maintenance->incidents()->update(['maintenance_id' => null]);

        if ($incidentIds !== []) {
            Incident::whereIn('id', $incidentIds)
                ->where('site_id', $siteId)
                ->update(['maintenance_id' => $maintenance->id]);
        }

        $maintenance->updateQuietly(['incident_count' => count($incidentIds)]);
    }
}
