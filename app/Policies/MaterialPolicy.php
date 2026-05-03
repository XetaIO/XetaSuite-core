<?php

declare(strict_types=1);

namespace XetaSuite\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use XetaSuite\Models\Material;
use XetaSuite\Models\User;
use XetaSuite\Policies\Concerns\RestrictsCrudToRegularSites;

class MaterialPolicy
{
    use HandlesAuthorization;
    use RestrictsCrudToRegularSites;

    protected string $hqViewPermission = 'material.view';

    /**
     * Determine whether the user can view the list of materials.
     * Materials are filtered by current_site_id in the controller.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('material.viewAny');
    }

    /**
     * Determine whether the user can view a material.
     * User must be on the same site as the material's zone.
     */
    public function view(User $user, Material $material): bool
    {
        return $user->can('material.view')
            && $material->zone?->site_id === $user->current_site_id;
    }

    /**
     * Determine whether the user can create materials.
     */
    public function create(User $user): bool
    {
        return $user->can('material.create');
    }

    /**
     * Determine whether the user can update the material.
     * User must be on the same site as the material's zone.
     */
    public function update(User $user, Material $material): bool
    {
        return $user->can('material.update')
            && $material->zone?->site_id === $user->current_site_id;
    }

    /**
     * Determine whether the user can delete the material.
     * User must be on the same site as the material's zone.
     */
    public function delete(User $user, Material $material): bool
    {
        return $user->can('material.delete')
            && $material->zone?->site_id === $user->current_site_id;
    }

    /**
     * Determine whether the user can generate a QR code for the material.
     * User must be on the same site as the material's zone.
     */
    public function generateQrCode(User $user, Material $material): bool
    {
        return $user->can('material.generateQrCode')
            && $material->zone?->site_id === $user->current_site_id;
    }

    /**
     * Determine whether the user can scan QrCode for the model.
     */
    public function scanQrCode(User $user): bool
    {
        return $user->can('material.scanQrCode');
    }
}
