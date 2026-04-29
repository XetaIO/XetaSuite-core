<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Maintenances;

use XetaSuite\Models\Maintenance;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class ListMaintenancesTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'list_maintenances';
    }

    public function permission(): ?string
    {
        return 'maintenance.viewAny';
    }

    protected function description(): string
    {
        return 'Liste les maintenances du site';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['pending', 'in_progress', 'completed', 'cancelled']],
                'type' => ['type' => 'string', 'enum' => ['preventive', 'corrective']],
                'search' => ['type' => 'string', 'description' => 'Recherche textuelle'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
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

        return ['data' => $maintenances->all(), 'total' => $maintenances->count()];
    }
}
