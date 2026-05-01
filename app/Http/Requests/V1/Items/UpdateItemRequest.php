<?php

declare(strict_types=1);

namespace XetaSuite\Http\Requests\V1\Items;

use Illuminate\Foundation\Http\FormRequest;
use XetaSuite\Http\Requests\Concerns\SharedItemAttributes;
use XetaSuite\Http\Requests\Concerns\SiteScopedRules;

class UpdateItemRequest extends FormRequest
{
    use SharedItemAttributes;
    use SiteScopedRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('item'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $itemId = $this->route('item')->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', $this->uniqueOnCurrentSite('items', $itemId)],
            'reference' => ['nullable', 'string', 'max:100', $this->uniqueOnCurrentSite('items', $itemId)],
            ...$this->commonItemRules(),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->itemAttributeLabels();
    }
}
