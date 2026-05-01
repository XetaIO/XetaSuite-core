<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Incidents;

use XetaSuite\Models\Incident;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class GetIncidentTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'get_incident';
    }

    public function permission(): ?string
    {
        return 'incident.view';
    }

    protected function description(): string
    {
        return 'Récupère le détail d\'un incident';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['incident_id'],
            'properties' => [
                'incident_id' => ['type' => 'integer', 'description' => 'ID de l\'incident'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $incident = Incident::with(['material', 'maintenance', 'reporter', 'editor'])
            ->findOrFail($args['incident_id']);

        $this->authorizeModel($user, 'view', $incident);

        return $incident->toArray();
    }
}
