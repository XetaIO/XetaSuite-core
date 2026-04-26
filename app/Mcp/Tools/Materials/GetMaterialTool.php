<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Materials;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Material;

#[Description('Get a material by its ID.')]
class GetMaterialTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['material_id' => 'required|integer']);

        $material = Material::with(['zone', 'site', 'creator'])
            ->findOrFail($request->get('material_id'));

        abort_if(! $user->can('view', $material), 403, 'Unauthorized action.');

        return Response::json($material);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'material_id' => $schema->integer()->description('ID of the material')->required(),
        ];
    }
}
