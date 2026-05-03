<?php

declare(strict_types=1);

namespace XetaSuite\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Helpers for building validation rules scoped to the user's current site.
 *
 * Centralizes patterns previously duplicated across Item, Material, Cleaning,
 * Incident, Maintenance and Zone form requests.
 */
trait SiteScopedRules
{
    /**
     * The id of the user's currently active site (per session).
     */
    protected function currentSiteId(): ?int
    {
        $value = session('current_site_id');

        return $value !== null ? (int) $value : null;
    }

    /**
     * `Rule::exists` constrained to a column equal to the current site id.
     */
    protected function existsOnCurrentSite(string $table, string $column = 'site_id'): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists($table, 'id')->where($column, $this->currentSiteId());
    }

    /**
     * `Rule::exists` for a material that lives in a zone of the current site.
     */
    protected function materialOnCurrentSiteRule(): \Illuminate\Validation\Rules\Exists
    {
        $siteId = $this->currentSiteId();

        return Rule::exists('materials', 'id')->where(function ($query) use ($siteId): void {
            $query->whereIn('zone_id', function ($subQuery) use ($siteId): void {
                $subQuery->select('id')->from('zones')->where('site_id', $siteId);
            });
        });
    }

    /**
     * `Rule::exists` for a user attached to the current site.
     */
    protected function userOnCurrentSiteRule(): \Illuminate\Validation\Rules\Exists
    {
        $siteId = $this->currentSiteId();

        return Rule::exists('users', 'id')->where(function ($query) use ($siteId): void {
            $query->whereIn('id', function ($subQuery) use ($siteId): void {
                $subQuery->select('user_id')->from('site_user')->where('site_id', $siteId);
            });
        });
    }

    /**
     * `Rule::unique` scoped to the current site, optionally ignoring a row id.
     */
    protected function uniqueOnCurrentSite(string $table, ?int $ignoreId = null): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique($table)->where('site_id', $this->currentSiteId());

        return $ignoreId !== null ? $rule->ignore($ignoreId) : $rule;
    }
}
