<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\CalendarEvents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\CalendarEvent;

#[Description('Get a calendar event by its ID.')]
class GetCalendarEventTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['event_id' => 'required|integer']);

        $event = CalendarEvent::with(['eventCategory', 'createdBy'])
            ->findOrFail($request->get('event_id'));

        abort_if(! $user->can('view', $event), 403, 'Unauthorized action.');

        return Response::json($event);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'event_id' => $schema->integer()->description('ID of the event to retrieve')->required(),
        ];
    }
}
