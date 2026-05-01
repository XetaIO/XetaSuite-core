<?php

declare(strict_types=1);

namespace XetaSuite\Http\Resources\V1\Assistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistantConversationMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
