<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Maintenances;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Maintenances\CreateMaintenance;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Maintenance;

#[Description('Create a new maintenance for a piece of equipment.')]
class CreateMaintenanceTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('create', Maintenance::class), 403, 'Unauthorized action.');

        $request->validate([
            'material_id' => 'nullable|integer',
            'description' => 'required|string',
            'reason' => 'required|string',
            'type' => 'nullable|string|in:corrective,preventive',
            'realization' => 'nullable|string|in:internal,external,both',
            'status' => 'nullable|string|in:planned,in_progress,resolved',
            'started_at' => 'nullable|date',
            'resolved_at' => 'nullable|date',
            'operator_ids' => 'nullable|array',
            'operator_ids.*' => 'integer',
            'company_ids' => 'nullable|array',
            'company_ids.*' => 'integer',
            'incident_ids' => 'nullable|array',
            'incident_ids.*' => 'integer',
        ]);

        $maintenance = app(CreateMaintenance::class)->handle($user, [
            'material_id' => $request->get('material_id'),
            'description' => $request->get('description'),
            'reason' => $request->get('reason'),
            'type' => $request->get('type'),
            'realization' => $request->get('realization'),
            'status' => $request->get('status'),
            'started_at' => $request->get('started_at'),
            'resolved_at' => $request->get('resolved_at'),
            'operator_ids' => $request->get('operator_ids', []),
            'company_ids' => $request->get('company_ids', []),
            'incident_ids' => $request->get('incident_ids', []),
        ]);

        return Response::json($maintenance);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'material_id' => $schema->integer()->description('ID of the equipment'),
            'description' => $schema->string()->description('Description of the maintenance')->required(),
            'reason' => $schema->string()->description('Reason for the maintenance')->required(),
            'type' => $schema->string()->description('Type: corrective or preventive'),
            'realization' => $schema->string()->description('Realization: internal, external, or both'),
            'status' => $schema->string()->description('Initial status: planned, in_progress, or resolved'),
            'started_at' => $schema->string()->description('Start date ISO 8601'),
            'resolved_at' => $schema->string()->description('Resolution date ISO 8601'),
            'operator_ids' => $schema->array()->items($schema->integer())->description('IDs of internal operators'),
            'company_ids' => $schema->array()->items($schema->integer())->description('IDs of contractor companies'),
            'incident_ids' => $schema->array()->items($schema->integer())->description('IDs of incidents related to this maintenance'),
        ];
    }
}
