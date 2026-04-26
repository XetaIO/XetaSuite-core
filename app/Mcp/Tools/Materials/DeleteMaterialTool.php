<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Materials;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Materials\DeleteMaterial;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Material;

#[Description('Delete a material.')]
class DeleteMaterialTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['material_id' => 'required|integer']);

        $material = Material::findOrFail($request->get('material_id'));

        abort_if(! $user->can('delete', $material), 403, 'Unauthorized action.');

        app(DeleteMaterial::class)->handle($material);

        return Response::text("Material #{$request->get('material_id')} deleted successfully.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'material_id' => $schema->integer()->description('ID of the material to delete')->required(),
        ];
    }
}
