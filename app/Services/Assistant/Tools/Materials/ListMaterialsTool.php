<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Materials;

use XetaSuite\Models\Material;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class ListMaterialsTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'list_materials';
    }

    public function permission(): ?string
    {
        return 'material.viewAny';
    }

    protected function description(): string
    {
        return 'Liste les matériels du site';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Recherche textuelle'],
                'per_page' => ['type' => 'integer', 'description' => 'Nombre de résultats (défaut: 20)'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $query = Material::query()
            ->forCurrentSite()
            ->select(['id', 'name', 'description'])
            ->orderBy('name');

        if (! empty($args['search'])) {
            $query->where('name', 'ilike', "%{$args['search']}%");
        }

        $perPage = min((int) ($args['per_page'] ?? 20), 50);
        $materials = $query->limit($perPage)->get(['id', 'name', 'description']);

        return ['data' => $materials->all(), 'total' => $materials->count()];
    }
}
