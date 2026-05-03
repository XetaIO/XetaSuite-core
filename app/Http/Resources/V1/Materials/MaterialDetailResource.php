<?php

declare(strict_types=1);

namespace XetaSuite\Http\Resources\V1\Materials;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use XetaSuite\Http\Resources\V1\Concerns\FormatsRelations;

class MaterialDetailResource extends JsonResource
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
            'description' => $this->description,
            'site_id' => $this->site_id,
            'site' => $this->siteRelation(),

            // Zone info
            'zone_id' => $this->zone_id,
            'zone' => $this->whenLoaded('zone', fn () => [
                'id' => $this->zone->id,
                'name' => $this->zone->name,
                'site_id' => $this->zone->site_id,
            ]),

            // Creator info
            'created_by_id' => $this->created_by_id,
            'created_by_name' => $this->created_by_name,
            'creator' => $this->userRelation('creator', includeEmail: true),

            // Cleaning alert configuration
            'cleaning_alert' => $this->cleaning_alert,
            'cleaning_alert_email' => $this->cleaning_alert_email,
            'cleaning_alert_frequency_repeatedly' => $this->cleaning_alert_frequency_repeatedly,
            'cleaning_alert_frequency_type' => $this->cleaning_alert_frequency_type?->value,
            'cleaning_alert_frequency_type_label' => $this->cleaning_alert_frequency_type?->label(),
            'last_cleaning_at' => $this->last_cleaning_at?->toISOString(),
            'last_cleaning_alert_send_at' => $this->last_cleaning_alert_send_at?->toISOString(),

            // Recipients for cleaning alerts
            'recipients' => $this->whenLoaded('recipients', fn () => $this->recipients->map(fn ($recipient) => [
                'id' => $recipient->id,
                'full_name' => $recipient->full_name,
                'email' => $recipient->email,
            ])),

            // Counts
            'incident_count' => $this->incident_count,
            'maintenance_count' => $this->maintenance_count,
            'cleaning_count' => $this->cleaning_count,
            'item_count' => $this->item_count,
            'qrcode_flash_count' => $this->qrcode_flash_count,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
