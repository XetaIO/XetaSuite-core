<?php

declare(strict_types=1);

namespace XetaSuite\Services\VoiceTools;

use XetaSuite\Models\Item;
use XetaSuite\Models\User;

trait HandlesItems
{
    /**
     * @param  array<string, mixed>  $args
     */
    private function listItems(User $user, array $args): string
    {
        $query = Item::query()
            ->forCurrentSite()
            ->select(['id', 'name', 'reference', 'item_entry_total', 'item_exit_total'])
            ->orderBy('name');

        if (! empty($args['search'])) {
            $query->where('name', 'ilike', "%{$args['search']}%");
        }

        $perPage = min((int) ($args['per_page'] ?? 20), 50);
        $items = $query->limit($perPage)->get()->map(fn (Item $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'reference' => $item->reference,
            'current_stock' => $item->item_entry_total - $item->item_exit_total,
        ]);

        return json_encode(['data' => $items, 'total' => $items->count()]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getItem(User $user, array $args): string
    {
        $item = Item::with(['site', 'company', 'creator', 'materials'])
            ->findOrFail($args['item_id']);

        return json_encode($item->toArray());
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createItemAction(User $user, array $args): string
    {
        $item = $this->createItem->handle($user, $args);

        return json_encode([
            'success' => true,
            'id' => $item->id,
            'name' => $item->name,
            'reference' => $item->reference,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function updateItemAction(User $user, array $args): string
    {
        $item = Item::findOrFail($args['item_id']);
        $item = $this->updateItem->handle($item, $user, $args);

        return json_encode([
            'success' => true,
            'id' => $item->id,
            'name' => $item->name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function deleteItemAction(User $user, array $args): string
    {
        $item = Item::findOrFail($args['item_id']);
        $this->deleteItem->handle($item);

        return json_encode(['success' => true]);
    }
}
