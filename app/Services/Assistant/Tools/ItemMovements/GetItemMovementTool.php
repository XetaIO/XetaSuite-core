<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\ItemMovements;

use XetaSuite\Models\ItemMovement;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class GetItemMovementTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'get_item_movement';
    }

    public function permission(): ?string
    {
        return 'item-movement.view';
    }

    protected function description(): string
    {
        return 'Récupère le détail d\'un mouvement de stock';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['movement_id'],
            'properties' => [
                'movement_id' => ['type' => 'integer', 'description' => 'ID du mouvement'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $movement = ItemMovement::with(['item', 'company', 'creator'])
            ->findOrFail($args['movement_id']);

        $this->authorizeModel($user, 'view', $movement);

        return $movement->toArray();
    }
}
