<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Materials;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Materials\CreateMaterial;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Material;

#[Description('Create a new material in a site zone.')]
class CreateMaterialTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('create', Material::class), 403, 'Unauthorized action.');

        $request->validate([
            'zone_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cleaning_alert' => 'nullable|boolean',
            'cleaning_alert_frequency_type' => 'nullable|string|in:daily,weekly,monthly',
        ]);

        $material = app(CreateMaterial::class)->handle($user, [
            'zone_id' => $request->get('zone_id'),
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'cleaning_alert' => $request->get('cleaning_alert', false),
            'cleaning_alert_frequency_type' => $request->get('cleaning_alert_frequency_type', 'daily'),
        ]);

        return Response::json($material);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'zone_id' => $schema->integer()->description('ID of the zone to place the material')->required(),
            'name' => $schema->string()->description('Name of the material')->required(),
            'description' => $schema->string()->description('Description of the material'),
            'cleaning_alert' => $schema->boolean()->description('Enable cleaning alerts'),
            'cleaning_alert_frequency_type' => $schema->string()->description('Alert frequency: daily, weekly, or monthly'),
        ];
    }
}
