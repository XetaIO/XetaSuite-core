<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Items;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Item;
use XetaSuite\Services\ItemService;

#[Description('List the site\'s items with optional filters.')]
class ListItemsTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('viewAny', Item::class), 403, 'Unauthorized action.');

        $items = app(ItemService::class)->getPaginatedItems([
            'company_id' => $request->get('company_id'),
            'stock_status' => $request->get('stock_status'),
            'search' => $request->get('search'),
            'sort_by' => $request->get('sort_by'),
            'sort_direction' => $request->get('sort_direction'),
            'per_page' => $request->get('per_page', 20),
        ]);

        return Response::json($items);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'company_id' => $schema->integer()->description('Filter by supplier'),
            'stock_status' => $schema->string()->description('Stock status: ok, warning, or critical'),
            'search' => $schema->string()->description('Text search'),
            'sort_by' => $schema->string()->description('Sort field'),
            'sort_direction' => $schema->string()->description('Direction: asc or desc'),
            'per_page' => $schema->integer()->description('Number of items per page (default: 20)'),
        ];
    }
}
