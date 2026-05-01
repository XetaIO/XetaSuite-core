<?php

declare(strict_types=1);

namespace XetaSuite\Http\Requests\V1\Maintenances;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use XetaSuite\Enums\Maintenances\MaintenanceRealization;
use XetaSuite\Enums\Maintenances\MaintenanceStatus;
use XetaSuite\Enums\Maintenances\MaintenanceType;
use XetaSuite\Http\Requests\Concerns\SiteScopedRules;
use XetaSuite\Models\User;

class UpdateMaintenanceRequest extends FormRequest
{
    use SiteScopedRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('maintenance'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            // Note: material_id cannot be changed after creation
            'description' => ['sometimes', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'type' => ['sometimes', Rule::enum(MaintenanceType::class)],
            'realization' => ['sometimes', Rule::enum(MaintenanceRealization::class)],
            'status' => ['sometimes', Rule::enum(MaintenanceStatus::class)],
            'started_at' => ['nullable', 'date'],
            'resolved_at' => ['nullable', 'date', 'after_or_equal:started_at'],

            // Relations
            'incident_ids' => ['sometimes', 'array'],
            'incident_ids.*' => [
                'integer',
                $this->existsOnCurrentSite('incidents'),
            ],

            'operator_ids' => ['sometimes', 'array'],
            'operator_ids.*' => ['integer', 'exists:users,id'],

            'company_ids' => ['sometimes', 'array'],
            'company_ids.*' => ['integer', 'exists:companies,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->validateRealizationRequirements($validator);
            $this->validateOperatorsOnSite($validator);
        });
    }

    /**
     * Validate that operators/companies are provided based on realization type.
     */
    private function validateRealizationRequirements($validator): void
    {
        $maintenance = $this->route('maintenance');
        $realization = $this->input('realization', $maintenance->realization->value);
        $operatorIds = $this->has('operator_ids') ? $this->input('operator_ids', []) : null;
        $companyIds = $this->has('company_ids') ? $this->input('company_ids', []) : null;

        // Only validate if realization or operators/companies are being updated
        if ($operatorIds !== null && in_array($realization, [MaintenanceRealization::INTERNAL->value, MaintenanceRealization::BOTH->value])) {
            if (empty($operatorIds)) {
                $validator->errors()->add('operator_ids', __('maintenances.validation.operators_required_for_internal'));
            }
        }

        if ($companyIds !== null && in_array($realization, [MaintenanceRealization::EXTERNAL->value, MaintenanceRealization::BOTH->value])) {
            if (empty($companyIds)) {
                $validator->errors()->add('company_ids', __('maintenances.validation.companies_required_for_external'));
            }
        }
    }

    /**
     * Validate that all operators have access to the current site.
     */
    private function validateOperatorsOnSite($validator): void
    {
        $operatorIds = $this->input('operator_ids', []);
        if (empty($operatorIds)) {
            return;
        }

        $currentSiteId = session('current_site_id');

        foreach ($operatorIds as $operatorId) {
            $user = User::find($operatorId);
            if ($user && ! $user->sites()->where('site_id', $currentSiteId)->exists()) {
                $validator->errors()->add('operator_ids', __('maintenances.validation.operator_not_on_site'));
                break;
            }
        }
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'description' => __('maintenances.description'),
            'reason' => __('maintenances.reason'),
            'type' => __('maintenances.type'),
            'realization' => __('maintenances.realization'),
            'status' => __('maintenances.status'),
            'started_at' => __('maintenances.started_at'),
            'resolved_at' => __('maintenances.resolved_at'),
            'incident_ids' => __('maintenances.incidents'),
            'operator_ids' => __('maintenances.operators'),
            'company_ids' => __('maintenances.companies'),
        ];
    }
}
