<?php

declare(strict_types=1);

namespace XetaSuite\Http\Requests\V1\Maintenances;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use XetaSuite\Enums\Maintenances\MaintenanceRealization;
use XetaSuite\Enums\Maintenances\MaintenanceStatus;
use XetaSuite\Enums\Maintenances\MaintenanceType;
use XetaSuite\Http\Requests\Concerns\SiteScopedRules;
use XetaSuite\Models\Item;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\User;

class StoreMaintenanceRequest extends FormRequest
{
    use SiteScopedRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Maintenance::class);
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
                'nullable',
                'integer',
                $this->existsOnCurrentSite('materials'),
            ],
            'description' => ['required', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:5000'],
            'type' => [Rule::enum(MaintenanceType::class)],
            'realization' => [Rule::enum(MaintenanceRealization::class)],
            'status' => [Rule::enum(MaintenanceStatus::class)],
            'started_at' => ['nullable', 'date'],
            'resolved_at' => ['nullable', 'date', 'after_or_equal:started_at'],

            // Relations
            'incident_ids' => ['nullable', 'array'],
            'incident_ids.*' => [
                'integer',
                $this->existsOnCurrentSite('incidents'),
            ],

            'operator_ids' => ['nullable', 'array'],
            'operator_ids.*' => ['integer', 'exists:users,id'],

            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', 'exists:companies,id'],

            // Spare parts
            'item_movements' => ['nullable', 'array'],
            'item_movements.*.item_id' => [
                'required_with:item_movements',
                'integer',
                $this->existsOnCurrentSite('items'),
            ],
            'item_movements.*.quantity' => ['required_with:item_movements', 'integer', 'min:1'],
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
            $this->validateSparePartsStock($validator);
        });
    }

    /**
     * Validate that operators/companies are provided based on realization type.
     */
    private function validateRealizationRequirements($validator): void
    {
        $realization = $this->input('realization', MaintenanceRealization::INTERNAL->value);
        $operatorIds = $this->input('operator_ids', []);
        $companyIds = $this->input('company_ids', []);

        if (in_array($realization, [MaintenanceRealization::INTERNAL->value, MaintenanceRealization::BOTH->value])) {
            if (empty($operatorIds)) {
                $validator->errors()->add('operator_ids', __('maintenances.validation.operators_required_for_internal'));
            }
        }

        if (in_array($realization, [MaintenanceRealization::EXTERNAL->value, MaintenanceRealization::BOTH->value])) {
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
     * Validate that spare parts have sufficient stock.
     */
    private function validateSparePartsStock($validator): void
    {
        $spareParts = $this->input('item_movements', []);
        if (empty($spareParts)) {
            return;
        }

        foreach ($spareParts as $index => $part) {
            $item = Item::find($part['item_id'] ?? null);
            if (! $item) {
                continue;
            }

            $quantity = (int) ($part['quantity'] ?? 0);
            if ($item->current_stock < $quantity) {
                $validator->errors()->add(
                    "item_movements.{$index}.quantity",
                    __('maintenances.validation.item_insufficient_stock', [
                        'name' => $item->name,
                        'stock' => $item->current_stock,
                        'requested' => $quantity,
                    ])
                );
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
            'material_id' => __('maintenances.material'),
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
            'item_movements' => __('maintenances.spare_parts'),
        ];
    }
}
