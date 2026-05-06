<?php

declare(strict_types=1);

namespace XetaSuite\Http\Resources\V1\Maintenances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \XetaSuite\Models\Maintenance
 */
class MaintenanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,

            // Enums
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'realization' => $this->realization->value,
            'realization_label' => $this->realization->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            // Material info
            'material_id' => $this->material_id,
            'material_name' => $this->material?->name ?? $this->material_name,
            'material' => $this->when($this->relationLoaded('material') && $this->material, fn () => [
                'id' => $this->material?->id,
                'name' => $this->material?->name,
            ]),

            // Site info
            'site_id' => $this->site_id,
            'site' => $this->when($this->relationLoaded('site') && $this->site, fn () => [
                'id' => $this->site?->id,
                'name' => $this->site?->name,
            ]),

            // Creator info
            'created_by_id' => $this->created_by_id,
            'created_by_name' => $this->creator?->full_name ?? $this->created_by_name,
            'creator' => $this->when($this->relationLoaded('creator') && $this->creator, fn () => [
                'id' => $this->creator?->id,
                'full_name' => $this->creator?->full_name,
            ]),

            // Counts
            'incident_count' => $this->incident_count ?? $this->incidents_count ?? 0,

            // Operators summary
            'operators' => $this->when($this->relationLoaded('operators'), fn () => $this->operators->map(fn ($op) => [
                'id' => $op->id,
                'full_name' => $op->full_name,
            ])),

            // Companies summary
            'companies' => $this->when($this->relationLoaded('companies'), fn () => $this->companies->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ])),

            // Dates
            'started_at' => $this->started_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
