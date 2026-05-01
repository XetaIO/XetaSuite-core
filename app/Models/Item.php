<?php

declare(strict_types=1);

namespace XetaSuite\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Xetaio\Counts\Concerns\HasCounts;
use XetaSuite\Models\Concerns\HasStockQueries;
use XetaSuite\Models\Concerns\SiteScoped;
use XetaSuite\Models\Presenters\ItemPresenter;
use XetaSuite\Observers\ItemObserver;

#[ObservedBy([ItemObserver::class])]
class Item extends Model
{
    use HasCounts;
    use HasFactory;
    use HasStockQueries;
    use ItemPresenter;
    use SiteScoped;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'site_id',
        'created_by_id',
        'created_by_name',
        'company_id',
        'company_name',
        'company_reference',
        'edited_by_id',
        'name',
        'description',
        'reference',
        'current_price',
        'number_warning_enabled',
        'number_warning_minimum',
        'number_critical_enabled',
        'number_critical_minimum',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'current_price' => 'decimal:2',
        'number_warning_enabled' => 'boolean',
        'number_warning_minimum' => 'integer',
        'number_critical_enabled' => 'boolean',
        'number_critical_minimum' => 'integer',
    ];

    /**
     * The relations to be counted.
     */
    protected static array $countsConfig = [
        'company' => 'item_count',
        'creator' => 'item_count',
    ];

    /**
     * Get the site that owns the item.
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get the company (item provider) that supplies the item.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The materials that belong to the item.
     */
    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class)
            ->using(ItemMaterial::class)
            ->withTimestamps();
    }

    /**
     * Get the item movements for the item.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(ItemMovement::class)
            ->orderBy('movement_date', 'desc');
    }

    /**
     * Get the item prices for the item.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ItemPrice::class)
            ->orderBy('effective_date', 'desc');
    }

    /**
     * Get the recipients for the item.
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'item_user')
            ->withTimestamps();
    }

    /**
     * The creator of the item.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * The editor of the item.
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_id');
    }
}
