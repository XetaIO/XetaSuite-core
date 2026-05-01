<?php

declare(strict_types=1);

namespace XetaSuite\Policies\Concerns;

use XetaSuite\Models\User;

/**
 * Blocks every ability for users currently outside the headquarters site.
 *
 * Used by policies governing globally-managed resources (sites, roles, permissions).
 */
trait RestrictsToHeadquarters
{
    public function before(User $user, string $ability): ?bool
    {
        if (! isOnHeadquarters()) {
            return false;
        }

        return null;
    }
}
