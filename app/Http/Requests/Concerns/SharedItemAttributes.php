<?php

declare(strict_types=1);

namespace XetaSuite\Http\Requests\Concerns;

/**
 * Provides the shared validation building blocks used by both
 * `StoreItemRequest` and `UpdateItemRequest`.
 *
 * Consumers must also use {@see SiteScopedRules}.
 */
trait SharedItemAttributes
{
    /**
     * Common (non-name/reference) item validation rules.
     *
     * @return array<string, mixed>
     */
    protected function commonItemRules(): array
    {
        return [
            'description' => 'nullable|string|max:2000',
            'company_id' => 'nullable|integer|exists:companies,id',
            'company_reference' => 'nullable|string|max:100',
            'current_price' => 'nullable|numeric|min:0|max:9999999.99',
            'number_warning_enabled' => 'boolean',
            'number_warning_minimum' => 'nullable|integer|min:0',
            'number_critical_enabled' => 'boolean',
            'number_critical_minimum' => 'nullable|integer|min:0',
            'material_ids' => 'nullable|array',
            'material_ids.*' => ['integer', $this->materialOnCurrentSiteRule()],
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => ['integer', $this->userOnCurrentSiteRule()],
        ];
    }

    /**
     * Translated attribute labels for item fields.
     *
     * @return array<string, string>
     */
    protected function itemAttributeLabels(): array
    {
        return [
            'name' => __('items.name'),
            'reference' => __('items.reference'),
            'description' => __('items.description'),
            'company_id' => __('items.company'),
            'company_reference' => __('items.company_reference'),
            'current_price' => __('items.current_price'),
            'number_warning_enabled' => __('items.number_warning_enabled'),
            'number_warning_minimum' => __('items.number_warning_minimum'),
            'number_critical_enabled' => __('items.number_critical_enabled'),
            'number_critical_minimum' => __('items.number_critical_minimum'),
            'material_ids' => __('items.materials'),
            'recipient_ids' => __('items.recipients'),
        ];
    }
}
