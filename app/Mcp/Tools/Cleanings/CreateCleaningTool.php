<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Cleanings;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Cleanings\CreateCleaning;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Cleaning;

#[Description('Create a new cleaning for a material.')]
class CreateCleaningTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('create', Cleaning::class), 403, 'Unauthorized action.');

        $request->validate([
            'material_id' => 'required|integer',
            'description' => 'required|string',
            'type' => 'nullable|string|in:casual,periodic,deep',
        ]);

        $cleaning = app(CreateCleaning::class)->handle($user, [
            'material_id' => $request->get('material_id'),
            'description' => $request->get('description'),
            'type' => $request->get('type'),
        ]);

        return Response::json($cleaning);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'material_id' => $schema->integer()->description('ID of the cleaned material')->required(),
            'description' => $schema->string()->description('Description of the cleaning performed')->required(),
            'type' => $schema->string()->description('Type: casual, periodic, or deep (default: casual)'),
        ];
    }
}
