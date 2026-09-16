<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->index(['is_active', 'created_at'], 'branches_admin_status_created_idx');
        });

        Schema::table('theaters', function (Blueprint $table) {
            $table->index(['branch_id', 'status', 'created_at'], 'theaters_admin_branch_status_created_idx');
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->index(['theater_id', 'status', 'created_at'], 'screens_admin_theater_status_created_idx');
        });

        Schema::table('seat_layout_templates', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'seat_templates_admin_status_created_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['type', 'status', 'created_at'], 'products_admin_type_status_created_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['role_id', 'status', 'created_at'], 'users_admin_role_status_created_idx');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->index(['status', 'category', 'created_at'], 'promotions_admin_status_category_created_idx');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->index(['is_published', 'category', 'created_at'], 'posts_admin_status_category_created_idx');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->index(['is_active', 'position', 'display_order'], 'banners_admin_status_position_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex('banners_admin_status_position_order_idx');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_admin_status_category_created_idx');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex('promotions_admin_status_category_created_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_admin_role_status_created_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_admin_type_status_created_idx');
        });

        Schema::table('seat_layout_templates', function (Blueprint $table) {
            $table->dropIndex('seat_templates_admin_status_created_idx');
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->dropIndex('screens_admin_theater_status_created_idx');
        });

        Schema::table('theaters', function (Blueprint $table) {
            $table->dropIndex('theaters_admin_branch_status_created_idx');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex('branches_admin_status_created_idx');
        });
    }
};
