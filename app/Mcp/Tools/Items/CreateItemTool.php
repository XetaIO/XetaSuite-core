<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Items;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Items\CreateItem;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Item;

#[Description('Create a new item in the site.')]
class CreateItemTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('create', Item::class), 403, 'Unauthorized action.');

        $request->validate([
            'name' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'company_id' => 'nullable|integer',
            'company_reference' => 'nullable|string|max:255',
            'current_price' => 'nullable|numeric|min:0',
            'number_warning_enabled' => 'nullable|boolean',
            'number_warning_minimum' => 'nullable|integer|min:0',
            'number_critical_enabled' => 'nullable|boolean',
            'number_critical_minimum' => 'nullable|integer|min:0',
            'material_ids' => 'nullable|array',
            'material_ids.*' => 'integer',
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'integer',
        ]);

        $item = app(CreateItem::class)->handle($user, array_filter([
            'name' => $request->get('name'),
            'reference' => $request->get('reference'),
            'description' => $request->get('description'),
            'company_id' => $request->get('company_id'),
            'company_reference' => $request->get('company_reference'),
            'current_price' => $request->get('current_price', 0),
            'number_warning_enabled' => $request->get('number_warning_enabled', false),
            'number_warning_minimum' => $request->get('number_warning_minimum', 0),
            'number_critical_enabled' => $request->get('number_critical_enabled', false),
            'number_critical_minimum' => $request->get('number_critical_minimum', 0),
            'material_ids' => $request->get('material_ids'),
            'recipient_ids' => $request->get('recipient_ids'),
        ], fn ($v) => $v !== null));

        return Response::json($item);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'name' => $schema->string()->description('Item name')->required(),
            'reference' => $schema->string()->description('Internal reference'),
            'description' => $schema->string()->description('Description'),
            'company_id' => $schema->integer()->description('Supplier ID'),
            'company_reference' => $schema->string()->description('Supplier reference'),
            'current_price' => $schema->number()->description('Current unit price'),
            'number_warning_enabled' => $schema->boolean()->description('Enable low stock warning'),
            'number_warning_minimum' => $schema->integer()->description('Low stock warning threshold'),
            'number_critical_enabled' => $schema->boolean()->description('Enable critical stock warning'),
            'number_critical_minimum' => $schema->integer()->description('Critical stock warning threshold'),
            'material_ids' => $schema->array()->items($schema->integer())->description('Associated material IDs'),
            'recipient_ids' => $schema->array()->items($schema->integer())->description('Alert recipient IDs'),
        ];
    }
}
