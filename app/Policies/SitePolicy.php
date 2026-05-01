<?php

declare(strict_types=1);

namespace XetaSuite\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use XetaSuite\Models\Site;
use XetaSuite\Models\User;
use XetaSuite\Policies\Concerns\RestrictsToHeadquarters;

class SitePolicy
{
    use HandlesAuthorization;
    use RestrictsToHeadquarters;

    /**
     * Determine whether the user can view the list of sites.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('site.viewAny');
    }

    /**
     * Determine whether the user can view the site.
     */
    public function view(User $user, Site $site): bool
    {
        return $user->can('site.view');
    }

    /**
     * Determine whether the user can create sites.
     */
    public function create(User $user): bool
    {
        return $user->can('site.create');
    }

    /**
     * Determine whether the user can update the site.
     */
    public function update(User $user, Site $site): bool
    {
        return $user->can('site.update');
    }

    /**
     * Determine whether the user can delete the site.
     */
    public function delete(User $user, Site $site): bool
    {
        return $user->can('site.delete');
    }
}
