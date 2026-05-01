<?php

declare(strict_types=1);

namespace XetaSuite\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use XetaSuite\Models\Cleaning;
use XetaSuite\Models\User;
use XetaSuite\Policies\Concerns\RestrictsCrudToRegularSites;

class CleaningPolicy
{
    use HandlesAuthorization;
    use RestrictsCrudToRegularSites;

    protected string $hqViewPermission = 'cleaning.view';

    /**
     * Determine whether the user can view the list of cleanings.
     * Cleanings are filtered by current_site_id in the controller.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('cleaning.viewAny');
    }

    /**
     * Determine whether the user can view a cleaning.
     * User must be on the same site as the cleaning.
     */
    public function view(User $user, Cleaning $cleaning): bool
    {
        return $user->can('cleaning.view') && $cleaning->site_id === $user->current_site_id;
    }

    /**
     * Determine whether the user can create cleanings.
     */
    public function create(User $user): bool
    {
        return $user->can('cleaning.create');
    }

    /**
     * Determine whether the user can update the cleaning.
     * User must be on the same site as the cleaning.
     */
    public function update(User $user, Cleaning $cleaning): bool
    {
        return $user->can('cleaning.update') && $cleaning->site_id === $user->current_site_id;
    }

    /**
     * Determine whether the user can delete the cleaning.
     * User must be on the same site as the cleaning.
     */
    public function delete(User $user, Cleaning $cleaning): bool
    {
        return $user->can('cleaning.delete') && $cleaning->site_id === $user->current_site_id;
    }
}
