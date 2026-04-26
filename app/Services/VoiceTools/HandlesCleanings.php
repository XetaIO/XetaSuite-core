<?php

declare(strict_types=1);

namespace XetaSuite\Services\VoiceTools;

use XetaSuite\Models\Cleaning;
use XetaSuite\Models\User;

trait HandlesCleanings
{
    /**
     * @param  array<string, mixed>  $args
     */
    private function listCleanings(User $user, array $args): string
    {
        $query = Cleaning::query()
            ->with('material:id,name')
            ->forCurrentSite()
            ->select(['id', 'material_id', 'description', 'type', 'created_at'])
            ->orderByDesc('created_at');

        if (! empty($args['type'])) {
            $query->where('type', $args['type']);
        }

        if (! empty($args['search'])) {
            $query->where('description', 'ilike', "%{$args['search']}%");
        }

        $perPage = min((int) ($args['per_page'] ?? 20), 50);
        $cleanings = $query->limit($perPage)->get()->map(fn (Cleaning $c) => [
            'id' => $c->id,
            'description' => $c->description,
            'material' => $c->material?->name,
            'type' => $c->type?->value,
            'created_at' => $c->created_at?->toIso8601String(),
        ]);

        return json_encode(['data' => $cleanings, 'total' => $cleanings->count()]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getCleaning(User $user, array $args): string
    {
        $cleaning = Cleaning::with(['material', 'creator', 'editor'])
            ->findOrFail($args['cleaning_id']);

        return json_encode($cleaning->toArray());
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createCleaningAction(User $user, array $args): string
    {
        $cleaning = $this->createCleaning->handle($user, $args);

        return json_encode([
            'success' => true,
            'id' => $cleaning->id,
            'description' => $cleaning->description,
            'type' => $cleaning->type?->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function updateCleaningAction(User $user, array $args): string
    {
        $cleaning = Cleaning::findOrFail($args['cleaning_id']);
        $cleaning = $this->updateCleaning->handle($cleaning, $user, $args);

        return json_encode([
            'success' => true,
            'id' => $cleaning->id,
            'description' => $cleaning->description,
            'type' => $cleaning->type?->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function deleteCleaningAction(User $user, array $args): string
    {
        $cleaning = Cleaning::findOrFail($args['cleaning_id']);
        $this->deleteCleaning->handle($cleaning);

        return json_encode(['success' => true]);
    }
}
