<?php

declare(strict_types=1);

namespace XetaSuite\Http\Resources\V1\Maintenances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \XetaSuite\Models\Maintenance
 */
class MaintenanceDetailResource extends JsonResource
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

            // Site info
            'site_id' => $this->site_id,
            'site' => $this->when($this->relationLoaded('site') && $this->site, fn () => [
                'id' => $this->site?->id,
                'name' => $this->site?->name,
            ]),

            // Material info
            'material_id' => $this->material_id,
            'material_name' => $this->material?->name ?? $this->material_name,
            'material' => $this->when($this->relationLoaded('material') && $this->material, [
                'id' => $this->material?->id,
                'name' => $this->material?->name,
                'zone' => $this->when($this->material?->relationLoaded('zone') && $this->material?->zone, [
                    'id' => $this->material?->zone?->id,
                    'name' => $this->material?->zone?->name,
                ]),
            ]),

            // Creator info
            'created_by_id' => $this->created_by_id,
            'created_by_name' => $this->creator?->full_name ?? $this->created_by_name,
            'creator' => $this->when($this->relationLoaded('creator') && $this->creator, [
                'id' => $this->creator?->id,
                'full_name' => $this->creator?->full_name,
            ]),

            // Editor info
            'edited_by_id' => $this->edited_by_id,
            'editor' => $this->when($this->relationLoaded('editor') && $this->editor, [
                'id' => $this->editor?->id,
                'full_name' => $this->editor?->full_name,
            ]),

            // Counts
            'incident_count' => $this->incident_count ?? $this->incidents_count ?? 0,
            'operator_count' => $this->when($this->relationLoaded('operators'), fn () => $this->operators->count()),
            'company_count' => $this->when($this->relationLoaded('companies'), fn () => $this->companies->count()),
            'item_movement_count' => $this->when($this->relationLoaded('itemMovements'), fn () => $this->itemMovements->count()),

            // Incidents
            'incidents' => $this->when($this->relationLoaded('incidents'), fn () => $this->incidents->map(fn ($inc) => [
                'id' => $inc->id,
                'description' => $inc->description,
                'severity' => $inc->severity->value,
                'severity_label' => $inc->severity->label(),
                'status' => $inc->status->value,
                'status_label' => $inc->status->label(),
            ])),

            // Operators
            'operators' => $this->when($this->relationLoaded('operators'), fn () => $this->operators->map(fn ($op) => [
                'id' => $op->id,
                'full_name' => $op->full_name,
                'email' => $op->email,
            ])),

            // Companies
            'companies' => $this->when($this->relationLoaded('companies'), fn () => $this->companies->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ])),

            // Item movements (spare parts)
            'item_movements' => $this->when($this->relationLoaded('itemMovements'), fn () => $this->itemMovements->map(fn ($mov) => [
                'id' => $mov->id,
                'item_id' => $mov->item_id,
                'item_name' => $mov->item?->name,
                'quantity' => $mov->quantity,
                'unit_price' => (float) $mov->unit_price,
                'total_price' => (float) $mov->total_price,
                'created_at' => $mov->created_at->toIso8601String(),
            ])),

            // Total cost
            'total_spare_parts_cost' => $this->when(
                $this->relationLoaded('itemMovements'),
                fn () =>
                (float) $this->itemMovements->sum('total_price')
            ),

            // Dates
            'started_at' => $this->started_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
