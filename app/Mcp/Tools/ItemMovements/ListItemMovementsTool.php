<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\ItemMovements;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\ItemMovement;
use XetaSuite\Services\ItemMovementService;

#[Description('List the stock movements of the site with optional filters.')]
class ListItemMovementsTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $siteId = $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('viewAny', ItemMovement::class), 403, 'Unauthorized action.');

        $movements = app(ItemMovementService::class)->getAllPaginatedMovements($siteId, [
            'type' => $request->get('type'),
            'item_id' => $request->get('item_id'),
            'search' => $request->get('search'),
            'sort_by' => $request->get('sort_by'),
            'sort_direction' => $request->get('sort_direction'),
            'per_page' => $request->get('per_page', 20),
        ]);

        return Response::json($movements);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'type' => $schema->string()->description('Filter by type: entry or exit'),
            'item_id' => $schema->integer()->description('Filter by item'),
            'search' => $schema->string()->description('Text search'),
            'sort_by' => $schema->string()->description('Sort field'),
            'sort_direction' => $schema->string()->description('Direction: asc or desc'),
            'per_page' => $schema->integer()->description('Number of items per page (default: 20)'),
        ];
    }
}
