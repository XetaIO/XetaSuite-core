<?php

declare(strict_types=1);

namespace XetaSuite\Http\Resources\V1\Items;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use XetaSuite\Http\Resources\V1\Concerns\FormatsRelations;

class ItemResource extends JsonResource
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
            'reference' => $this->reference,
            'description' => $this->description,

            'site_id' => $this->site_id,
            'site' => $this->siteRelation(),

            // Company info
            'company_id' => $this->company_id,
            'company_name' => $this->company_name,
            'company_reference' => $this->company_reference,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ]),

            // Creator info
            'created_by_id' => $this->created_by_id,
            'created_by_name' => $this->created_by_name,

            // Pricing
            'current_price' => (float) $this->current_price,

            // Stock counts
            'item_entry_total' => $this->item_entry_total,
            'item_exit_total' => $this->item_exit_total,
            'current_stock' => $this->current_stock,
            'stock_status' => $this->stock_status,
            'stock_status_color' => $this->stock_status_color,

            // Relation counts
            'material_count' => $this->material_count,
            'movement_count' => $this->item_entry_count + $this->item_exit_count,

            // Alerts
            'number_warning_enabled' => $this->number_warning_enabled,
            'number_warning_minimum' => $this->number_warning_minimum,
            'number_critical_enabled' => $this->number_critical_enabled,
            'number_critical_minimum' => $this->number_critical_minimum,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
