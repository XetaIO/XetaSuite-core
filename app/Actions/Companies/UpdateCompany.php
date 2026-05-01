<?php

declare(strict_types=1);

namespace XetaSuite\Actions\Companies;

use XetaSuite\Enums\Companies\CompanyType;
use XetaSuite\Models\Company;

class UpdateCompany
{
    /**
     * Update an existing company.
     *
     * @param  Company  $company  The company to update.
     * @param  array  $data  The data to update the company with.
     */
    public function handle(Company $company, array $data): Company
    {
        $payload = collect($data)
            ->only(['name', 'description', 'types', 'email', 'phone', 'address'])
            ->all();

        if (array_key_exists('types', $payload)) {
            $payload['types'] = collect($payload['types'] ?? [])
                ->filter(fn (string $type) => in_array($type, CompanyType::values(), true))
                ->values()
                ->all();
        }

        $company->update($payload);

        return $company->fresh();
    }
}
