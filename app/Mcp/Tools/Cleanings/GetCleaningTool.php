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

#[Description('Get a cleaning by its ID.')]
class GetCleaningTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['cleaning_id' => 'required|integer']);

        $cleaning = Cleaning::with(['material', 'creator', 'editor'])
            ->findOrFail($request->get('cleaning_id'));

        abort_if(! $user->can('view', $cleaning), 403, 'Unauthorized action.');

        return Response::json($cleaning);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'cleaning_id' => $schema->integer()->description('ID of the cleaning')->required(),
        ];
    }
}
