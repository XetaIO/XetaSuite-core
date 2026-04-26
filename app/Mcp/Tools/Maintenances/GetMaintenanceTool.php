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

#[Description('Retrieve a maintenance by its ID.')]
class GetMaintenanceTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['maintenance_id' => 'required|integer']);

        $maintenance = Maintenance::with(['material', 'creator', 'editor', 'operators', 'companies', 'incidents'])
            ->findOrFail($request->get('maintenance_id'));

        abort_if(! $user->can('view', $maintenance), 403, 'Unauthorized action.');

        return Response::json($maintenance);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'maintenance_id' => $schema->integer()->description('ID of the maintenance')->required(),
        ];
    }
}
