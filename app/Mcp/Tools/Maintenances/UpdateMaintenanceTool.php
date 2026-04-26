<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Maintenances;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Maintenances\UpdateMaintenance;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Maintenance;

#[Description('Update an existing maintenance.')]
class UpdateMaintenanceTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['maintenance_id' => 'required|integer']);

        $maintenance = Maintenance::findOrFail($request->get('maintenance_id'));

        abort_if(! $user->can('update', $maintenance), 403, 'Unauthorized action.');

        $request->validate([
            'description' => 'nullable|string',
            'reason' => 'nullable|string',
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

        $data = [];
        $fields = ['description', 'reason', 'type', 'realization', 'status', 'started_at', 'resolved_at', 'operator_ids', 'company_ids', 'incident_ids'];

        foreach ($fields as $field) {
            if ($request->get($field) !== null) {
                $data[$field] = $request->get($field);
            }
        }

        $maintenance = app(UpdateMaintenance::class)->handle($maintenance, $user, $data);

        return Response::json($maintenance);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'maintenance_id' => $schema->integer()->description('ID of the maintenance to update')->required(),
            'description' => $schema->string()->description('New description'),
            'reason' => $schema->string()->description('New reason'),
            'type' => $schema->string()->description('New type: corrective or preventive'),
            'realization' => $schema->string()->description('New realization: internal, external, or both'),
            'status' => $schema->string()->description('New status: planned, in_progress, or resolved'),
            'started_at' => $schema->string()->description('New start date ISO 8601'),
            'resolved_at' => $schema->string()->description('New resolution date ISO 8601'),
            'operator_ids' => $schema->array()->items($schema->integer())->description('New operator IDs'),
            'company_ids' => $schema->array()->items($schema->integer())->description('New company IDs'),
            'incident_ids' => $schema->array()->items($schema->integer())->description('New incident IDs'),
        ];
    }
}
