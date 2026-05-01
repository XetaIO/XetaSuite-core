<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Materials;

use XetaSuite\Actions\Materials\UpdateMaterial;
use XetaSuite\Models\Material;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class UpdateMaterialTool extends AbstractAssistantTool
{
    public function __construct(private readonly UpdateMaterial $action)
    {
    }

    public function name(): string
    {
        return 'update_material';
    }

    public function permission(): ?string
    {
        return 'material.update';
    }

    protected function description(): string
    {
        return 'Met à jour un matériel existant';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['material_id'],
            'properties' => [
                'material_id' => ['type' => 'integer', 'description' => 'ID du matériel'],
                'name' => ['type' => 'string', 'description' => 'Nouveau nom'],
                'description' => ['type' => 'string', 'description' => 'Nouvelle description'],
                'zone_id' => ['type' => 'integer', 'description' => 'Nouvelle zone'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $material = Material::findOrFail($args['material_id']);
        $this->authorizeModel($user, 'update', $material);

        $material = $this->action->handle($material, $args);

        return [
            'success' => true,
            'id' => $material->id,
            'name' => $material->name,
        ];
    }
}
