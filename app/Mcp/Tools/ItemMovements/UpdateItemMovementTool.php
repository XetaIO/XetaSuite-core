<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\ItemMovements;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\ItemMovements\UpdateItemMovement;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\ItemMovement;

#[Description('Update an existing stock movement.')]
class UpdateItemMovementTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['movement_id' => 'required|integer']);

        $movement = ItemMovement::findOrFail($request->get('movement_id'));

        abort_if(! $user->can('update', $movement), 403, 'Unauthorized action.');

        $request->validate([
            'quantity' => 'nullable|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'company_id' => 'nullable|integer',
            'company_invoice_number' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'movement_date' => 'nullable|date',
        ]);

        $fields = ['quantity', 'unit_price', 'company_id', 'company_invoice_number', 'invoice_date', 'notes', 'movement_date'];

        $data = [];
        foreach ($fields as $field) {
            if ($request->get($field) !== null) {
                $data[$field] = $request->get($field);
            }
        }

        $movement = app(UpdateItemMovement::class)->handle($movement, $data);

        return Response::json($movement);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'movement_id' => $schema->integer()->description('ID of the movement to update')->required(),
            'quantity' => $schema->integer()->description('New quantity'),
            'unit_price' => $schema->number()->description('New unit price'),
            'company_id' => $schema->integer()->description('New supplier'),
            'company_invoice_number' => $schema->string()->description('New invoice number'),
            'invoice_date' => $schema->string()->description('New invoice date (YYYY-MM-DD)'),
            'notes' => $schema->string()->description('New notes'),
            'movement_date' => $schema->string()->description('New movement date (YYYY-MM-DD)'),
        ];
    }
}
