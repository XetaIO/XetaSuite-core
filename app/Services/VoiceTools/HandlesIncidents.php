<?php

declare(strict_types=1);

namespace XetaSuite\Services\VoiceTools;

use XetaSuite\Models\Incident;
use XetaSuite\Models\Material;
use XetaSuite\Models\User;

trait HandlesIncidents
{
    /**
     * @param  array<string, mixed>  $args
     */
    private function listIncidents(User $user, array $args): string
    {
        $query = Incident::query()
            ->with('material:id,name')
            ->forCurrentSite()
            ->select(['id', 'material_id', 'material_name', 'description', 'severity', 'status', 'started_at'])
            ->orderByDesc('created_at');

        if (! empty($args['search'])) {
            $query->where('description', 'ilike', "%{$args['search']}%");
        }

        $perPage = min((int) ($args['per_page'] ?? 20), 50);
        $incidents = $query->limit($perPage)->get()->map(fn (Incident $i) => [
            'id' => $i->id,
            'description' => $i->description,
            'material' => $i->material?->name ?? $i->material_name,
            'severity' => $i->severity?->value,
            'status' => $i->status?->value,
            'started_at' => $i->started_at?->toIso8601String(),
        ]);

        return json_encode(['data' => $incidents, 'total' => $incidents->count()]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getIncident(User $user, array $args): string
    {
        $incident = Incident::with(['material', 'maintenance', 'reporter', 'editor'])
            ->findOrFail($args['incident_id']);

        return json_encode($incident->toArray());
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createIncidentAction(User $user, array $args): string
    {
        // If no material_id is provided, use the first available material for the site
        if (empty($args['material_id'])) {
            $material = Material::query()->forCurrentSite()->first();
            if ($material) {
                $args['material_id'] = $material->id;
            }
        }

        $incident = $this->createIncident->handle($user, $args);

        return json_encode([
            'success' => true,
            'id' => $incident->id,
            'description' => $incident->description,
            'severity' => $incident->severity?->value,
            'status' => $incident->status?->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function updateIncidentAction(User $user, array $args): string
    {
        $incident = Incident::findOrFail($args['incident_id']);
        $incident = $this->updateIncident->handle($incident, $user, $args);

        return json_encode([
            'success' => true,
            'id' => $incident->id,
            'description' => $incident->description,
            'severity' => $incident->severity?->value,
            'status' => $incident->status?->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function deleteIncidentAction(User $user, array $args): string
    {
        $incident = Incident::findOrFail($args['incident_id']);
        $this->deleteIncident->handle($incident);

        return json_encode(['success' => true]);
    }
}
