<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\ItemMovements;

use XetaSuite\Models\ItemMovement;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class ListItemMovementsTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'list_item_movements';
    }

    public function permission(): ?string
    {
        return 'item-movement.viewAny';
    }

    protected function description(): string
    {
        return 'Liste les mouvements de stock du site';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['entry', 'exit'], 'description' => 'Filtrer par type de mouvement'],
                'item_id' => ['type' => 'integer', 'description' => 'Filtrer par article'],
                'per_page' => ['type' => 'integer', 'description' => 'Nombre de résultats (défaut: 20)'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $query = ItemMovement::query()
            ->with('item:id,name')
            ->when(! isOnHeadquarters(), fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('site_id', session('current_site_id'))))
            ->select(['id', 'item_id', 'type', 'quantity', 'unit_price', 'total_price', 'movement_date'])
            ->orderByDesc('movement_date');

        if (! empty($args['type'])) {
            $query->where('type', $args['type']);
        }

        if (! empty($args['item_id'])) {
            $query->where('item_id', $args['item_id']);
        }

        $perPage = min((int) ($args['per_page'] ?? 20), 50);
        $movements = $query->limit($perPage)->get()->map(fn (ItemMovement $m) => [
            'id' => $m->id,
            'item' => $m->item?->name,
            'type' => $m->type,
            'quantity' => $m->quantity,
            'unit_price' => $m->unit_price,
            'total_price' => $m->total_price,
            'movement_date' => $m->movement_date?->toIso8601String(),
        ]);

        return ['data' => $movements->all(), 'total' => $movements->count()];
    }
}
