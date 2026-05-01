<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\CalendarEvents;

use XetaSuite\Models\CalendarEvent;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class GetCalendarEventTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'get_calendar_event';
    }

    public function permission(): ?string
    {
        return 'calendarEvent.view';
    }

    protected function description(): string
    {
        return 'Récupère le détail d\'un événement du calendrier';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_id'],
            'properties' => [
                'event_id' => ['type' => 'integer', 'description' => 'ID de l\'événement'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $event = CalendarEvent::with(['eventCategory', 'createdBy'])
            ->findOrFail($args['event_id']);

        $this->authorizeModel($user, 'view', $event);

        return $event->toArray();
    }
}
