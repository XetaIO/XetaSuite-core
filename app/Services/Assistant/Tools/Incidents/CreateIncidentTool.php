<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Incidents;

use XetaSuite\Actions\Incidents\CreateIncident;
use XetaSuite\Models\Material;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class CreateIncidentTool extends AbstractAssistantTool
{
    public function __construct(private readonly CreateIncident $action)
    {
    }

    public function name(): string
    {
        return 'create_incident';
    }

    public function permission(): ?string
    {
        return 'incident.create';
    }

    protected function description(): string
    {
        return 'Signale un nouvel incident';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['description'],
            'properties' => [
                'description' => ['type' => 'string', 'description' => 'Description de l\'incident'],
                'material_id' => ['type' => 'integer', 'description' => 'ID du matériel concerné'],
                'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical'], 'description' => 'Sévérité'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        if (empty($args['material_id'])) {
            $material = Material::query()->forCurrentSite()->first();
            if ($material) {
                $args['material_id'] = $material->id;
            }
        }

        $incident = $this->action->handle($user, $args);

        return [
            'success' => true,
            'id' => $incident->id,
            'description' => $incident->description,
            'severity' => $incident->severity?->value,
            'status' => $incident->status?->value,
        ];
    }
}
