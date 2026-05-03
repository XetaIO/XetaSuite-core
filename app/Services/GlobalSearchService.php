<?php

declare(strict_types=1);

namespace XetaSuite\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use XetaSuite\Models\Site;

class GlobalSearchService
{
    /**
     * Perform a global search across all authorized types.
     *
     * @return array{results: array<string, array>, total: int, query: string}
     */
    public function search(string $query, ?int $perType = null): array
    {
        $perType ??= (int) config('search.results_per_type', 5);

        $query = trim($query);

        if (strlen($query) < 2) {
            return [
                'results' => [],
                'total' => 0,
                'query' => $query,
            ];
        }

        $results = [];
        $total = 0;
        $isOnHq = isOnHeadquarters();

        foreach ($this->searchableTypes() as $type => $config) {
            // Skip HQ-only types if not on HQ
            if ($config['hq_only'] && ! $isOnHq) {
                continue;
            }

            // Check permission
            if (! Gate::allows($config['permission'])) {
                continue;
            }

            $typeResults = $this->searchType($query, $config, $perType);

            if ($typeResults->isNotEmpty()) {
                $results[$type] = [
                    'items' => $typeResults->map(fn ($item) => $this->formatResult($item, $type))->values()->all(),
                    'count' => $typeResults->count(),
                    'has_more' => $typeResults->count() >= $perType,
                ];
                $total += $typeResults->count();
            }
        }

        return [
            'results' => $results,
            'total' => $total,
            'query' => $query,
            'is_on_headquarters' => $isOnHq,
        ];
    }

    /**
     * Search within a specific type.
     */
    private function searchType(string $query, array $config, int $limit): Collection
    {
        $modelClass = $config['model'];

        $builder = $modelClass::query();

        // Load relations
        if (! empty($config['relations'])) {
            $builder->with($config['relations']);
        }

        // Apply site scoping for non-HQ-only models
        if (! $config['hq_only'] && method_exists($modelClass, 'scopeForCurrentSite')) {
            $builder->forCurrentSite();
        }

        // Apply search across columns
        $builder->where(function ($q) use ($query, $config): void {
            foreach ($config['columns'] as $i => $column) {
                $method = $i === 0 ? 'where' : 'orWhere';
                $q->$method($column, 'ILIKE', "%{$query}%");
            }
        });

        // Order by relevance (exact match first, then partial)
        $firstColumn = $config['columns'][0];
        $builder->orderByRaw("CASE WHEN {$firstColumn} ILIKE ? THEN 0 ELSE 1 END", [$query]);
        $builder->orderBy($firstColumn);

        return $builder->limit($limit)->get();
    }

    /**
     * Format a search result for the API response.
     */
    private function formatResult(mixed $item, string $type): array
    {
        $baseResult = [
            'id' => $item->id,
            'type' => $type,
        ];

        return match ($type) {
            'materials' => [
                ...$baseResult,
                'url' => "/materials/{$item->id}",
                'title' => $item->name,
                'subtitle' => $item->zone?->name,
                'description' => $item->description,
                'meta' => [
                    'site' => $item->site?->name,
                ],
            ],
            'zones' => [
                ...$baseResult,
                'url' => "/zones/{$item->id}",
                'title' => $item->name,
                'subtitle' => $item->parent?->name,
                'description' => null,
                'meta' => [
                    'site' => $item->site?->name,
                ],
            ],
            'items' => [
                ...$baseResult,
                'url' => "/items/{$item->id}",
                'title' => $item->name,
                'subtitle' => $item->reference,
                'description' => $item->description,
                'meta' => [
                    'site' => $item->site?->name,
                    'company' => $item->company?->name,
                    'stock' => $item->item_entry_total - $item->item_exit_total,
                ],
            ],
            'incidents' => [
                ...$baseResult,
                'url' => "/incidents/{$item->id}",
                'title' => str($item->description)->limit(50)->toString(),
                'subtitle' => $item->material?->name,
                'description' => $item->description,
                'meta' => [
                    'site' => $item->site?->name,
                    'status' => $item->status?->value,
                    'severity' => $item->severity?->value,
                    'reporter' => $item->reporter?->full_name,
                ],
            ],
            'maintenances' => [
                ...$baseResult,
                'url' => "/maintenances/{$item->id}",
                'title' => str($item->description)->limit(50)->toString(),
                'subtitle' => $item->material?->name,
                'description' => $item->description,
                'meta' => [
                    'site' => $item->site?->name,
                    'status' => $item->status?->value,
                    'type' => $item->type?->value,
                    'started_at' => $item->started_at?->toIso8601String(),
                ],
            ],
            'companies' => [
                ...$baseResult,
                'url' => "/companies/{$item->id}",
                'title' => $item->name,
                'subtitle' => null,
                'description' => $item->description,
                'meta' => [
                    'maintenance_count' => $item->maintenance_count,
                    'item_count' => $item->item_count,
                ],
            ],
            'sites' => [
                ...$baseResult,
                'url' => "/sites/{$item->id}",
                'title' => $item->name,
                'subtitle' => $item->is_headquarters ? 'Headquarters' : null,
                'description' => null,
                'meta' => [
                    'is_headquarters' => $item->is_headquarters,
                ],
            ],
            default => $baseResult,
        };
    }

    /**
     * Get the list of searchable types with their permissions for the current user.
     */
    public function getAvailableTypes(): array
    {
        $types = [];
        $isOnHq = isOnHeadquarters();

        foreach ($this->searchableTypes() as $type => $config) {
            if ($config['hq_only'] && ! $isOnHq) {
                continue;
            }

            if (Gate::allows($config['permission'])) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function searchableTypes(): array
    {
        return (array) config('search.searchable_types', []);
    }
}
