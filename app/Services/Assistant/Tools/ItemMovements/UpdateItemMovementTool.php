<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\ItemMovements;

use XetaSuite\Actions\ItemMovements\UpdateItemMovement;
use XetaSuite\Models\ItemMovement;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class UpdateItemMovementTool extends AbstractAssistantTool
{
    public function __construct(private readonly UpdateItemMovement $action)
    {
    }

    public function name(): string
    {
        return 'update_item_movement';
    }

    public function permission(): ?string
    {
        return 'item-movement.update';
    }

    protected function description(): string
    {
        return 'Met à jour un mouvement de stock existant';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['movement_id'],
            'properties' => [
                'movement_id' => ['type' => 'integer', 'description' => 'ID du mouvement à modifier'],
                'quantity' => ['type' => 'integer', 'description' => 'Nouvelle quantité'],
                'unit_price' => ['type' => 'number', 'description' => 'Nouveau prix unitaire'],
                'notes' => ['type' => 'string', 'description' => 'Nouvelles notes'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $movement = ItemMovement::findOrFail($args['movement_id']);
        $this->authorizeModel($user, 'update', $movement);

        $movement = $this->action->handle($movement, $args);

        return [
            'success' => true,
            'id' => $movement->id,
            'quantity' => $movement->quantity,
        ];
    }
}
