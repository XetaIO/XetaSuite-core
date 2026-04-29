<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Cleanings;

use XetaSuite\Actions\Cleanings\CreateCleaning;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class CreateCleaningTool extends AbstractAssistantTool
{
    public function __construct(private readonly CreateCleaning $action)
    {
    }

    public function name(): string
    {
        return 'create_cleaning';
    }

    public function permission(): ?string
    {
        return 'cleaning.create';
    }

    protected function description(): string
    {
        return 'Enregistre un nouveau nettoyage';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['material_id', 'description'],
            'properties' => [
                'material_id' => ['type' => 'integer', 'description' => 'ID du matériel nettoyé'],
                'description' => ['type' => 'string', 'description' => 'Description du nettoyage effectué'],
                'type' => ['type' => 'string', 'enum' => ['casual', 'daily', 'weekly', 'monthly'], 'description' => 'Type de nettoyage'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $cleaning = $this->action->handle($user, $args);

        return [
            'success' => true,
            'id' => $cleaning->id,
            'description' => $cleaning->description,
            'type' => $cleaning->type?->value,
        ];
    }
}
