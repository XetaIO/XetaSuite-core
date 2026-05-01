<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Items;

use XetaSuite\Actions\Items\CreateItem;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class CreateItemTool extends AbstractAssistantTool
{
    public function __construct(private readonly CreateItem $action)
    {
    }

    public function name(): string
    {
        return 'create_item';
    }

    public function permission(): ?string
    {
        return 'item.create';
    }

    protected function description(): string
    {
        return 'Crée un nouvel article';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Nom de l\'article'],
                'reference' => ['type' => 'string', 'description' => 'Référence de l\'article'],
                'description' => ['type' => 'string', 'description' => 'Description'],
                'company_id' => ['type' => 'integer', 'description' => 'ID du fournisseur'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $item = $this->action->handle($user, $args);

        return [
            'success' => true,
            'id' => $item->id,
            'name' => $item->name,
            'reference' => $item->reference,
        ];
    }
}
