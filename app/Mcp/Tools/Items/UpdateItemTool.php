<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Items;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Items\UpdateItem;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Item;

#[Description('Update an existing item.')]
class UpdateItemTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['item_id' => 'required|integer']);

        $item = Item::findOrFail($request->get('item_id'));

        abort_if(! $user->can('update', $item), 403, 'Unauthorized action.');

        $request->validate([
            'name' => 'nullable|string|max:255',
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

        $fields = [
            'name', 'reference', 'description', 'company_id', 'company_reference',
            'current_price', 'number_warning_enabled', 'number_warning_minimum',
            'number_critical_enabled', 'number_critical_minimum', 'material_ids', 'recipient_ids',
        ];

        $data = [];
        foreach ($fields as $field) {
            if ($request->get($field) !== null) {
                $data[$field] = $request->get($field);
            }
        }

        $item = app(UpdateItem::class)->handle($item, $user, $data);

        return Response::json($item);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'item_id' => $schema->integer()->description('ID of the item to update')->required(),
            'name' => $schema->string()->description('New name'),
            'reference' => $schema->string()->description('New internal reference'),
            'description' => $schema->string()->description('New description'),
            'company_id' => $schema->integer()->description('New supplier'),
            'company_reference' => $schema->string()->description('New supplier reference'),
            'current_price' => $schema->number()->description('New unit price'),
            'number_warning_enabled' => $schema->boolean()->description('Low stock alert enabled'),
            'number_warning_minimum' => $schema->integer()->description('Low stock alert threshold'),
            'number_critical_enabled' => $schema->boolean()->description('Critical stock alert enabled'),
            'number_critical_minimum' => $schema->integer()->description('Critical stock alert threshold'),
            'material_ids' => $schema->array()->items($schema->integer())->description('IDs of associated materials'),
            'recipient_ids' => $schema->array()->items($schema->integer())->description('IDs of alert recipients'),
        ];
    }
}
