<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\CalendarEvents;

use XetaSuite\Actions\Calendar\CreateCalendarEvent;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class CreateCalendarEventTool extends AbstractAssistantTool
{
    public function __construct(private readonly CreateCalendarEvent $action)
    {
    }

    public function name(): string
    {
        return 'create_calendar_event';
    }

    public function permission(): ?string
    {
        return 'calendarEvent.create';
    }

    protected function description(): string
    {
        return 'Crée un événement dans le calendrier';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['title', 'start_at'],
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Titre de l\'événement'],
                'description' => ['type' => 'string', 'description' => 'Description'],
                'start_at' => ['type' => 'string', 'description' => 'Date de début ISO 8601'],
                'end_at' => ['type' => 'string', 'description' => 'Date de fin ISO 8601'],
                'all_day' => ['type' => 'boolean', 'description' => 'Événement toute la journée'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $event = $this->action->handle($user, $args);

        return [
            'success' => true,
            'id' => $event->id,
            'title' => $event->title,
            'start_at' => $event->start_at?->toIso8601String(),
        ];
    }
}
