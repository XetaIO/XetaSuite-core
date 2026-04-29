<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Maintenances;

use XetaSuite\Models\Maintenance;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class GetMaintenanceTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'get_maintenance';
    }

    public function permission(): ?string
    {
        return 'maintenance.view';
    }

    protected function description(): string
    {
        return 'Récupère le détail d\'une maintenance';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['maintenance_id'],
            'properties' => [
                'maintenance_id' => ['type' => 'integer', 'description' => 'ID de la maintenance'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $maintenance = Maintenance::with(['material', 'creator', 'editor', 'operators', 'companies', 'incidents'])
            ->findOrFail($args['maintenance_id']);

        $this->authorizeModel($user, 'view', $maintenance);

        return $maintenance->toArray();
    }
}
