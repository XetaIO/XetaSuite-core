<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Incidents;

use XetaSuite\Actions\Incidents\DeleteIncident;
use XetaSuite\Models\Incident;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class DeleteIncidentTool extends AbstractAssistantTool
{
    public function __construct(private readonly DeleteIncident $action)
    {
    }

    public function name(): string
    {
        return 'delete_incident';
    }

    public function permission(): ?string
    {
        return 'incident.delete';
    }

    protected function description(): string
    {
        return 'Supprime un incident';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['incident_id'],
            'properties' => [
                'incident_id' => ['type' => 'integer', 'description' => 'ID de l\'incident à supprimer'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $incident = Incident::findOrFail($args['incident_id']);
        $this->authorizeModel($user, 'delete', $incident);

        $this->action->handle($incident);

        return ['success' => true];
    }
}
