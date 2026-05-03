<?php

declare(strict_types=1);

namespace XetaSuite\Http\Requests\V1\Materials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use XetaSuite\Enums\Materials\CleaningFrequency;
use XetaSuite\Http\Requests\Concerns\SiteScopedRules;
use XetaSuite\Models\Material;
use XetaSuite\Models\User;

class UpdateMaterialRequest extends FormRequest
{
    use SiteScopedRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('material'));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        /** @var Material $material */
        // $material = $this->route('material');
        $currentSiteId = $this->currentSiteId();

        return [
            'zone_id' => [
                'sometimes',
                'required',
                'integer',
                $this->existsOnCurrentSite('zones')->where('allow_material', true),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cleaning_alert' => ['sometimes', 'boolean'],
            'cleaning_alert_email' => ['sometimes', 'boolean'],
            'cleaning_alert_frequency_repeatedly' => ['sometimes', 'integer', 'min:0'],
            'cleaning_alert_frequency_type' => [
                'nullable',
                Rule::enum(CleaningFrequency::class),
            ],
            'recipients' => ['sometimes', 'array'],
            'recipients.*' => [
                'integer',
                function ($attribute, $value, $fail) use ($currentSiteId): void {
                    $userHasAccess = User::query()
                        ->where('id', $value)
                        ->whereHas('sites', fn ($query) => $query->where('site_id', $currentSiteId))
                        ->exists();

                    if (! $userHasAccess) {
                        $fail(__('materials.validation.recipient_no_site_access'));
                    }
                },
            ],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'zone_id' => __('materials.zone'),
            'name' => __('materials.name'),
            'description' => __('materials.description'),
            'cleaning_alert' => __('materials.cleaning_alert'),
            'cleaning_alert_email' => __('materials.cleaning_alert_email'),
            'cleaning_alert_frequency_repeatedly' => __('materials.cleaning_alert_frequency_repeatedly'),
            'cleaning_alert_frequency_type' => __('materials.cleaning_alert_frequency_type'),
            'recipients' => __('materials.recipients'),
        ];
    }
}
