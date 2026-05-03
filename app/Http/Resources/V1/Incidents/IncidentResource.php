<?php

declare(strict_types=1);

namespace XetaSuite\Http\Resources\V1\Incidents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use XetaSuite\Http\Resources\V1\Concerns\FormatsRelations;

class IncidentResource extends JsonResource
{
    use FormatsRelations;
    /**
     * Transform the resource into an array (for list view).
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'severity' => $this->severity?->value,
            'severity_label' => $this->severity?->label(),

            // Site info
            'site_id' => $this->site_id,
            'site' => $this->siteRelation(),

            // Material info
            'material_id' => $this->material_id,
            'material_name' => $this->material_name,
            'material' => $this->whenLoaded('material', fn () => [
                'id' => $this->material->id,
                'name' => $this->material->name,
            ]),

            // Maintenance info (optional)
            'maintenance_id' => $this->maintenance_id,
            'maintenance' => $this->whenLoaded('maintenance', fn () => $this->maintenance ? [
                'id' => $this->maintenance->id,
                'description' => $this->maintenance->description,
            ] : null),

            // Reporter info
            'reported_by_id' => $this->reported_by_id,
            'reported_by_name' => $this->reported_by_name,
            'reporter' => $this->userRelation('reporter'),

            // Dates
            'started_at' => $this->started_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
