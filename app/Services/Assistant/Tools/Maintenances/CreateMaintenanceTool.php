<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Maintenances;

use XetaSuite\Actions\Maintenances\CreateMaintenance;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class CreateMaintenanceTool extends AbstractAssistantTool
{
    public function __construct(private readonly CreateMaintenance $action)
    {
    }

    public function name(): string
    {
        return 'create_maintenance';
    }

    public function permission(): ?string
    {
        return 'maintenance.create';
    }

    protected function description(): string
    {
        return 'Planifie une nouvelle maintenance';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['material_id', 'description', 'type'],
            'properties' => [
                'material_id' => ['type' => 'integer', 'description' => 'ID du matériel'],
                'description' => ['type' => 'string', 'description' => 'Description de la maintenance'],
                'type' => ['type' => 'string', 'enum' => ['preventive', 'corrective'], 'description' => 'Type de maintenance'],
                'started_at' => ['type' => 'string', 'description' => 'Date de début ISO 8601'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $maintenance = $this->action->handle($user, $args);

        return [
            'success' => true,
            'id' => $maintenance->id,
            'description' => $maintenance->description,
            'status' => $maintenance->status?->value,
        ];
    }
}
