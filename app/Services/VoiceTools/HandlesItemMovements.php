<?php

declare(strict_types=1);

namespace XetaSuite\Services\VoiceTools;

use XetaSuite\Models\Item;
use XetaSuite\Models\ItemMovement;
use XetaSuite\Models\User;

trait HandlesItemMovements
{
    /**
     * @param  array<string, mixed>  $args
     */
    private function listItemMovements(User $user, array $args): string
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

        return json_encode(['data' => $movements, 'total' => $movements->count()]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getItemMovement(User $user, array $args): string
    {
        $movement = ItemMovement::with(['item', 'company', 'creator'])
            ->findOrFail($args['movement_id']);

        return json_encode($movement->toArray());
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createItemMovementAction(User $user, array $args): string
    {
        $item = Item::findOrFail($args['item_id']);
        $movement = $this->createItemMovement->handle($item, $user, $args);

        return json_encode([
            'success' => true,
            'id' => $movement->id,
            'type' => $movement->type,
            'quantity' => $movement->quantity,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function updateItemMovementAction(User $user, array $args): string
    {
        $movement = ItemMovement::findOrFail($args['movement_id']);
        $movement = $this->updateItemMovement->handle($movement, $args);

        return json_encode([
            'success' => true,
            'id' => $movement->id,
            'quantity' => $movement->quantity,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function deleteItemMovementAction(User $user, array $args): string
    {
        $movement = ItemMovement::findOrFail($args['movement_id']);
        $this->deleteItemMovement->handle($movement);

        return json_encode(['success' => true]);
    }
}
