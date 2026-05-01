<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Cleanings;

use XetaSuite\Actions\Cleanings\UpdateCleaning;
use XetaSuite\Models\Cleaning;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class UpdateCleaningTool extends AbstractAssistantTool
{
    public function __construct(private readonly UpdateCleaning $action)
    {
    }

    public function name(): string
    {
        return 'update_cleaning';
    }

    public function permission(): ?string
    {
        return 'cleaning.update';
    }

    protected function description(): string
    {
        return 'Met à jour un nettoyage existant';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['cleaning_id'],
            'properties' => [
                'cleaning_id' => ['type' => 'integer', 'description' => 'ID du nettoyage à modifier'],
                'description' => ['type' => 'string', 'description' => 'Nouvelle description'],
                'type' => ['type' => 'string', 'enum' => ['casual', 'daily', 'weekly', 'monthly'], 'description' => 'Nouveau type'],
                'material_id' => ['type' => 'integer', 'description' => 'Nouveau matériel concerné'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $cleaning = Cleaning::findOrFail($args['cleaning_id']);
        $this->authorizeModel($user, 'update', $cleaning);

        $cleaning = $this->action->handle($cleaning, $user, $args);

        return [
            'success' => true,
            'id' => $cleaning->id,
            'description' => $cleaning->description,
            'type' => $cleaning->type?->value,
        ];
    }
}
