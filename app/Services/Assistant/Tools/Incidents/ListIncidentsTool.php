<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Incidents;

use XetaSuite\Models\Incident;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class ListIncidentsTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'list_incidents';
    }

    public function permission(): ?string
    {
        return 'incident.viewAny';
    }

    protected function description(): string
    {
        return 'Liste les incidents du site';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Recherche textuelle'],
                'per_page' => ['type' => 'integer', 'description' => 'Nombre de résultats'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
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

        return ['data' => $incidents->all(), 'total' => $incidents->count()];
    }
}
