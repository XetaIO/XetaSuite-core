<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Incidents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Incidents\UpdateIncident;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Incident;

#[Description('Update an existing incident.')]
class UpdateIncidentTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['incident_id' => 'required|integer']);

        $incident = Incident::findOrFail($request->get('incident_id'));

        abort_if(! $user->can('update', $incident), 403, 'Unauthorized action.');

        $request->validate([
            'material_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'started_at' => 'nullable|date',
            'resolved_at' => 'nullable|date',
            'severity' => 'nullable|string|in:low,medium,high,critical',
            'maintenance_id' => 'nullable|integer',
        ]);

        $data = [];
        foreach (['material_id', 'description', 'started_at', 'resolved_at', 'severity', 'maintenance_id'] as $field) {
            if ($request->get($field) !== null) {
                $data[$field] = $request->get($field);
            }
        }

        $incident = app(UpdateIncident::class)->handle($incident, $user, $data);

        return Response::json($incident);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'incident_id' => $schema->integer()->description('ID of the incident to update')->required(),
            'material_id' => $schema->integer()->description('New material ID'),
            'description' => $schema->string()->description('New description'),
            'started_at' => $schema->string()->description('New start date ISO 8601'),
            'resolved_at' => $schema->string()->description('Resolution date ISO 8601'),
            'severity' => $schema->string()->description('New severity: low, medium, high, or critical'),
            'maintenance_id' => $schema->integer()->description('ID of the associated maintenance'),
        ];
    }
}
