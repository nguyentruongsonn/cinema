<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * Mass assignable attributes - SECURITY: sensitive privilege fields removed.
     * Status remains fillable for controlled admin/service workflows and must not be accepted from public requests.
     * Do NOT add: role_id, loyalty_points, email_verified_at, last_login_*, remember_token
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'phone',
        'password',
        'avatar_url',
        'birthday',
        'gender',
        'address',
        'status',
    ];

    /**
     * Guarded attributes - prevent mass assignment
     */
    protected $guarded = [
        'id',
        'role_id',
        'loyalty_points',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'birthday' => 'date',
        'status' => 'boolean',
    ];

    protected $with = ['role']; // Always eager load role for auth checks

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function seatHolds(): HasMany
    {
        return $this->hasMany(SeatHold::class);
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'user_promotion')
            ->withPivot(['status', 'used_at', 'order_id', 'usage_count'])
            ->withTimestamps();
    }

    // JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // Helper: check role
    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    // Helper: check any of roles
    public function hasAnyRole(array $slugs): bool
    {
        return $this->role && in_array($this->role->slug, $slugs, true);
    }

    // Helper: check admin role
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    // Helper: check permission via role
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->role?->permissions()->where('slug', $permissionSlug)->exists() ?? false;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    // Explicit setters for guarded fields (admin/service use only)

    public function assignRole(int $roleId): void
    {
        $this->role_id = $roleId;
        $this->save();
    }

    public function updateLoyaltyPoints(int $points): void
    {
        $this->loyalty_points = $points;
        $this->save();
    }

    public function activate(): void
    {
        $this->status = true;
        $this->save();
    }

    public function deactivate(): void
    {
        $this->status = false;
        $this->save();
    }

    public function markEmailAsVerified(): void
    {
        $this->email_verified_at = now();
        $this->save();
    }

    public function recordLogin(string $ip): void
    {
        $this->last_login_at = now();
        $this->last_login_ip = $ip;
        $this->save();
    }
}
