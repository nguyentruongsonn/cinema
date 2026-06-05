<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define all permissions with categories
        $permissions = [
            // User Management
            ['name' => 'View Users', 'slug' => 'view_users', 'group' => 'users'],
            ['name' => 'Create Users', 'slug' => 'create_users', 'group' => 'users'],
            ['name' => 'Edit Users', 'slug' => 'edit_users', 'group' => 'users'],
            ['name' => 'Delete Users', 'slug' => 'delete_users', 'group' => 'users'],
            ['name' => 'Manage User Roles', 'slug' => 'manage_user_roles', 'group' => 'users'],

            // Movie Management
            ['name' => 'View Movies', 'slug' => 'view_movies', 'group' => 'movies'],
            ['name' => 'Create Movies', 'slug' => 'create_movies', 'group' => 'movies'],
            ['name' => 'Edit Movies', 'slug' => 'edit_movies', 'group' => 'movies'],
            ['name' => 'Delete Movies', 'slug' => 'delete_movies', 'group' => 'movies'],
            ['name' => 'Manage Movie Categories', 'slug' => 'manage_movie_categories', 'group' => 'movies'],

            // Theater Management
            ['name' => 'View Theaters', 'slug' => 'view_theaters', 'group' => 'theaters'],
            ['name' => 'Create Theaters', 'slug' => 'create_theaters', 'group' => 'theaters'],
            ['name' => 'Edit Theaters', 'slug' => 'edit_theaters', 'group' => 'theaters'],
            ['name' => 'Delete Theaters', 'slug' => 'delete_theaters', 'group' => 'theaters'],

            // Screen Management
            ['name' => 'View Screens', 'slug' => 'view_screens', 'group' => 'screens'],
            ['name' => 'Create Screens', 'slug' => 'create_screens', 'group' => 'screens'],
            ['name' => 'Edit Screens', 'slug' => 'edit_screens', 'group' => 'screens'],
            ['name' => 'Delete Screens', 'slug' => 'delete_screens', 'group' => 'screens'],
            ['name' => 'Manage Seat Layouts', 'slug' => 'manage_seat_layouts', 'group' => 'screens'],

            // Showtime Management
            ['name' => 'View Showtimes', 'slug' => 'view_showtimes', 'group' => 'showtimes'],
            ['name' => 'Create Showtimes', 'slug' => 'create_showtimes', 'group' => 'showtimes'],
            ['name' => 'Edit Showtimes', 'slug' => 'edit_showtimes', 'group' => 'showtimes'],
            ['name' => 'Delete Showtimes', 'slug' => 'delete_showtimes', 'group' => 'showtimes'],

            // Order Management
            ['name' => 'View All Orders', 'slug' => 'view_all_orders', 'group' => 'orders'],
            ['name' => 'View Own Orders', 'slug' => 'view_own_orders', 'group' => 'orders'],
            ['name' => 'Create Orders', 'slug' => 'create_orders', 'group' => 'orders'],
            ['name' => 'Cancel Orders', 'slug' => 'cancel_orders', 'group' => 'orders'],
            ['name' => 'Refund Orders', 'slug' => 'refund_orders', 'group' => 'orders'],

            // Booking Management
            ['name' => 'Book Tickets', 'slug' => 'book_tickets', 'group' => 'booking'],
            ['name' => 'Hold Seats', 'slug' => 'hold_seats', 'group' => 'booking'],
            ['name' => 'Release Seats', 'slug' => 'release_seats', 'group' => 'booking'],

            // Payment Management
            ['name' => 'Process Payments', 'slug' => 'process_payments', 'group' => 'payments'],
            ['name' => 'View Payment Details', 'slug' => 'view_payment_details', 'group' => 'payments'],
            ['name' => 'Verify Payments', 'slug' => 'verify_payments', 'group' => 'payments'],

            // Promotion Management
            ['name' => 'View Promotions', 'slug' => 'view_promotions', 'group' => 'promotions'],
            ['name' => 'Create Promotions', 'slug' => 'create_promotions', 'group' => 'promotions'],
            ['name' => 'Edit Promotions', 'slug' => 'edit_promotions', 'group' => 'promotions'],
            ['name' => 'Delete Promotions', 'slug' => 'delete_promotions', 'group' => 'promotions'],
            ['name' => 'Apply Promotions', 'slug' => 'apply_promotions', 'group' => 'promotions'],

            // Report & Analytics
            ['name' => 'View Dashboard', 'slug' => 'view_dashboard', 'group' => 'analytics'],
            ['name' => 'View Reports', 'slug' => 'view_reports', 'group' => 'analytics'],
            ['name' => 'Export Reports', 'slug' => 'export_reports', 'group' => 'analytics'],

            // System Management
            ['name' => 'Manage Settings', 'slug' => 'manage_settings', 'group' => 'system'],
            ['name' => 'View Audit Logs', 'slug' => 'view_audit_logs', 'group' => 'system'],
            ['name' => 'Manage Roles & Permissions', 'slug' => 'manage_roles_permissions', 'group' => 'system'],
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'group' => $permission['group'],
                    'description' => null,
                ]
            );
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();

        $this->command->info('Permissions seeded successfully!');
    }

    /**
     * Assign permissions to roles based on their responsibilities.
     */
    private function assignPermissionsToRoles(): void
    {
        // Admin - Full access to everything
        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole) {
            $allPermissions = Permission::all()->pluck('id');
            $adminRole->permissions()->sync($allPermissions);
            $this->command->info('Admin role: ALL permissions assigned');
        }

        // Manager - Manage operations (movies, theaters, screens, showtimes, orders)
        $managerRole = Role::where('slug', 'manager')->first();
        if ($managerRole) {
            $managerPermissions = Permission::whereIn('slug', [
                // Movies
                'view_movies', 'create_movies', 'edit_movies', 'delete_movies', 'manage_movie_categories',
                // Theaters & Screens
                'view_theaters', 'create_theaters', 'edit_theaters', 'delete_theaters',
                'view_screens', 'create_screens', 'edit_screens', 'delete_screens', 'manage_seat_layouts',
                // Showtimes
                'view_showtimes', 'create_showtimes', 'edit_showtimes', 'delete_showtimes',
                // Orders
                'view_all_orders', 'cancel_orders', 'refund_orders',
                // Payments
                'view_payment_details', 'verify_payments',
                // Promotions
                'view_promotions', 'create_promotions', 'edit_promotions', 'delete_promotions',
                // Analytics
                'view_dashboard', 'view_reports', 'export_reports',
            ])->pluck('id');
            $managerRole->permissions()->sync($managerPermissions);
            $this->command->info('Manager role: ' . $managerPermissions->count() . ' permissions assigned');
        }

        // Staff - View and basic operations (check-in, view orders)
        $staffRole = Role::where('slug', 'staff')->first();
        if ($staffRole) {
            $staffPermissions = Permission::whereIn('slug', [
                // View only
                'view_movies', 'view_theaters', 'view_screens', 'view_showtimes',
                // Orders
                'view_all_orders', 'cancel_orders',
                // Payments
                'view_payment_details',
                // Booking
                'release_seats',
            ])->pluck('id');
            $staffRole->permissions()->sync($staffPermissions);
            $this->command->info('Staff role: ' . $staffPermissions->count() . ' permissions assigned');
        }

        // Customer - Basic user permissions (book tickets, view own orders)
        $customerRole = Role::where('slug', 'customer')->first();
        if ($customerRole) {
            $customerPermissions = Permission::whereIn('slug', [
                // Booking
                'book_tickets', 'hold_seats', 'release_seats',
                // Orders
                'view_own_orders', 'create_orders', 'cancel_orders',
                // Payments
                'process_payments',
                // Promotions
                'apply_promotions',
            ])->pluck('id');
            $customerRole->permissions()->sync($customerPermissions);
            $this->command->info('Customer role: ' . $customerPermissions->count() . ' permissions assigned');
        }
    }
}
