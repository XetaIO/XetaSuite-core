<?php

declare(strict_types=1);

namespace XetaSuite\Actions\Items;

use XetaSuite\Models\Item;

/**
 * Synchronizes the many-to-many relationships of an `Item` (materials and
 * critical-stock recipients) from the action payload.
 *
 * Used by both {@see CreateItem} and {@see UpdateItem} to remove the
 * duplicated attach/sync logic.
 */
class SyncItemRelationships
{
    /**
     * @param  array<string, mixed>  $data
     * @param  bool  $isCreating  When true, only `attach` non-empty arrays.
     *                            When false, `sync` whenever the key is present
     *                            (including with an empty array, to detach all).
     */
    public function handle(Item $item, array $data, bool $isCreating = false): void
    {
        $this->syncRelation($item, 'materials', 'material_ids', $data, $isCreating);
        $this->syncRelation($item, 'recipients', 'recipient_ids', $data, $isCreating);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelation(Item $item, string $relation, string $key, array $data, bool $isCreating): void
    {
        if ($isCreating) {
            if (! empty($data[$key])) {
                $item->{$relation}()->attach($data[$key]);
            }

            return;
        }

        if (array_key_exists($key, $data)) {
            $item->{$relation}()->sync($data[$key] ?? []);
        }
    }
}
