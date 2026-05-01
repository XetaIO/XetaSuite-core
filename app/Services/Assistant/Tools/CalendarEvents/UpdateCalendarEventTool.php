<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\CalendarEvents;

use XetaSuite\Actions\Calendar\UpdateCalendarEvent;
use XetaSuite\Models\CalendarEvent;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class UpdateCalendarEventTool extends AbstractAssistantTool
{
    public function __construct(private readonly UpdateCalendarEvent $action)
    {
    }

    public function name(): string
    {
        return 'update_calendar_event';
    }

    public function permission(): ?string
    {
        return 'calendarEvent.update';
    }

    protected function description(): string
    {
        return 'Met à jour un événement du calendrier existant';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_id'],
            'properties' => [
                'event_id' => ['type' => 'integer', 'description' => 'ID de l\'événement à modifier'],
                'title' => ['type' => 'string', 'description' => 'Nouveau titre'],
                'description' => ['type' => 'string', 'description' => 'Nouvelle description'],
                'start_at' => ['type' => 'string', 'description' => 'Nouvelle date de début ISO 8601'],
                'end_at' => ['type' => 'string', 'description' => 'Nouvelle date de fin ISO 8601'],
                'all_day' => ['type' => 'boolean', 'description' => 'Événement toute la journée'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $event = CalendarEvent::findOrFail($args['event_id']);
        $this->authorizeModel($user, 'update', $event);

        $event = $this->action->handle($event, $user, $args);

        return [
            'success' => true,
            'id' => $event->id,
            'title' => $event->title,
            'start_at' => $event->start_at?->toIso8601String(),
        ];
    }
}
