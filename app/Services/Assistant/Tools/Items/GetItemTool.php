<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Items;

use XetaSuite\Models\Item;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class GetItemTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'get_item';
    }

    public function permission(): ?string
    {
        return 'item.view';
    }

    protected function description(): string
    {
        return 'Récupère le détail d\'un article';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['item_id'],
            'properties' => [
                'item_id' => ['type' => 'integer', 'description' => 'ID de l\'article'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $item = Item::with(['site', 'company', 'creator', 'materials'])
            ->findOrFail($args['item_id']);

        $this->authorizeModel($user, 'view', $item);

        return $item->toArray();
    }
}
