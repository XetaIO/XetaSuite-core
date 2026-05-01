<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Incidents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Incidents\CreateIncident;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Incident;

#[Description('Report a new incident on a material.')]
class CreateIncidentTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('create', Incident::class), 403, 'Unauthorized action.');

        $request->validate([
            'material_id' => 'required|integer',
            'description' => 'required|string',
            'started_at' => 'nullable|date',
            'resolved_at' => 'nullable|date',
            'severity' => 'nullable|string|in:low,medium,high,critical',
            'maintenance_id' => 'nullable|integer',
        ]);

        $incident = app(CreateIncident::class)->handle($user, [
            'material_id' => $request->get('material_id'),
            'description' => $request->get('description'),
            'started_at' => $request->get('started_at'),
            'resolved_at' => $request->get('resolved_at'),
            'severity' => $request->get('severity'),
            'maintenance_id' => $request->get('maintenance_id'),
        ]);

        return Response::json($incident);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'material_id' => $schema->integer()->description('ID of the material')->required(),
            'description' => $schema->string()->description('Description of the incident')->required(),
            'started_at' => $schema->string()->description('Start date of the incident ISO 8601'),
            'resolved_at' => $schema->string()->description('Resolution date ISO 8601 (if already resolved)'),
            'severity' => $schema->string()->description('Severity: low, medium, high, or critical'),
            'maintenance_id' => $schema->integer()->description('ID of the associated maintenance'),
        ];
    }
}
