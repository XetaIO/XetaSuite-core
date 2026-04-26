<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Cleanings;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Cleaning;
use XetaSuite\Services\CleaningService;

#[Description('List cleanings for the site with optional filters.')]
class ListCleaningsTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('viewAny', Cleaning::class), 403, 'Unauthorized action.');

        $cleanings = app(CleaningService::class)->getPaginatedCleanings([
            'material_id' => $request->get('material_id'),
            'type' => $request->get('type'),
            'search' => $request->get('search'),
            'per_page' => $request->get('per_page', 20),
        ]);

        return Response::json($cleanings);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'material_id' => $schema->integer()->description('Filter by material ID'),
            'type' => $schema->string()->description('Cleaning type: casual, periodic, deep'),
            'search' => $schema->string()->description('Text search'),
            'per_page' => $schema->integer()->description('Number of items per page (default: 20)'),
        ];
    }
}
