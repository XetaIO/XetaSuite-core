<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Materials;

use XetaSuite\Models\Material;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class GetMaterialTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'get_material';
    }

    public function permission(): ?string
    {
        return 'material.view';
    }

    protected function description(): string
    {
        return 'Récupère le détail d\'un matériel';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['material_id'],
            'properties' => [
                'material_id' => ['type' => 'integer', 'description' => 'ID du matériel'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $material = Material::with(['zone', 'site', 'creator'])
            ->findOrFail($args['material_id']);

        $this->authorizeModel($user, 'view', $material);

        return $material->toArray();
    }
}
