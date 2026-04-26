<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Mcp\Request;
use Spatie\Permission\PermissionRegistrar;
use XetaSuite\Models\Site;
use XetaSuite\Models\User;

/**
 * Resolves and activates the multi-tenant site context for MCP tools.
 *
 * Because MCP tools are called via Bearer token (no web session), the session
 * that normally carries `current_site_id` and `is_on_headquarters` is not populated
 * by the regular middleware chain. This trait replicates that logic so that
 * `forCurrentSite()`, `isOnHeadquarters()`, and Spatie permission scoping all work.
 */
trait ResolvesSiteContext
{
    /**
     * Resolve the site to operate on from the MCP request, then activate its context.
     *
     * Priority:
     *  1. `site_id` parameter from the MCP tool input
     *  2. `current_site_id` on the authenticated user
     *
     * Throws an AuthorizationException when:
     *  - No site can be resolved
     *  - The resolved site does not exist
     *  - The user does not belong to the resolved site
     *
     * @throws AuthorizationException
     */
    protected function resolveSiteId(Request $request): int
    {
        /** @var User $user */
        $user = $request->user();

        $siteId = $request->get('site_id')
            ? (int) $request->get('site_id')
            : (int) $user->current_site_id;

        if (! $siteId) {
            throw new AuthorizationException('No site context available. Provide a site_id parameter or set a current site on your account.');
        }

        $site = Site::find($siteId);

        if (! $site) {
            throw new AuthorizationException("Site {$siteId} not found.");
        }

        // Verify the user belongs to this site
        $userBelongsToSite = $user->sites()->where('sites.id', $siteId)->exists();

        if (! $userBelongsToSite) {
            throw new AuthorizationException("You do not have access to site {$siteId}.");
        }

        $this->activateSiteContext($siteId, $site->is_headquarters);

        // Temporarily set current_site_id on the user model for actions that read it directly
        $user->current_site_id = $siteId;

        return $siteId;
    }

    /**
     * Populate the session values and Spatie team context that the rest of the application
     * relies on (forCurrentSite scope, isOnHeadquarters helper, permission checks).
     */
    protected function activateSiteContext(int $siteId, bool $isHeadquarters): void
    {
        session([
            'current_site_id' => $siteId,
            'is_on_headquarters' => $isHeadquarters,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($siteId);
    }
}
