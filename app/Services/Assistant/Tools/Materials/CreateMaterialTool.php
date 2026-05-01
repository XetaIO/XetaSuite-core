<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Materials;

use XetaSuite\Actions\Materials\CreateMaterial;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class CreateMaterialTool extends AbstractAssistantTool
{
    public function __construct(private readonly CreateMaterial $action)
    {
    }

    public function name(): string
    {
        return 'create_material';
    }

    public function permission(): ?string
    {
        return 'material.create';
    }

    protected function description(): string
    {
        return 'Crée un nouveau matériel';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'zone_id'],
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Nom du matériel'],
                'zone_id' => ['type' => 'integer', 'description' => 'ID de la zone'],
                'description' => ['type' => 'string', 'description' => 'Description'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $material = $this->action->handle($user, $args);

        return [
            'success' => true,
            'id' => $material->id,
            'name' => $material->name,
        ];
    }
}
