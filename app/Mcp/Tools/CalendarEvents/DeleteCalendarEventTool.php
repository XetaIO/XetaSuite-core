<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\CalendarEvents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Calendar\DeleteCalendarEvent;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\CalendarEvent;

#[Description('Delete a calendar event.')]
class DeleteCalendarEventTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['event_id' => 'required|integer']);

        $event = CalendarEvent::findOrFail($request->get('event_id'));

        abort_if(! $user->can('delete', $event), 403, 'Unauthorized action.');

        app(DeleteCalendarEvent::class)->handle($event);

        return Response::text("Event #{$request->get('event_id')} deleted successfully.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'event_id' => $schema->integer()->description('ID of the event to delete')->required(),
        ];
    }
}
