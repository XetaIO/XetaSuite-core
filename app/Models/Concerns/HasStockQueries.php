<?php

declare(strict_types=1);

namespace XetaSuite\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Centralizes raw stock formula `(item_entry_total - item_exit_total)` so it
 * is defined exactly once across the codebase.
 */
trait HasStockQueries
{
    /**
     * SQL expression for the current stock value.
     */
    public static function stockExpression(): string
    {
        return '(item_entry_total - item_exit_total)';
    }

    /**
     * Add a `current_stock_value` column computed from entries minus exits.
     */
    public function scopeWithCalculatedStock(Builder $query): Builder
    {
        return $query->selectRaw('*, '.self::stockExpression().' as current_stock_value');
    }

    /**
     * Filter rows whose stock is strictly below a threshold (column or integer).
     */
    public function scopeWhereStockBelow(Builder $query, string|int $threshold): Builder
    {
        return $query->whereRaw(self::stockExpression().' < '.(is_int($threshold) ? (string) $threshold : $threshold));
    }

    /**
     * Filter rows whose stock is less than or equal to a threshold.
     */
    public function scopeWhereStockAtMost(Builder $query, string|int $threshold): Builder
    {
        return $query->whereRaw(self::stockExpression().' <= '.(is_int($threshold) ? (string) $threshold : $threshold));
    }

    /**
     * Filter rows whose stock is strictly greater than a threshold.
     */
    public function scopeWhereStockAbove(Builder $query, string|int $threshold): Builder
    {
        return $query->whereRaw(self::stockExpression().' > '.(is_int($threshold) ? (string) $threshold : $threshold));
    }

    /**
     * Filter rows whose stock is below their critical threshold (and critical alert is enabled).
     */
    public function scopeWhereStockBelowCritical(Builder $query): Builder
    {
        return $query
            ->where('number_critical_enabled', true)
            ->whereRaw(self::stockExpression().' < number_critical_minimum');
    }

    /**
     * Filter rows whose stock is below their warning threshold but not critical.
     */
    public function scopeWhereStockBelowWarning(Builder $query): Builder
    {
        return $query
            ->where('number_warning_enabled', true)
            ->whereRaw(self::stockExpression().' < number_warning_minimum');
    }

    /**
     * Filter rows whose stock is empty (<= 0).
     */
    public function scopeWhereStockEmpty(Builder $query): Builder
    {
        return $query->whereRaw(self::stockExpression().' <= 0');
    }

    /**
     * Filter rows whose stock is strictly positive.
     */
    public function scopeWhereStockPositive(Builder $query): Builder
    {
        return $query->whereRaw(self::stockExpression().' > 0');
    }
}
