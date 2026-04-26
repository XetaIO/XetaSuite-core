<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Incidents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Incident;
use XetaSuite\Services\IncidentService;

#[Description('List incidents of the site with optional filters.')]
class ListIncidentsTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('viewAny', Incident::class), 403, 'Unauthorized action.');

        $incidents = app(IncidentService::class)->getPaginatedIncidents([
            'material_id' => $request->get('material_id'),
            'status' => $request->get('status'),
            'severity' => $request->get('severity'),
            'search' => $request->get('search'),
            'per_page' => $request->get('per_page', 20),
        ]);

        return Response::json($incidents);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'material_id' => $schema->integer()->description('Filter by material ID'),
            'status' => $schema->string()->description('Status: open or resolved'),
            'severity' => $schema->string()->description('Severity: low, medium, high, or critical'),
            'search' => $schema->string()->description('Textual search'),
            'per_page' => $schema->integer()->description('Number of items per page (default: 20)'),
        ];
    }
}
