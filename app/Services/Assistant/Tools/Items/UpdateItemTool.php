<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Items;

use XetaSuite\Actions\Items\UpdateItem;
use XetaSuite\Models\Item;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class UpdateItemTool extends AbstractAssistantTool
{
    public function __construct(private readonly UpdateItem $action)
    {
    }

    public function name(): string
    {
        return 'update_item';
    }

    public function permission(): ?string
    {
        return 'item.update';
    }

    protected function description(): string
    {
        return 'Met à jour un article existant';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['item_id'],
            'properties' => [
                'item_id' => ['type' => 'integer', 'description' => 'ID de l\'article à modifier'],
                'name' => ['type' => 'string', 'description' => 'Nouveau nom'],
                'reference' => ['type' => 'string', 'description' => 'Nouvelle référence'],
                'description' => ['type' => 'string', 'description' => 'Nouvelle description'],
                'company_id' => ['type' => 'integer', 'description' => 'Nouveau fournisseur'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $item = Item::findOrFail($args['item_id']);
        $this->authorizeModel($user, 'update', $item);

        $item = $this->action->handle($item, $user, $args);

        return [
            'success' => true,
            'id' => $item->id,
            'name' => $item->name,
        ];
    }
}
