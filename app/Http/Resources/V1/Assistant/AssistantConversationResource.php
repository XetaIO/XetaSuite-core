<?php

declare(strict_types=1);

namespace XetaSuite\Http\Resources\V1\Assistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistantConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'site_id' => $this->site_id,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'messages' => AssistantConversationMessageResource::collection(
                $this->whenLoaded('messages')
            ),
        ];
    }
}
