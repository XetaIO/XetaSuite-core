<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Tools\CalendarEvents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use XetaSuite\Actions\Calendar\CreateCalendarEvent;
use XetaSuite\Mcp\Concerns\ResolvesSiteContext;
use XetaSuite\Models\CalendarEvent;

#[Description('Create a new calendar event for the site.')]
class CreateCalendarEventTool extends Tool
{
    use ResolvesSiteContext;

    public function handle(Request $request): Response
    {
        $this->resolveSiteId($request);
        $user = $request->user();

        abort_if(! $user->can('create', CalendarEvent::class), 403, 'Unauthorized action.');

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'all_day' => 'nullable|boolean',
            'event_category_id' => 'nullable|integer',
        ]);

        $event = app(CreateCalendarEvent::class)->handle($user, [
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'color' => $request->get('color'),
            'start_at' => $request->get('start_at'),
            'end_at' => $request->get('end_at'),
            'all_day' => $request->get('all_day', false),
            'event_category_id' => $request->get('event_category_id'),
        ]);

        return Response::json($event);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()->description('Site ID (optional)'),
            'title' => $schema->string()->description('Title of the event')->required(),
            'description' => $schema->string()->description('Description of the event'),
            'color' => $schema->string()->description('Hexadecimal color (e.g., #ff0000)'),
            'start_at' => $schema->string()->description('Start date and time in ISO 8601 format')->required(),
            'end_at' => $schema->string()->description('End date and time in ISO 8601 format'),
            'all_day' => $schema->boolean()->description('All-day event'),
            'event_category_id' => $schema->integer()->description('ID of the event category'),
        ];
    }
}
