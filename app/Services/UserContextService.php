<?php

declare(strict_types=1);

namespace XetaSuite\Services;

use XetaSuite\Models\Site;

class UserContextService
{
    private ?Site $cachedSite = null;

    private ?int $cachedSiteId = -1;

    /**
     * Get the currently selected site id (from session).
     */
    public function currentSiteId(): ?int
    {
        if ($this->cachedSiteId === -1) {
            $value = session('current_site_id');
            $this->cachedSiteId = $value !== null ? (int) $value : null;
        }

        return $this->cachedSiteId;
    }

    /**
     * Whether the current request is on the headquarters site.
     */
    public function isOnHeadquarters(): bool
    {
        return (bool) session('is_on_headquarters', false);
    }

    /**
     * Resolve the current Site model (cached per-request).
     */
    public function currentSite(): ?Site
    {
        if ($this->cachedSite !== null) {
            return $this->cachedSite;
        }

        $siteId = $this->currentSiteId();

        if ($siteId === null) {
            return null;
        }

        return $this->cachedSite = Site::query()->find($siteId);
    }

    /**
     * Reset cached values. Mainly useful in tests when session is mutated mid-request.
     */
    public function flush(): void
    {
        $this->cachedSite = null;
        $this->cachedSiteId = -1;
    }
}
