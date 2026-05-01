<?php

declare(strict_types=1);

namespace XetaSuite\Http\Resources\V1\Zones;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use XetaSuite\Http\Resources\V1\Concerns\FormatsRelations;

class ZoneResource extends JsonResource
{
    use FormatsRelations;
    /**
     * Transform the resource into an array (for list view).
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

            // Counts
            'children_count' => $this->whenCounted('children'),
            'material_count' => $this->material_count,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
