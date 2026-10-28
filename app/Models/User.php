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

    private ?array $resolvedPermissionSlugs = null;

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

    protected $with = ['role'];

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

    public function loyaltyHistories(): HasMany
    {
        return $this->hasMany(LoyaltyHistory::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'user_promotion')
            ->withPivot(['status', 'used_at', 'order_id', 'usage_count'])
            ->withTimestamps();
    }

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

    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->role && in_array($this->role->slug, $slugs, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->hasAnyPermission([$permissionSlug]);
    }

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
