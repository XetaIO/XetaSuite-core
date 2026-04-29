<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Maintenances;

use XetaSuite\Actions\Maintenances\UpdateMaintenance;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class UpdateMaintenanceTool extends AbstractAssistantTool
{
    public function __construct(private readonly UpdateMaintenance $action)
    {
    }

    public function name(): string
    {
        return 'update_maintenance';
    }

    public function permission(): ?string
    {
        return 'maintenance.update';
    }

    protected function description(): string
    {
        return 'Met à jour une maintenance existante';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['maintenance_id'],
            'properties' => [
                'maintenance_id' => ['type' => 'integer', 'description' => 'ID de la maintenance'],
                'description' => ['type' => 'string', 'description' => 'Nouvelle description'],
                'status' => ['type' => 'string', 'enum' => ['pending', 'in_progress', 'completed', 'cancelled']],
                'type' => ['type' => 'string', 'enum' => ['preventive', 'corrective']],
                'resolved_at' => ['type' => 'string', 'description' => 'Date de résolution ISO 8601'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $maintenance = Maintenance::findOrFail($args['maintenance_id']);
        $this->authorizeModel($user, 'update', $maintenance);

        $maintenance = $this->action->handle($maintenance, $user, $args);

        return [
            'success' => true,
            'id' => $maintenance->id,
            'description' => $maintenance->description,
            'status' => $maintenance->status?->value,
        ];
    }
}
