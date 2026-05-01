<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Cleanings;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Cleanings\UpdateCleaning;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Cleaning;

#[Description('Update an existing cleaning.')]
class UpdateCleaningTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['cleaning_id' => 'required|integer']);

        $cleaning = Cleaning::findOrFail($request->get('cleaning_id'));

        abort_if(! $user->can('update', $cleaning), 403, 'Unauthorized action.');

        $request->validate([
            'material_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:casual,periodic,deep',
        ]);

        $cleaning = app(UpdateCleaning::class)->handle($cleaning, $user, array_filter([
            'material_id' => $request->get('material_id'),
            'description' => $request->get('description'),
            'type' => $request->get('type'),
        ], fn ($v) => $v !== null));

        return Response::json($cleaning);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'cleaning_id' => $schema->integer()->description('ID of the cleaning to update')->required(),
            'material_id' => $schema->integer()->description('New material ID'),
            'description' => $schema->string()->description('New description'),
            'type' => $schema->string()->description('New type: casual, periodic, or deep'),
        ];
    }
}
