<?php

declare(strict_types=1);

namespace XetaSuite\Http\Requests\V1\Cleanings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use XetaSuite\Enums\Cleanings\CleaningType;
use XetaSuite\Http\Requests\Concerns\SiteScopedRules;
use XetaSuite\Models\Cleaning;

class StoreCleaningRequest extends FormRequest
{
    use SiteScopedRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Cleaning::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'material_id' => [
                'required',
                'integer',
                $this->existsOnCurrentSite('materials'),
            ],
            'description' => ['required', 'string', 'max:5000'],
            'type' => ['required', Rule::enum(CleaningType::class)],
        ];
    }
}
