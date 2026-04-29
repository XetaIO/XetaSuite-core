<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Cleanings;

use XetaSuite\Actions\Cleanings\DeleteCleaning;
use XetaSuite\Models\Cleaning;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class DeleteCleaningTool extends AbstractAssistantTool
{
    public function __construct(private readonly DeleteCleaning $action)
    {
    }

    public function name(): string
    {
        return 'delete_cleaning';
    }

    public function permission(): ?string
    {
        return 'cleaning.delete';
    }

    protected function description(): string
    {
        return 'Supprime un nettoyage';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['cleaning_id'],
            'properties' => [
                'cleaning_id' => ['type' => 'integer', 'description' => 'ID du nettoyage à supprimer'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $cleaning = Cleaning::findOrFail($args['cleaning_id']);
        $this->authorizeModel($user, 'delete', $cleaning);

        $this->action->handle($cleaning);

        return ['success' => true];
    }
}
