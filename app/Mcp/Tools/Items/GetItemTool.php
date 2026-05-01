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

#[Description('Get an item by its ID.')]
class GetItemTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['item_id' => 'required|integer']);

        $item = Item::with(['site', 'company', 'creator', 'materials'])
            ->findOrFail($request->get('item_id'));

        abort_if(! $user->can('view', $item), 403, 'Unauthorized action.');

        return Response::json($item);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'item_id' => $schema->integer()->description('ID of the item')->required(),
        ];
    }
}
