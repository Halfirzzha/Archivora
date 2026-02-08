<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginActivity extends Model
{
    /**
     * Disable updated_at column (only created_at needed)
     */
    public $timestamps = false;

    /**
     * Professional mid-max: Use $guarded instead of $fillable for cleaner code
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     * Professional mid-max: Cast JSON fields to arrays for easy access
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'string',
            'created_at' => 'datetime',
            'context' => 'array',
            'changes' => 'array',
        ];
    }

    /**
     * Get the user that owns this login activity
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for specific event types
     */
    public function scopeEvent($query, string $event): void
    {
        $query->where('event', $event);
    }

    /**
     * Scope for failed login attempts
     */
    public function scopeFailedLogins($query): void
    {
        $query->where('event', 'login_failure');
    }

    /**
     * Scope for successful logins
     */
    public function scopeSuccessfulLogins($query): void
    {
        $query->where('event', 'login_success');
    }

    /**
     * Scope for suspicious activities (multiple IPs)
     */
    public function scopeSuspiciousActivity($query, int $userId, int $hours = 24): void
    {
        $query->where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours($hours))
            ->distinct('ip_address')
            ->havingRaw('COUNT(DISTINCT ip_address) > 3');
    }
}
