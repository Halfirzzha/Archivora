<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUuids, SoftDeletes, LogsActivity;

    /**
     * Professional mid-max: UUID primary key configuration
     */
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Professional mid-max: Use $guarded instead of $fillable for better security
     * Only block sensitive fields, allow all others (cleaner code)
     */
    protected $guarded = ['id', 'remember_token'];

    /**
     * Boot model events.
     * Auto-assign role='developer' for Filament make:filament-user command.
     * Auto-update last_seen realtime on any model interaction.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            // Filament make:filament-user doesn't prompt for role field
            // Auto-set 'developer' if role is empty (command context)
            if (empty($user->role)) {
                $user->role = 'developer';
            }
        });

        // Observer: Auto-update last_seen realtime on model updates
        static::updating(function (User $user): void {
            // Professional realtime tracking: Always update last_seen when user data changes
            // Skip only for internal batch operations (migrations, seeders)
            if (!app()->runningInConsole()) {
                $user->last_seen_at = now();
                $user->last_seen_ip = request()->ip();
            }
        });

        // Observer: Log profile updates for audit trail
        static::updated(function (User $user): void {
            // Skip logging for internal system updates (login IP tracking, etc.)
            if ($user->wasChanged(['last_login_ip', 'last_seen_ip', 'last_seen_at', 'failed_login_attempts', 'locked_at'])) {
                return;
            }

            // Get changed attributes (excluding timestamps)
            $changes = $user->getChanges();
            unset($changes['updated_at']);

            if (!empty($changes)) {
                $oldValues = [];
                $newValues = [];

                foreach (array_keys($changes) as $key) {
                    $oldValues[$key] = $user->getOriginal($key);
                    $newValues[$key] = $user->getAttribute($key);
                }

                // Log to user_login_activities with changes diff
                $user->loginActivities()->create([
                    'event' => 'profile_update',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'changes' => [
                        'old' => $oldValues,
                        'new' => $newValues,
                    ],
                ]);
            }
        });
    }

    /**
     * Filament panel access control (FilamentUser interface requirement)
     * Professional mid-max: Role-based access with account status validation
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Security Check 1: Account must be active
        if ($this->account_status !== 'active') {
            return false;
        }

        // Security Check 2: Account must not be locked or blocked
        if ($this->isLocked() || $this->isBlocked()) {
            return false;
        }

        // Role-based access: Only admin, developer, or authorized roles
        // For 'core-gateway' panel - allow all active users with valid account
        if ($panel->getId() === 'core-gateway') {
            return in_array($this->role, ['user', 'admin', 'developer', 'moderator'], true);
        }

        // Default: deny access to unknown panels
        return false;
    }

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     * Professional mid-max: Strict type casting with PHP 8.5+ support
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'phone_number' => 'encrypted', // Encrypt sensitive phone data (Laravel 12+)
            'last_seen_at' => 'datetime',
            'last_failed_login_at' => 'datetime',
            'locked_at' => 'datetime',
            'blocked_until' => 'datetime',
            'failed_login_attempts' => 'integer',
            'blocked_by' => 'string', // UUID string, not integer
        ];
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get all archives uploaded by this user
     */
    public function uploads(): HasMany
    {
        return $this->hasMany(Archive::class, 'uploaded_by');
    }

    /**
     * Get login activities for this user
     */
    public function loginActivities(): HasMany
    {
        return $this->hasMany(UserLoginActivity::class, 'user_id');
    }

    /**
     * Get the admin who blocked this user
     */
    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /**
     * Get users blocked by this admin
     */
    public function blockedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'blocked_by');
    }

    // ==================== SECURITY METHODS ====================

    /**
     * Check if account is active and not blocked/locked
     */
    public function isActive(): bool
    {
        return $this->account_status === 'active'
            && !$this->isLocked()
            && !$this->isBlocked();
    }

    /**
     * Check if account is temporarily locked due to failed login attempts
     */
    public function isLocked(): bool
    {
        if (!$this->locked_at) {
            return false;
        }

        // Auto-unlock after 30 minutes
        if ($this->locked_at->diffInMinutes(now()) >= 30) {
            $this->unlock();
            return false;
        }

        return true;
    }

    /**
     * Check if account is blocked (temporary or permanent)
     */
    public function isBlocked(): bool
    {
        if (!$this->blocked_until) {
            return in_array($this->account_status, ['blocked', 'suspended', 'terminated']);
        }

        // Check if block period has expired
        if (now()->isAfter($this->blocked_until)) {
            $this->unblock();
            return false;
        }

        return true;
    }

    /**
     * Lock account temporarily after failed login attempts
     */
    public function lock(): void
    {
        $this->update([
            'locked_at' => now(),
            'failed_login_attempts' => 0, // Reset counter after lock
        ]);

        // Log the lock event
        $this->logActivity('account_locked', [
            'ip_address' => request()->ip(),
            'reason' => 'Too many failed login attempts',
        ]);
    }

    /**
     * Unlock account (auto-unlock after timeout)
     */
    public function unlock(): void
    {
        $this->update([
            'locked_at' => null,
            'failed_login_attempts' => 0,
        ]);
    }

    /**
     * Block account (by admin)
     */
    public function block(?string $reason = null, ?Carbon $until = null, ?string $blockedBy = null): void
    {
        $this->update([
            'account_status' => 'blocked',
            'blocked_until' => $until,
            'blocked_reason' => $reason,
            'blocked_by' => $blockedBy, // UUID string
        ]);

        $this->logActivity('account_blocked', [
            'reason' => $reason,
            'blocked_by' => $blockedBy,
            'blocked_until' => $until?->toDateTimeString(),
        ]);
    }

    /**
     * Unblock account
     */
    public function unblock(): void
    {
        $this->update([
            'account_status' => 'active',
            'blocked_until' => null,
            'blocked_reason' => null,
            'blocked_by' => null,
        ]);

        $this->logActivity('account_unblocked');
    }

    /**
     * Increment failed login attempts
     */
    public function incrementFailedLoginAttempts(): void
    {
        $attempts = $this->failed_login_attempts + 1;

        $this->update([
            'failed_login_attempts' => $attempts,
            'last_failed_login_at' => now(),
            'last_failed_login_ip' => request()->ip(),
        ]);

        // Lock account after 5 failed attempts
        if ($attempts >= 5) {
            $this->lock();
        }

        $this->logActivity('login_failure', [
            'ip_address' => request()->ip(),
            'attempts' => $attempts,
        ]);
    }

    /**
     * Reset failed login attempts (after successful login)
     */
    public function resetFailedLoginAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'last_failed_login_at' => null,
            'last_failed_login_ip' => null,
        ]);
    }

    /**
     * Update last login information
     */
    public function updateLastLogin(): void
    {
        $this->update([
            'last_login_ip' => request()->ip(),
            'last_seen_ip' => request()->ip(),
        ]);

        $this->logActivity('login_success', [
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Log activity to user_login_activities table
     * Senior Professional: Prevents duplicates, adds session/request tracking, complete audit trail
     *
     * @param string $event Event name (login_success, logout, profile_update, etc.)
     * @param array $properties Optional: context, changes, identity, ip_address, etc.
     * @return void
     */
    public function logActivity(string $event, array $properties = []): void
    {
        // Duplicate prevention: Check if same event logged within last 2 seconds
        $recentLog = $this->loginActivities()
            ->where('event', $event)
            ->where('created_at', '>=', now()->subSeconds(2))
            ->first();

        if ($recentLog) {
            // Skip logging duplicate (already logged within 2 seconds)
            return;
        }

        // Professional activity logging with complete audit trail
        $this->loginActivities()->create([
            'event' => $event,
            'identity' => $properties['identity'] ?? $this->email ?? $this->username ?? null,
            'ip_address' => $properties['ip_address'] ?? request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'context' => $properties['context'] ?? null,
            'changes' => $properties['changes'] ?? null,
        ]);
    }

    // ==================== AUTHORIZATION METHODS ====================

    /**
     * Check if user has admin role
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    // ==================== AUTHENTICATION METHODS ====================

    /**
     * Find user by credentials (username OR email) for authentication
     * Mid-master professional: Flexible login identifier
     *
     * @param  string  $identifier  Can be username or email
     * @return User|null
     */
    public static function findForAuth(string $identifier): ?self
    {
        return static::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();
    }
}
