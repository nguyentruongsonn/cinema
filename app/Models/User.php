<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /** @var array<int, string>|null */
    private ?array $resolvedPermissionSlugs = null;

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
        'account_status',
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
        'system_key',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'system_key',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'birthday' => 'date',
        'status' => 'boolean',
    ];

    protected $with = ['role']; // Always eager load role for auth checks

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return HasMany<Order, $this> */
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

    public function loyaltyHistories(): HasMany
    {
        return $this->hasMany(LoyaltyHistory::class);
    }

    /** @return BelongsToMany<Promotion, $this> */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'user_promotion')
            ->withPivot(['status', 'used_at', 'order_id', 'usage_count'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Theater, $this> */
    public function theaters(): BelongsToMany
    {
        return $this->belongsToMany(Theater::class, 'theater_user')
            ->withTimestamps();
    }

    public function isAssignedToTheater(int $theaterId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->theaters()->whereKey($theaterId)->exists();
    }

    public function requiresTheaterScope(): bool
    {
        return $this->hasAnyRole([
            'theater_manager',
            'ticket_seller',
            'ticket_checker',
            'concession_staff',
        ]);
    }

    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    public function isSystemGuest(): bool
    {
        return $this->account_status === 'system_guest'
            || str_starts_with((string) $this->system_key, 'pos_guest:');
    }

    public function canAccessAdminPanel(): bool
    {
        if (! $this->role) {
            return false;
        }

        if ($this->isCustomer()) {
            return false;
        }

        if ($this->hasRole('super-admin')) {
            return true;
        }

        return array_key_exists($this->role->slug, config('rbac.roles', []));
    }

    public function adminLandingRouteName(): string
    {
        if ($this->hasRole('ticket_seller')
            && $this->hasAnyPermission(['orders.create', 'booking.create_order'])) {
            return 'pos.index';
        }

        if ($this->hasRole('ticket_checker') && $this->hasPermission('tickets.verify')) {
            return 'staff.ticket-check';
        }

        if ($this->hasRole('concession_staff') && $this->hasPermission('concessions.fulfill')) {
            return 'staff.concessions';
        }

        $routesByPermission = [
            'dashboard.view' => 'admin.dashboard',
            'reports.view' => 'admin.revenue.index',
            'orders.view_all' => 'admin.orders.index',
            'orders.view_theater' => 'admin.orders.index',
            'tickets.view' => 'admin.tickets.index',
            'tickets.verify' => 'admin.tickets.index',
            'showtimes.view' => 'admin.showtimes.index',
            'movies.view' => 'admin.movies.index',
            'products.view' => 'admin.products.index',
            'combos.view' => 'admin.combos.index',
            'promotions.view' => 'admin.promotions.index',
            'theaters.view' => 'admin.theaters.index',
            'screens.view' => 'admin.screens.index',
            'users.view' => 'admin.users.index',
            'posts.view' => 'admin.posts.index',
            'banners.view' => 'admin.banners.index',
        ];

        foreach ($routesByPermission as $permission => $routeName) {
            if ($this->hasPermission($permission)) {
                return $routeName;
            }
        }

        return 'home';
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
        return $this->hasAnyRole(['admin', 'super-admin']);
    }

    // Helper: check permission via role
    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->hasAnyPermission([$permissionSlug]);
    }

    /**
     * Check whether the user has any supplied permissions using a single
     * cached permission lookup for the current model instance.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    public function hasAnyPermission(array $permissionSlugs): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $grantedPermissions = $this->resolvedPermissionSlugs();

        foreach ($permissionSlugs as $permissionSlug) {
            if (array_intersect($this->permissionSlugCandidates($permissionSlug), $grantedPermissions) !== []) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    private function resolvedPermissionSlugs(): array
    {
        if ($this->resolvedPermissionSlugs !== null) {
            return $this->resolvedPermissionSlugs;
        }

        $role = $this->role;

        if (! $role) {
            return $this->resolvedPermissionSlugs = [];
        }

        return $this->resolvedPermissionSlugs = $role->permissions()
            ->pluck('slug')
            ->all();
    }

    private function permissionSlugCandidates(string $permissionSlug): array
    {
        $aliases = config('rbac.permission_aliases', []);
        $candidates = [$permissionSlug];

        if (isset($aliases[$permissionSlug])) {
            $candidates[] = $aliases[$permissionSlug];
        }

        foreach (array_keys($aliases, $permissionSlug, true) as $legacySlug) {
            $candidates[] = $legacySlug;
        }

        return array_values(array_unique($candidates));
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
        $this->resolvedPermissionSlugs = null;
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

    public function markEmailAsVerified(): bool
    {
        $this->email_verified_at = now();

        return $this->save();
    }

    public function recordLogin(string $ip): void
    {
        $this->last_login_at = now();
        $this->last_login_ip = $ip;
        $this->save();
    }
}
