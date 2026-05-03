<?php

declare(strict_types=1);

namespace XetaSuite\Http\Resources\V1\Zones;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use XetaSuite\Http\Resources\V1\Concerns\FormatsRelations;

class ZoneDetailResource extends JsonResource
{
    use FormatsRelations;
    /**
     * Transform the resource into an array (for detail view).
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'allow_material' => $this->allow_material,

            // Parent info
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
            ]),

            // Site info
            'site_id' => $this->site_id,
            'site' => $this->siteRelation(),

            // Children zones
            'children' => $this->whenLoaded('children', fn () => $this->children->map(fn ($child) => [
                'id' => $child->id,
                'name' => $child->name,
                'allow_material' => $child->allow_material,
                'children_count' => $child->children_count ?? $child->children()->count(),
                'material_count' => $child->material_count ?? $child->materials()->count(),
            ])),

            // Materials (only when zone allows materials)
            'materials' => $this->when(
                $this->allow_material,
                fn () => $this->whenLoaded('materials', fn () => $this->materials->map(fn ($material) => [
                    'id' => $material->id,
                    'name' => $material->name,
                    'description' => $material->description,
                ]))
            ),

            // Counts
            'children_count' => $this->whenCounted('children'),
            'material_count' => $this->material_count,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
