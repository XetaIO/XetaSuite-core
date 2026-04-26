<?php

declare(strict_types=1);

namespace XetaSuite\Services\VoiceTools;

use XetaSuite\Models\CalendarEvent;
use XetaSuite\Models\User;

trait HandlesCalendarEvents
{
    /**
     * @param  array<string, mixed>  $args
     */
    private function listCalendarEvents(User $user, array $args): string
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

        return json_encode(['data' => $events, 'total' => $events->count()]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getCalendarEvent(User $user, array $args): string
    {
        $event = CalendarEvent::with(['eventCategory', 'createdBy'])
            ->findOrFail($args['event_id']);

        return json_encode($event->toArray());
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createCalendarEventAction(User $user, array $args): string
    {
        $event = $this->createCalendarEvent->handle($user, $args);

        return json_encode([
            'success' => true,
            'id' => $event->id,
            'title' => $event->title,
            'start_at' => $event->start_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function updateCalendarEventAction(User $user, array $args): string
    {
        $event = CalendarEvent::findOrFail($args['event_id']);
        $event = $this->updateCalendarEvent->handle($event, $user, $args);

        return json_encode([
            'success' => true,
            'id' => $event->id,
            'title' => $event->title,
            'start_at' => $event->start_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function deleteCalendarEventAction(User $user, array $args): string
    {
        $event = CalendarEvent::findOrFail($args['event_id']);
        $this->deleteCalendarEvent->handle($event);

        return json_encode(['success' => true]);
    }
}
