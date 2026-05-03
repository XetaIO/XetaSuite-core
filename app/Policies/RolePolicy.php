<?php

declare(strict_types=1);

namespace XetaSuite\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use XetaSuite\Models\Role;
use XetaSuite\Models\User;
use XetaSuite\Policies\Concerns\RestrictsToHeadquarters;

class RolePolicy
{
    use HandlesAuthorization;
    use RestrictsToHeadquarters;

    /**
     * Determine whether the user can view the list of roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('role.viewAny');
    }

    /**
     * Determine whether the user can view the role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('role.view');
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can('role.create');
    }

    /**
     * Determine whether the user can update the role.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('role.update');
    }

    /**
     * Determine whether the user can delete the role.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->can('role.delete');
    }
}
