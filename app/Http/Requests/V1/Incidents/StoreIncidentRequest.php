<?php

declare(strict_types=1);

namespace XetaSuite\Http\Requests\V1\Incidents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use XetaSuite\Enums\Incidents\IncidentSeverity;
use XetaSuite\Http\Requests\Concerns\SiteScopedRules;
use XetaSuite\Models\Incident;

class StoreIncidentRequest extends FormRequest
{
    use SiteScopedRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Incident::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'material_id' => [
                'required',
                'integer',
                $this->existsOnCurrentSite('materials'),
            ],
            'maintenance_id' => [
                'nullable',
                'integer',
                $this->existsOnCurrentSite('maintenances'),
            ],
            'description' => ['required', 'string', 'max:5000'],
            'severity' => [
                'sometimes',
                Rule::enum(IncidentSeverity::class),
            ],
            'started_at' => ['nullable', 'date'],
            'resolved_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'material_id.required' => __('incidents.validation.material_required'),
            'material_id.exists' => __('incidents.validation.material_not_found'),
            'maintenance_id.exists' => __('incidents.validation.maintenance_not_found'),
            'description.required' => __('incidents.validation.description_required'),
            'resolved_at.after_or_equal' => __('incidents.validation.resolved_after_started'),
        ];
    }
}
