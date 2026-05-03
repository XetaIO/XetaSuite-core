<?php

declare(strict_types=1);

namespace XetaSuite\Http\Resources\V1\Concerns;

/**
 * Helpers that emit consistent, lightweight payloads for the `site`, `creator`
 * and `editor` relationships shared across most V1 API resources.
 *
 * The trait must be used on classes extending `Illuminate\Http\Resources\Json\JsonResource`
 * (or `JsonResource`-compatible) since it relies on `$this->whenLoaded()`.
 */
trait FormatsRelations
{
    /**
     * Return a {id, name} payload for the `site` relation when loaded.
     */
    protected function siteRelation(): mixed
    {
        return $this->whenLoaded('site', fn () => [
            'id' => $this->site->id,
            'name' => $this->site->name,
        ]);
    }

    /**
     * Return a minimal user payload for a relation when loaded.
     *
     * Includes `id`, `full_name` and (optionally) `email`.
     * Returns `null` when the relation is loaded but empty (e.g. nullable editor).
     */
    protected function userRelation(string $relation, bool $includeEmail = false): mixed
    {
        return $this->whenLoaded($relation, function () use ($relation, $includeEmail) {
            $user = $this->{$relation};

            if ($user === null) {
                return null;
            }

            $payload = [
                'id' => $user->id,
                'full_name' => $user->full_name,
            ];

            if ($includeEmail) {
                $payload['email'] = $user->email;
            }

            return $payload;
        });
    }
}
