<?php

declare(strict_types=1);

namespace XetaSuite\Models;

use BackedEnum;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;
use XetaSuite\Http\Controllers\Api\V1\Auth\Traits\MustSetupPassword;
use XetaSuite\Models\Presenters\UserPresenter;
use XetaSuite\Observers\UserObserver;

#[ObservedBy([UserObserver::class])]
class User extends Model implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract
{
    use Authenticatable;
    use Authorizable;
    use CanResetPassword;
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use MustSetupPassword;
    use Notifiable;
    use SoftDeletes;
    use UserPresenter;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username',
        'password',
        'first_name',
        'last_name',
        'email',
        'locale',
        'office_phone',
        'cell_phone',
        'current_site_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'password_setup_at' => 'datetime',
        'password' => 'hashed',
        'last_login_date' => 'datetime',
    ];

    /**
     * Get the cleanings created by the user.
     */
    public function cleanings(): HasMany
    {
        return $this->hasMany(Cleaning::class, 'created_by_id');
    }

    /**
     * Get the incidents reported by the user.
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'reported_by_id');
    }

    /**
     * Get the maintenances created by the user.
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'created_by_id');
    }

    /**
     * Get the maintenance's operators for the user.
     */
    public function maintenancesOperators(): BelongsToMany
    {
        return $this->belongsToMany(Maintenance::class)
            ->withTimestamps();
    }

    /**
     * Get the settings for the user.
     */
    public function settings(): MorphMany
    {
        return $this->morphMany(Setting::class, 'model');
    }

    /**
     * Get the sites for the user.
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class)
            ->withTimestamps();
    }

    /**
     * Get the user who deleted the user.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_id')->withTrashed();
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')
            ->orderByRaw('read_at IS NOT NULL')  // Unread (NULL) first
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get the first site ID for the user.
     */
    public function getFirstSiteId(): ?int
    {
        return $this->sites()->orderBy('id')->value('id');
    }

    /**
     * Function to assign the given roles to the given sites
     *
     *
     * @return $this
     */
    public function assignRolesToSites(BackedEnum|Collection|int|array|string|Role $roles, array|int $sites): self
    {
        if (! app(PermissionRegistrar::class)->teams) {
            return $this;
        }

        $sites = Arr::wrap($sites);
        $teamId = getPermissionsTeamId();

        foreach ($sites as $site) {
            setPermissionsTeamId($site);
            $this->assignRole($roles);
        }

        setPermissionsTeamId($teamId);

        return $this;
    }

    /**
     * Function to assign the given permissions to the given sites
     *
     *
     * @return $this
     */
    public function assignPermissionsToSites(BackedEnum|Collection|int|array|string|Permission $permissions, array|int $sites): self
    {
        if (! app(PermissionRegistrar::class)->teams) {
            return $this;
        }

        $sites = Arr::wrap($sites);
        $teamId = getPermissionsTeamId();

        foreach ($sites as $site) {
            setPermissionsTeamId($site);
            $this->givePermissionTo($permissions);
        }

        setPermissionsTeamId($teamId);

        return $this;
    }
}
