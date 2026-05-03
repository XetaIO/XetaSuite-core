<?php

declare(strict_types=1);

namespace XetaSuite\Policies\Concerns;

use XetaSuite\Models\User;

/**
 * Blocks create/update/delete abilities for users outside the headquarters site.
 *
 * View abilities remain delegated to the regular policy methods.
 * Used by policies governing HQ-managed resources (companies, settings).
 */
trait RestrictsCrudToHeadquarters
{
    public function before(User $user, string $ability): ?bool
    {
        if (! isOnHeadquarters() && in_array($ability, ['create', 'update', 'delete'], true)) {
            return false;
        }

        return null;
    }
}
