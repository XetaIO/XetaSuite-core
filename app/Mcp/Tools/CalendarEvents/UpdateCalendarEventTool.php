<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\CalendarEvents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Calendar\UpdateCalendarEvent;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\CalendarEvent;

#[Description('Update an existing calendar event.')]
class UpdateCalendarEventTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        $request->validate(['event_id' => 'required|integer']);

        $event = CalendarEvent::findOrFail($request->get('event_id'));

        abort_if(! $user->can('update', $event), 403, 'Unauthorized action.');

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date',
            'all_day' => 'nullable|boolean',
            'event_category_id' => 'nullable|integer',
        ]);

        $event = app(UpdateCalendarEvent::class)->handle($event, $user, array_filter([
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'color' => $request->get('color'),
            'start_at' => $request->get('start_at'),
            'end_at' => $request->get('end_at'),
            'all_day' => $request->get('all_day'),
            'event_category_id' => $request->get('event_category_id'),
        ], fn ($v) => $v !== null));

        return Response::json($event);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'event_id' => $schema->integer()->description('ID of the event to update')->required(),
            'title' => $schema->string()->description('New title'),
            'description' => $schema->string()->description('New description'),
            'color' => $schema->string()->description('New hexadecimal color'),
            'start_at' => $schema->string()->description('New start date in ISO 8601 format'),
            'end_at' => $schema->string()->description('New end date in ISO 8601 format'),
            'all_day' => $schema->boolean()->description('All-day event'),
            'event_category_id' => $schema->integer()->description('New category ID'),
        ];
    }
}
