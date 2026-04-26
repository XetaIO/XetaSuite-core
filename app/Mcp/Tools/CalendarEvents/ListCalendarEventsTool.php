<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\CalendarEvents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Services\CalendarEventService;

#[Description('List calendar events for a site.')]
class ListCalendarEventsTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('calendar-event.viewAny'), 403, 'Unauthorized action.');

        $events = app(CalendarEventService::class)->getEvents([
            'start' => $request->get('start'),
            'end' => $request->get('end'),
            'category_id' => $request->get('category_id'),
        ]);

        return Response::json($events);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional, defaults to the current site)'),
            'start' => $schema->string()->description('Start date in ISO 8601 format to filter events'),
            'end' => $schema->string()->description('End date in ISO 8601 format to filter events'),
            'category_id' => $schema->integer()->description('Filter by category ID'),
        ];
    }
}
