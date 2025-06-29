<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Carbon\Carbon;

// -------------------------------------------------------------------------------------------
// ATTENTION :
// -------------------------------------------------------------------------------------------
// the user attribute "is_developer" should be set directly in the database
// by the application developer on the one user account needed to manage the whole application
// including user management and managing all people in all teams
// -------------------------------------------------------------------------------------------

final class User extends Authenticatable
    // ---------------------------------------------------------------------------------------
    // class User extends Authenticatable implements MustVerifyEmail
    //
    // Ref : https://jetstream.laravel.com/features/registration.html#email-verification
    // ---------------------------------------------------------------------------------------
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'firstname',
        'surname',

        'email',
        'password',

        'language',
        'timezone',

        'seen_at',
    ];

    /**
     * The attributes that are not mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'is_developer',
    ];

    /**
     * Use the built-in $casts property for automatic casting.
     *
     * @var array<int, string>
     */
    protected $casts = [
        'email_verified_at'       => 'datetime',
        'password'                => 'hashed',
        'is_developer'            => 'boolean',
        'seen_at'                 => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'name',
        'profile_photo_url',
    ];

    /* -------------------------------------------------------------------------------------------- */
    // Log activities
    /* -------------------------------------------------------------------------------------------- */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user_team')
            ->setDescriptionForEvent(fn (string $eventName): string => __('user.user') . ' ' . __('app.event_' . $eventName))
            ->logOnly([
                'firstname',
                'surname',

                'email',

                'language',
                'timezone',

                'is_developer',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->team_id = auth()->user()?->currentTeam?->id ?? null;
    }

    /* -------------------------------------------------------------------------------------------- */
    // Permission System Methods
    /* -------------------------------------------------------------------------------------------- */
    
    /**
     * The permissions relationship.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
                    ->withPivot(['granted_at', 'granted_by', 'expires_at', 'justification'])
                    ->withTimestamps();
    }

    /**
     * Check if user has specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        // Check for active (non-expired) permission
        return $this->permissions()
            ->where('name', $permission)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Grant permission to user with full audit trail.
     */
    public function grantPermission(string $permissionName, User $grantedBy, ?string $justification = null, ?Carbon $expiresAt = null): void
    {
        $permission = Permission::where('name', $permissionName)->firstOrFail();
        
        // Check if permission already exists
        if ($this->hasPermission($permissionName)) {
            throw new \Exception("User already has permission: {$permissionName}");
        }
        
        // Grant the permission
        $this->permissions()->attach($permission->id, [
            'granted_at' => now(),
            'granted_by' => $grantedBy->id,
            'expires_at' => $expiresAt,
            'justification' => $justification,
        ]);
        
        // Log the action
        $this->logPermissionAction('granted', $permission, $grantedBy, $justification);
        
        // Activity log for broader system audit
        activity('permission')
            ->performedOn($this)
            ->by($grantedBy)
            ->withProperties([
                'permission' => $permissionName,
                'justification' => $justification,
                'expires_at' => $expiresAt,
                'ip_address' => request()->ip(),
            ])
            ->log("Permission '{$permissionName}' granted to user");
    }

    /**
     * Revoke permission from user.
     */
    public function revokePermission(string $permissionName, User $revokedBy, ?string $reason = null): void
    {
        $permission = Permission::where('name', $permissionName)->firstOrFail();
        
        if (!$this->hasPermission($permissionName)) {
            throw new \Exception("User does not have permission: {$permissionName}");
        }
        
        // Revoke the permission
        $this->permissions()->detach($permission->id);
        
        // Log the action
        $this->logPermissionAction('revoked', $permission, $revokedBy, $reason);
        
        // Activity log
        activity('permission')
            ->performedOn($this)
            ->by($revokedBy)
            ->withProperties([
                'permission' => $permissionName,
                'reason' => $reason,
                'ip_address' => request()->ip(),
            ])
            ->log("Permission '{$permissionName}' revoked from user");
    }

    /**
     * Log permission action to audit table.
     */
    private function logPermissionAction(string $action, Permission $permission, User $performedBy, ?string $context = null): void
    {
        DB::table('permission_audit_log')->insert([
            'user_id' => $this->id,
            'permission_id' => $permission->id,
            'action' => $action,
            'performed_by' => $performedBy->id,
            'context' => $context ? json_encode(['note' => $context]) : null,
            'performed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Backward compatibility: Check for is_developer flag or modern permissions.
     * This method will be deprecated once migration is complete.
     */
    public function hasLegacyDeveloperAccess(): bool
    {
        return $this->is_developer || $this->hasPermission('admin.developer.database');
    }

    public function teamsStatistics(): Collection
    {
        return collect(DB::select('
            SELECT
                `id`, `name`, `personal_team`,
                (SELECT COUNT(*) FROM `users` INNER JOIN `team_user` ON `users`.`id` = `team_user`.`user_id` WHERE `teams`.`id` = `team_user`.`team_id` AND `users`.`deleted_at` IS NULL) AS `users_count`,
                (SELECT COUNT(*) FROM `people` WHERE `teams`.`id` = `people`.`team_id` AND `people`.`deleted_at` IS NULL) AS `persons_count`,
                (SELECT COUNT(*) FROM `couples` WHERE `teams`.`id` = `couples`.`team_id`) AS `couples_count`
            FROM `teams` WHERE `user_id` = ' . $this->id . ' ORDER BY `name` ASC;
        '));
    }

    public function isDeletable(): bool
    {
        return $this->teamsStatistics()->sum(fn ($team): float|int|array => $team->users_count + $team->persons_count + $team->couples_count) === 0;
    }

    /* -------------------------------------------------------------------------------------------- */
    // Relations
    /* -------------------------------------------------------------------------------------------- */
    /* returns ALL USERLOGS (n Userlog) */
    public function userlogs(): HasMany
    {
        return $this->hasMany(Userlog::class);
    }

    /* -------------------------------------------------------------------------------------------- */
    // Accessors & Mutators
    /* -------------------------------------------------------------------------------------------- */
    protected function getNameAttribute(): ?string
    {
        return implode(' ', array_filter([$this->firstname, $this->surname]));
    }
}
