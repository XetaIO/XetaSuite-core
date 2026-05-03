<?php

declare(strict_types=1);

namespace XetaSuite\Actions\Items;

use Illuminate\Support\Facades\DB;
use XetaSuite\Actions\ItemPrices\CreateItemPrice;
use XetaSuite\Models\Company;
use XetaSuite\Models\Item;
use XetaSuite\Models\User;

class UpdateItem
{
    public function __construct(private readonly SyncItemRelationships $syncRelationships)
    {
    }

    /**
     * Update an existing item with new data.
     *
     * @param  Item  $item  The item to be updated.
     * @param  User  $user  The user performing the update.
     * @param  array  $data  The data to update the item with.
     */
    public function handle(Item $item, User $user, array $data): Item
    {
        return DB::transaction(function () use ($item, $user, $data) {
            $oldPrice = (float) $item->current_price;
            $oldCompanyId = $item->company_id;
            $newPrice = isset($data['current_price']) ? (float) $data['current_price'] : $oldPrice;
            $newCompanyId = $data['company_id'] ?? $oldCompanyId;
            // Check if price has changed
            $priceChanged = abs($newPrice - $oldPrice) > 0.001;
            $companyChanged = $newCompanyId !== $oldCompanyId;

            $item->update([
                'edited_by_id' => $user->id,
                'name' => $data['name'] ?? $item->name,
                'reference' => array_key_exists('reference', $data) ? $data['reference'] : $item->reference,
                'description' => array_key_exists('description', $data) ? $data['description'] : $item->description,
                'company_id' => $newCompanyId,
                'company_reference' => array_key_exists('company_reference', $data) ? $data['company_reference'] : $item->company_reference,
                'current_price' => $newPrice,
                'number_warning_enabled' => $data['number_warning_enabled'] ?? $item->number_warning_enabled,
                'number_warning_minimum' => $data['number_warning_minimum'] ?? $item->number_warning_minimum,
                'number_critical_enabled' => $data['number_critical_enabled'] ?? $item->number_critical_enabled,
                'number_critical_minimum' => $data['number_critical_minimum'] ?? $item->number_critical_minimum,
            ]);

            // Sync materials and recipients if provided
            $this->syncRelationships->handle($item, $data, isCreating: false);

            // Record price change in history via queue if price or company changed
            if ($priceChanged || $companyChanged) {
                $data['notes'] = $priceChanged ? __('items.price_updated') : __('items.company_changed');
                $data['company'] = $newCompanyId ? Company::find($newCompanyId) : null;
                $data['current_price'] = $newPrice;

                app(CreateItemPrice::class)->handle($item, $user, $data);
            }

            return $item->fresh();
        });
    }
}
