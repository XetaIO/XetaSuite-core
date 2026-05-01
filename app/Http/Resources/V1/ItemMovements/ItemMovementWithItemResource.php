<?php

declare(strict_types=1);

namespace XetaSuite\Http\Resources\V1\ItemMovements;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use XetaSuite\Http\Resources\V1\Concerns\FormatsRelations;

class ItemMovementWithItemResource extends JsonResource
{
    use FormatsRelations;
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price ? (float) $this->unit_price : null,
            'total_price' => $this->total_price ? (float) $this->total_price : null,

            // Item info
            'item' => $this->whenLoaded('item', fn () => [
                'id' => $this->item->id,
                'name' => $this->item->name,
                'reference' => $this->item->reference,
                'current_stock' => $this->item->current_stock,
                'site' => $this->when(
                    $this->item && $this->item->relationLoaded('site'),
                    fn () => [
                        'id' => $this->item->site->id,
                        'name' => $this->item->site->name,
                    ]
                ),
            ]),

            // Company info
            'company_id' => $this->company_id,
            'company_name' => $this->company_name,
            'company_invoice_number' => $this->company_invoice_number,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ]),
            'invoice_date' => $this->invoice_date?->toDateString(),

            // Creator info
            'created_by_id' => $this->created_by_id,
            'created_by_name' => $this->creator?->full_name ?? $this->created_by_name,
            'creator' => $this->userRelation('creator'),

            // Related entity (maintenance, etc.)
            'movable_type' => $this->movable_type,
            'movable_id' => $this->movable_id,

            'notes' => $this->notes,
            'movement_date' => $this->movement_date?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
