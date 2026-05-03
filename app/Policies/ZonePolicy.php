<?php

declare(strict_types=1);

namespace XetaSuite\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use XetaSuite\Models\User;
use XetaSuite\Models\Zone;
use XetaSuite\Policies\Concerns\RestrictsCrudToRegularSites;

class ZonePolicy
{
    use HandlesAuthorization;
    use RestrictsCrudToRegularSites;

    protected string $hqViewPermission = 'zone.view';

    /**
     * Determine whether the user can view the list of zones.
     * Used for accessing the zones table/index page.
     * Zones are filtered by current_site_id in the controller.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('zone.viewAny');
    }

    /**
     * Determine whether the user can view a zone.
     * User must be on the same site as the zone.
     */
    public function view(User $user, Zone $zone): bool
    {
        return $user->can('zone.view') && $zone->site_id === $user->current_site_id;
    }

    /**
     * Determine whether the user can create zones.
     */
    public function create(User $user): bool
    {
        return $user->can('zone.create');
    }

    /**
     * Determine whether the user can update the zone.
     * User must be on the same site as the zone.
     */
    public function update(User $user, Zone $zone): bool
    {
        return $user->can('zone.update') && $zone->site_id === $user->current_site_id;
    }

    /**
     * Determine whether the user can delete the zone.
     * User must be on the same site as the zone.
     */
    public function delete(User $user, Zone $zone): bool
    {
        return $user->can('zone.delete') && $zone->site_id === $user->current_site_id;
    }
}
