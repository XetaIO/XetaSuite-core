<?php

declare(strict_types=1);

namespace XetaSuite\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use XetaSuite\Models\Incident;
use XetaSuite\Models\User;
use XetaSuite\Policies\Concerns\RestrictsCrudToRegularSites;

class IncidentPolicy
{
    use HandlesAuthorization;
    use RestrictsCrudToRegularSites;

    protected string $hqViewPermission = 'incident.view';

    /**
     * Determine whether the user can view the list of incidents.
     * Incidents are filtered by current_site_id in the controller.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('incident.viewAny');
    }

    /**
     * Determine whether the user can view an incident.
     * User must be on the same site as the incident.
     */
    public function view(User $user, Incident $incident): bool
    {
        return $user->can('incident.view') && $incident->site_id === $user->current_site_id;
    }

    /**
     * Determine whether the user can create incidents.
     */
    public function create(User $user): bool
    {
        return $user->can('incident.create');
    }

    /**
     * Determine whether the user can update the incident.
     * User must be on the same site as the incident.
     */
    public function update(User $user, Incident $incident): bool
    {
        return $user->can('incident.update') && $incident->site_id === $user->current_site_id;
    }

    /**
     * Determine whether the user can delete the incident.
     * User must be on the same site as the incident.
     */
    public function delete(User $user, Incident $incident): bool
    {
        return $user->can('incident.delete') && $incident->site_id === $user->current_site_id;
    }
}
