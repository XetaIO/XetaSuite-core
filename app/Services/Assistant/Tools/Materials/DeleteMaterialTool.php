<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Materials;

use XetaSuite\Actions\Materials\DeleteMaterial;
use XetaSuite\Models\Material;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class DeleteMaterialTool extends AbstractAssistantTool
{
    public function __construct(private readonly DeleteMaterial $action)
    {
    }

    public function name(): string
    {
        return 'delete_material';
    }

    public function permission(): ?string
    {
        return 'material.delete';
    }

    protected function description(): string
    {
        return 'Supprime un matériel';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['material_id'],
            'properties' => [
                'material_id' => ['type' => 'integer', 'description' => 'ID du matériel à supprimer'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $material = Material::findOrFail($args['material_id']);
        $this->authorizeModel($user, 'delete', $material);

        $this->action->handle($material);

        return ['success' => true];
    }
}
