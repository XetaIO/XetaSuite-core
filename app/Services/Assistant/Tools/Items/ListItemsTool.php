<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Items;

use XetaSuite\Models\Item;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class ListItemsTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'list_items';
    }

    public function permission(): ?string
    {
        return 'item.viewAny';
    }

    protected function description(): string
    {
        return 'Liste les articles du site avec filtres optionnels';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Recherche textuelle'],
                'per_page' => ['type' => 'integer', 'description' => 'Nombre de résultats (défaut: 20)'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
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

        return ['data' => $items->all(), 'total' => $items->count()];
    }
}
