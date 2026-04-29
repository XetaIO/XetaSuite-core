<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Items;

use XetaSuite\Actions\Items\DeleteItem;
use XetaSuite\Models\Item;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class DeleteItemTool extends AbstractAssistantTool
{
    public function __construct(private readonly DeleteItem $action)
    {
    }

    public function name(): string
    {
        return 'delete_item';
    }

    public function permission(): ?string
    {
        return 'item.delete';
    }

    protected function description(): string
    {
        return 'Supprime un article';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['item_id'],
            'properties' => [
                'item_id' => ['type' => 'integer', 'description' => 'ID de l\'article à supprimer'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $item = Item::findOrFail($args['item_id']);
        $this->authorizeModel($user, 'delete', $item);

        $this->action->handle($item);

        return ['success' => true];
    }
}
