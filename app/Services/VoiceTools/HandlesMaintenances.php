<?php

declare(strict_types=1);

namespace XetaSuite\Services\VoiceTools;

use XetaSuite\Models\Maintenance;
use XetaSuite\Models\User;

trait HandlesMaintenances
{
    /**
     * @param  array<string, mixed>  $args
     */
    private function listMaintenances(User $user, array $args): string
    {
        $query = Maintenance::query()
            ->with('material:id,name')
            ->forCurrentSite()
            ->select(['id', 'material_id', 'material_name', 'description', 'type', 'status', 'started_at', 'resolved_at'])
            ->orderByDesc('created_at');

        if (! empty($args['status'])) {
            $query->where('status', $args['status']);
        }

        if (! empty($args['type'])) {
            $query->where('type', $args['type']);
        }

        if (! empty($args['search'])) {
            $query->where('description', 'ilike', "%{$args['search']}%");
        }

        $maintenances = $query->limit(20)->get()->map(fn (Maintenance $m) => [
            'id' => $m->id,
            'description' => $m->description,
            'material' => $m->material?->name ?? $m->material_name,
            'type' => $m->type?->value,
            'status' => $m->status?->value,
            'started_at' => $m->started_at?->toIso8601String(),
            'resolved_at' => $m->resolved_at?->toIso8601String(),
        ]);

        return json_encode(['data' => $maintenances, 'total' => $maintenances->count()]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getMaintenance(User $user, array $args): string
    {
        $maintenance = Maintenance::with(['material', 'creator', 'editor', 'operators', 'companies', 'incidents'])
            ->findOrFail($args['maintenance_id']);

        return json_encode($maintenance->toArray());
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createMaintenanceAction(User $user, array $args): string
    {
        $maintenance = $this->createMaintenance->handle($user, $args);

        return json_encode([
            'success' => true,
            'id' => $maintenance->id,
            'description' => $maintenance->description,
            'status' => $maintenance->status?->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function updateMaintenanceAction(User $user, array $args): string
    {
        $maintenance = Maintenance::findOrFail($args['maintenance_id']);
        $maintenance = $this->updateMaintenance->handle($maintenance, $user, $args);

        return json_encode([
            'success' => true,
            'id' => $maintenance->id,
            'description' => $maintenance->description,
            'status' => $maintenance->status?->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function deleteMaintenanceAction(User $user, array $args): string
    {
        $maintenance = Maintenance::findOrFail($args['maintenance_id']);
        $this->deleteMaintenance->handle($maintenance);

        return json_encode(['success' => true]);
    }
}
