<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Materials;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Material;
use XetaSuite\Services\MaterialService;

#[Description('List materials of the site with optional filters.')]
class ListMaterialsTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('viewAny', Material::class), 403, 'Unauthorized action.');

        $materials = app(MaterialService::class)->getPaginatedMaterials([
            'zone_id' => $request->get('zone_id'),
            'search' => $request->get('search'),
            'sort_by' => $request->get('sort_by'),
            'sort_direction' => $request->get('sort_direction'),
            'per_page' => $request->get('per_page', 20),
        ]);

        return Response::json($materials);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'zone_id' => $schema->integer()->description('Filter by zone ID'),
            'search' => $schema->string()->description('Textual search'),
            'sort_by' => $schema->string()->description('Sort field: name, created_at, or last_cleaning_at'),
            'sort_direction' => $schema->string()->description('Direction: asc or desc'),
            'per_page' => $schema->integer()->description('Number of items per page (default: 20)'),
        ];
    }
}
