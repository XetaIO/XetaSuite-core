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

#[Description('Retrieve a stock movement by its ID.')]
class GetItemMovementTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['movement_id' => 'required|integer']);

        $movement = ItemMovement::with(['item', 'company', 'creator'])
            ->findOrFail($request->get('movement_id'));

        abort_if(! $user->can('view', $movement), 403, 'Unauthorized action.');

        return Response::json($movement);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'movement_id' => $schema->integer()->description('ID of the stock movement')->required(),
        ];
    }
}
