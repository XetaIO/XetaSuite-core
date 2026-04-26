<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Maintenances;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Maintenance;
use XetaSuite\Services\MaintenanceService;

#[Description('List maintenances of the site with optional filters.')]
class ListMaintenancesTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('viewAny', Maintenance::class), 403, 'Unauthorized action.');

        $maintenances = app(MaintenanceService::class)->getPaginatedMaintenances([
            'material_id' => $request->get('material_id'),
            'status' => $request->get('status'),
            'type' => $request->get('type'),
            'realization' => $request->get('realization'),
            'search' => $request->get('search'),
            'per_page' => $request->get('per_page', 20),
        ]);

        return Response::json($maintenances);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'material_id' => $schema->integer()->description('Filter by material ID'),
            'status' => $schema->string()->description('Status: planned, in_progress, or resolved'),
            'type' => $schema->string()->description('Type: corrective or preventive'),
            'realization' => $schema->string()->description('Realization: internal, external, or both'),
            'search' => $schema->string()->description('Textual search'),
            'per_page' => $schema->integer()->description('Number of items per page (default: 20)'),
        ];
    }
}
