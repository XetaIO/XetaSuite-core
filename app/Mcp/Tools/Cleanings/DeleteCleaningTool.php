<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\Cleanings;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Cleanings\DeleteCleaning;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\Cleaning;

#[Description('Delete a cleaning.')]
class DeleteCleaningTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['cleaning_id' => 'required|integer']);

        $cleaning = Cleaning::findOrFail($request->get('cleaning_id'));

        abort_if(! $user->can('delete', $cleaning), 403, 'Unauthorized action.');

        app(DeleteCleaning::class)->handle($cleaning);

        return Response::text("Cleaning #{$request->get('cleaning_id')} deleted successfully.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'cleaning_id' => $schema->integer()->description('ID of the cleaning to delete')->required(),
        ];
    }
}
