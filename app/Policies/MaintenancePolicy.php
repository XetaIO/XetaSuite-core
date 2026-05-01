<?php

declare(strict_types=1);

namespace XetaSuite\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\User;
use XetaSuite\Policies\Concerns\RestrictsCrudToRegularSites;

class MaintenancePolicy
{
    use HandlesAuthorization;
    use RestrictsCrudToRegularSites;

    protected string $hqViewPermission = 'maintenance.view';

    /**
     * Determine whether the user can view the list of maintenances.
     * Maintenances are filtered by current_site_id in the controller.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('maintenance.viewAny');
    }

    /**
     * Determine whether the user can view a maintenance.
     * User must be on the same site as the maintenance.
     */
    public function view(User $user, Maintenance $maintenance): bool
    {
        return $user->can('maintenance.view') && $maintenance->site_id === $user->current_site_id;
    }

    /**
     * Determine whether the user can create maintenances.
     */
    public function create(User $user): bool
    {
        return $user->can('maintenance.create');
    }

    /**
     * Determine whether the user can update the maintenance.
     * User must be on the same site as the maintenance.
     */
    public function update(User $user, Maintenance $maintenance): bool
    {
        return $user->can('maintenance.update') && $maintenance->site_id === $user->current_site_id;
    }

    /**
     * Determine whether the user can delete the maintenance.
     * User must be on the same site as the maintenance.
     */
    public function delete(User $user, Maintenance $maintenance): bool
    {
        return $user->can('maintenance.delete') && $maintenance->site_id === $user->current_site_id;
    }
}
