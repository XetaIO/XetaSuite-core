<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\ItemMovements;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\ItemMovements\CreateItemMovement;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Item;
use XetaSuite\Models\ItemMovement;

#[Description('Create a stock movement (entry or exit) for an item.')]
class CreateItemMovementTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('create', ItemMovement::class), 403, 'Unauthorized action.');

        $request->validate([
            'item_id' => 'required|integer',
            'type' => 'required|string|in:entry,exit',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'company_id' => 'nullable|integer',
            'company_invoice_number' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'movement_date' => 'nullable|date',
        ]);

        $item = Item::findOrFail($request->get('item_id'));

        $movement = app(CreateItemMovement::class)->handle($item, $user, array_filter([
            'type' => $request->get('type'),
            'quantity' => $request->get('quantity'),
            'unit_price' => $request->get('unit_price'),
            'company_id' => $request->get('company_id'),
            'company_invoice_number' => $request->get('company_invoice_number'),
            'invoice_date' => $request->get('invoice_date'),
            'notes' => $request->get('notes'),
            'movement_date' => $request->get('movement_date'),
        ], fn ($v) => $v !== null));

        return Response::json($movement);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'item_id' => $schema->integer()->description('ID of the item')->required(),
            'type' => $schema->string()->description('Type of movement: entry or exit')->required(),
            'quantity' => $schema->integer()->description('Quantity')->required(),
            'unit_price' => $schema->number()->description('Unit price'),
            'company_id' => $schema->integer()->description('ID of the supplier'),
            'company_invoice_number' => $schema->string()->description('Invoice number'),
            'invoice_date' => $schema->string()->description('Invoice date (YYYY-MM-DD)'),
            'notes' => $schema->string()->description('Notes'),
            'movement_date' => $schema->string()->description('Movement date (YYYY-MM-DD)'),
        ];
    }
}
