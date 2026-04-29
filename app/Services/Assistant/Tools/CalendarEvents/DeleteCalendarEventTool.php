<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\CalendarEvents;

use XetaSuite\Actions\Calendar\DeleteCalendarEvent;
use XetaSuite\Models\CalendarEvent;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class DeleteCalendarEventTool extends AbstractAssistantTool
{
    public function __construct(private readonly DeleteCalendarEvent $action)
    {
    }

    public function name(): string
    {
        return 'delete_calendar_event';
    }

    public function permission(): ?string
    {
        return 'calendarEvent.delete';
    }

    protected function description(): string
    {
        return 'Supprime un événement du calendrier';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_id'],
            'properties' => [
                'event_id' => ['type' => 'integer', 'description' => 'ID de l\'événement à supprimer'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $event = CalendarEvent::findOrFail($args['event_id']);
        $this->authorizeModel($user, 'delete', $event);

        $this->action->handle($event);

        return ['success' => true];
    }
}
