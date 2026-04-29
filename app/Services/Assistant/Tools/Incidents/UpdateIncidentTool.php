<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Incidents;

use XetaSuite\Actions\Incidents\UpdateIncident;
use XetaSuite\Models\Incident;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class UpdateIncidentTool extends AbstractAssistantTool
{
    public function __construct(private readonly UpdateIncident $action)
    {
    }

    public function name(): string
    {
        return 'update_incident';
    }

    public function permission(): ?string
    {
        return 'incident.update';
    }

    protected function description(): string
    {
        return 'Met à jour un incident existant';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['incident_id'],
            'properties' => [
                'incident_id' => ['type' => 'integer', 'description' => 'ID de l\'incident à modifier'],
                'description' => ['type' => 'string', 'description' => 'Nouvelle description'],
                'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical'], 'description' => 'Nouvelle sévérité'],
                'status' => ['type' => 'string', 'enum' => ['open', 'in_progress', 'resolved', 'closed'], 'description' => 'Nouveau statut'],
                'resolved_at' => ['type' => 'string', 'description' => 'Date de résolution ISO 8601'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $incident = Incident::findOrFail($args['incident_id']);
        $this->authorizeModel($user, 'update', $incident);

        $incident = $this->action->handle($incident, $user, $args);

        return [
            'success' => true,
            'id' => $incident->id,
            'description' => $incident->description,
            'severity' => $incident->severity?->value,
            'status' => $incident->status?->value,
        ];
    }
}
