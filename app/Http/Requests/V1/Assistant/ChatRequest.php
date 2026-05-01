<?php

declare(strict_types=1);

namespace XetaSuite\Http\Requests\V1\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->current_site_id !== null
            && $user->can('assistant.use');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['sometimes', 'nullable', 'integer', 'exists:assistant_conversations,id'],
        ];
    }
}
