<?php

declare(strict_types=1);

namespace XetaSuite\Policies\Concerns;

use XetaSuite\Models\User;

/**
 * Blocks create/update/delete abilities while on the headquarters site, and grants
 * view access to HQ users via the standard `<resource>.view` permission.
 *
 * Used by policies governing site-scoped resources that HQ may inspect but not edit
 * (zones, materials, items, cleanings, maintenances, incidents).
 *
 * Implementing classes must declare a `protected string $hqViewPermission` property
 * holding the permission name granted on HQ view (e.g. `zone.view`).
 */
trait RestrictsCrudToRegularSites
{
    public function before(User $user, string $ability): ?bool
    {
        if (isOnHeadquarters() && in_array($ability, ['create', 'update', 'delete'], true)) {
            return false;
        }

        if (isOnHeadquarters() && $ability === 'view') {
            return $user->can($this->hqViewPermission);
        }

        return null;
    }
}
