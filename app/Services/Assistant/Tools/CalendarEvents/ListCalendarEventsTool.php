<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\CalendarEvents;

use XetaSuite\Models\CalendarEvent;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class ListCalendarEventsTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'list_calendar_events';
    }

    public function permission(): ?string
    {
        return 'calendarEvent.viewAny';
    }

    protected function description(): string
    {
        return 'Liste les événements du calendrier du site avec filtres optionnels';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Recherche textuelle sur le titre'],
                'start' => ['type' => 'string', 'description' => 'Filtrer les événements à partir de cette date ISO 8601'],
                'end' => ['type' => 'string', 'description' => 'Filtrer les événements jusqu\'à cette date ISO 8601'],
                'per_page' => ['type' => 'integer', 'description' => 'Nombre de résultats (défaut: 20)'],
            ],
        ];
    }

    public function execute(User $user, array $args): array
    {
        $query = CalendarEvent::query()
            ->with('eventCategory:id,color')
            ->forCurrentSite()
            ->select(['id', 'event_category_id', 'title', 'description', 'color', 'start_at', 'end_at', 'all_day'])
            ->orderBy('start_at');

        if (! empty($args['start'])) {
            $query->where('start_at', '>=', $args['start']);
        }

        if (! empty($args['end'])) {
            $query->where('end_at', '<=', $args['end']);
        }

        $perPage = min((int) ($args['per_page'] ?? 20), 50);
        $events = $query->limit($perPage)->get()->map(fn (CalendarEvent $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'description' => $e->description,
            'color' => $e->color,
            'start_at' => $e->start_at?->toIso8601String(),
            'end_at' => $e->end_at?->toIso8601String(),
            'all_day' => $e->all_day,
        ]);

        return ['data' => $events->all(), 'total' => $events->count()];
    }
}
