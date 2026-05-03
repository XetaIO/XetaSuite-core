<?php

declare(strict_types=1);

namespace XetaSuite\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use XetaSuite\Models\Setting;
use XetaSuite\Models\User;
use XetaSuite\Policies\Concerns\RestrictsCrudToHeadquarters;

class SettingPolicy
{
    use HandlesAuthorization;
    use RestrictsCrudToHeadquarters;

    /**
     * Determine whether the user can view the list of settings.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('setting.viewAny');
    }

    /**
     * Determine whether the user can view the setting.
     */
    public function view(User $user, Setting $setting): bool
    {
        return $user->can('setting.view');
    }

    /**
     * Determine whether the user can update the setting.
     */
    public function update(User $user, Setting $setting): bool
    {
        return $user->can('setting.update');
    }
}
