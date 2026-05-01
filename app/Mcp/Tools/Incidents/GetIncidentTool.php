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

#[Description('Retrieve an incident by its ID.')]
class GetIncidentTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['incident_id' => 'required|integer']);

        $incident = Incident::with(['material', 'maintenance', 'reporter', 'editor'])
            ->findOrFail($request->get('incident_id'));

        abort_if(! $user->can('view', $incident), 403, 'Unauthorized action.');

        return Response::json($incident);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'incident_id' => $schema->integer()->description('ID of the incident')->required(),
        ];
    }
}
