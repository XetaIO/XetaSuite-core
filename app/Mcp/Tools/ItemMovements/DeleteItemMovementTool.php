<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\ItemMovements;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\ItemMovements\DeleteItemMovement;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\ItemMovement;

#[Description('Delete a stock movement.')]
class DeleteItemMovementTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['movement_id' => 'required|integer']);

        $movement = ItemMovement::findOrFail($request->get('movement_id'));

        abort_if(! $user->can('delete', $movement), 403, 'Unauthorized action.');

        app(DeleteItemMovement::class)->handle($movement);

        return Response::text("Stock movement #{$request->get('movement_id')} deleted successfully.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'movement_id' => $schema->integer()->description('ID of the movement to delete')->required(),
        ];
    }
}
