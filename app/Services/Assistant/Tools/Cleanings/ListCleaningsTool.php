<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Cleanings;

use XetaSuite\Models\Cleaning;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class ListCleaningsTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'list_cleanings';
    }

    public function permission(): ?string
    {
        return 'cleaning.viewAny';
    }

    protected function description(): string
    {
        return 'Liste les nettoyages du site avec filtres optionnels';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['casual', 'daily', 'weekly', 'monthly'], 'description' => 'Filtrer par type de nettoyage'],
                'search' => ['type' => 'string', 'description' => 'Recherche textuelle sur la description'],
                'per_page' => ['type' => 'integer', 'description' => 'Nombre de résultats (défaut: 20)'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $query = Cleaning::query()
            ->with('material:id,name')
            ->forCurrentSite()
            ->select(['id', 'material_id', 'description', 'type', 'created_at'])
            ->orderByDesc('created_at');

        if (! empty($args['type'])) {
            $query->where('type', $args['type']);
        }

        if (! empty($args['search'])) {
            $query->where('description', 'ilike', "%{$args['search']}%");
        }

        $perPage = min((int) ($args['per_page'] ?? 20), 50);
        $cleanings = $query->limit($perPage)->get()->map(fn (Cleaning $c) => [
            'id' => $c->id,
            'description' => $c->description,
            'material' => $c->material?->name,
            'type' => $c->type?->value,
            'created_at' => $c->created_at?->toIso8601String(),
        ]);

        return ['data' => $cleanings->all(), 'total' => $cleanings->count()];
    }
}
