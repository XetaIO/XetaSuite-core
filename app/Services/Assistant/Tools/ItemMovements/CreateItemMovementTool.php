<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\ItemMovements;

use XetaSuite\Actions\ItemMovements\CreateItemMovement;
use XetaSuite\Models\Item;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class CreateItemMovementTool extends AbstractAssistantTool
{
    public function __construct(private readonly CreateItemMovement $action)
    {
    }

    public function name(): string
    {
        return 'create_item_movement';
    }

    public function permission(): ?string
    {
        return 'item-movement.create';
    }

    protected function description(): string
    {
        return 'Enregistre un mouvement de stock (entrée ou sortie)';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['item_id', 'type', 'quantity'],
            'properties' => [
                'item_id' => ['type' => 'integer', 'description' => 'ID de l\'article'],
                'type' => ['type' => 'string', 'enum' => ['entry', 'exit'], 'description' => 'Type de mouvement'],
                'quantity' => ['type' => 'integer', 'description' => 'Quantité'],
                'unit_price' => ['type' => 'number', 'description' => 'Prix unitaire (pour une entrée)'],
                'notes' => ['type' => 'string', 'description' => 'Notes optionnelles'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $item = Item::findOrFail($args['item_id']);
        $this->authorizeModel($user, 'view', $item);

        $movement = $this->action->handle($item, $user, $args);

        return [
            'success' => true,
            'id' => $movement->id,
            'type' => $movement->type,
            'quantity' => $movement->quantity,
        ];
    }
}
