<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Cleanings;

use XetaSuite\Models\Cleaning;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class GetCleaningTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'get_cleaning';
    }

    public function permission(): ?string
    {
        return 'cleaning.view';
    }

    protected function description(): string
    {
        return 'Récupère le détail d\'un nettoyage';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['cleaning_id'],
            'properties' => [
                'cleaning_id' => ['type' => 'integer', 'description' => 'ID du nettoyage'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $cleaning = Cleaning::with(['material', 'creator', 'editor'])
            ->findOrFail($args['cleaning_id']);

        $this->authorizeModel($user, 'view', $cleaning);

        return $cleaning->toArray();
    }
}
