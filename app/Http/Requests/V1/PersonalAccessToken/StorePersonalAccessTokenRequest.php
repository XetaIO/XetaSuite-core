<?php

declare(strict_types=1);

namespace XetaSuite\Http\Requests\V1\PersonalAccessToken;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonalAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
