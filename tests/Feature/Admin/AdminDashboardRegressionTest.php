<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminDashboardRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_load_dashboard_metrics_and_paginated_combos(): void
    {
        Cache::flush();

        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Administrator']
        );
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/dashboard/stats?start_date=2026-07-01&end_date=2026-07-18')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['cards', 'revenue_by_day', 'top_movies', 'traffic_heatmap', 'recent_orders'],
            ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/combos?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'pagination' => ['current_page', 'last_page', 'per_page', 'total']]);
    }
}
