<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Materials;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Materials\UpdateMaterial;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Material;

#[Description('Update an existing material.')]
class UpdateMaterialTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['material_id' => 'required|integer']);

        $material = Material::findOrFail($request->get('material_id'));

        abort_if(! $user->can('update', $material), 403, 'Unauthorized action.');

        $request->validate([
            'zone_id' => 'nullable|integer',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'cleaning_alert' => 'nullable|boolean',
            'cleaning_alert_frequency_type' => 'nullable|string|in:daily,weekly,monthly',
        ]);

        $data = [];
        foreach (['zone_id', 'name', 'description', 'cleaning_alert', 'cleaning_alert_frequency_type'] as $field) {
            if ($request->get($field) !== null) {
                $data[$field] = $request->get($field);
            }
        }

        $material = app(UpdateMaterial::class)->handle($material, $data);

        return Response::json($material);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'material_id' => $schema->integer()->description('ID of the material to update')->required(),
            'zone_id' => $schema->integer()->description('New zone ID'),
            'name' => $schema->string()->description('New name'),
            'description' => $schema->string()->description('New description'),
            'cleaning_alert' => $schema->boolean()->description('Enable/disable cleaning alerts'),
            'cleaning_alert_frequency_type' => $schema->string()->description('New frequency: daily, weekly, or monthly'),
        ];
    }
}
