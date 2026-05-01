<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\ItemMovements;

use XetaSuite\Actions\ItemMovements\DeleteItemMovement;
use XetaSuite\Models\ItemMovement;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class DeleteItemMovementTool extends AbstractAssistantTool
{
    public function __construct(private readonly DeleteItemMovement $action)
    {
    }

    public function name(): string
    {
        return 'delete_item_movement';
    }

    public function permission(): ?string
    {
        return 'item-movement.delete';
    }

    protected function description(): string
    {
        return 'Supprime un mouvement de stock';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['movement_id'],
            'properties' => [
                'movement_id' => ['type' => 'integer', 'description' => 'ID du mouvement à supprimer'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $movement = ItemMovement::findOrFail($args['movement_id']);
        $this->authorizeModel($user, 'delete', $movement);

        $this->action->handle($movement);

        return ['success' => true];
    }
}
