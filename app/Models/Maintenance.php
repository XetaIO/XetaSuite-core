<?php

declare(strict_types=1);

namespace XetaSuite\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Xetaio\Counts\Concerns\HasCounts;
use XetaSuite\Enums\Maintenances\MaintenanceRealization;
use XetaSuite\Enums\Maintenances\MaintenanceStatus;
use XetaSuite\Enums\Maintenances\MaintenanceType;
use XetaSuite\Models\Concerns\SiteScoped;
use XetaSuite\Observers\MaintenanceObserver;

#[ObservedBy([MaintenanceObserver::class])]
class Maintenance extends Model
{
    use HasCounts;
    use HasFactory;
    use SiteScoped;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'site_id',
        'material_id',
        'material_name',
        'created_by_id',
        'created_by_name',
        'edited_by_id',
        'description',
        'type',
        'realization',
        'status',
        'started_at',
        'resolved_at',
        'incident_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'type' => MaintenanceType::class,
        'realization' => MaintenanceRealization::class,
        'status' => MaintenanceStatus::class,
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * The relations to be counted.
     */
    protected static array $countsConfig = [
        'material' => 'maintenance_count',
        'creator' => 'maintenance_count',
    ];

    /**
     * Get the site that owns the maintenance.
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get the material that owns the maintenance.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Get the incidents for the maintenance.
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * Get the user that created the maintenance.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get the user that edited the maintenance.
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_id');
    }

    /**
     * Get the operators involved in the maintenance.
     */
    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'maintenance_user')
            ->withTimestamps();
    }

    /**
     * Get the companies involved in the maintenance.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->using(CompanyMaintenance::class)
            ->withTimestamps();
    }

    /**
     * Maintenance-related item movements (outgoing items only).
     */
    public function itemMovements(): MorphMany
    {
        return $this->morphMany(ItemMovement::class, 'movable')
            ->where('type', 'exit');
    }
}
