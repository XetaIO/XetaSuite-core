<?php

declare(strict_types=1);

namespace XetaSuite\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use XetaSuite\Models\Company;
use XetaSuite\Models\User;
use XetaSuite\Policies\Concerns\RestrictsCrudToHeadquarters;

class CompanyPolicy
{
    use HandlesAuthorization;
    use RestrictsCrudToHeadquarters;

    /**
     * Determine whether the user can view the list of companies.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('company.viewAny');
    }

    /**
     * Determine whether the user can view the company.
     */
    public function view(User $user, Company $company): bool
    {
        return $user->can('company.view');
    }

    /**
     * Determine whether the user can create companies.
     */
    public function create(User $user): bool
    {
        return $user->can('company.create');
    }

    /**
     * Determine whether the user can update the company.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->can('company.update');
    }

    /**
     * Determine whether the user can delete the company.
     */
    public function delete(User $user, Company $company): bool
    {
        return $user->can('company.delete');
    }
}
