<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Maintenances;

use XetaSuite\Actions\Maintenances\DeleteMaintenance;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class DeleteMaintenanceTool extends AbstractAssistantTool
{
    public function __construct(private readonly DeleteMaintenance $action)
    {
    }

    public function name(): string
    {
        return 'delete_maintenance';
    }

    public function permission(): ?string
    {
        return 'maintenance.delete';
    }

    protected function description(): string
    {
        return 'Supprime une maintenance';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['maintenance_id'],
            'properties' => [
                'maintenance_id' => ['type' => 'integer', 'description' => 'ID de la maintenance à supprimer'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $maintenance = Maintenance::findOrFail($args['maintenance_id']);
        $this->authorizeModel($user, 'delete', $maintenance);

        $this->action->handle($maintenance);

        return ['success' => true];
    }
}
