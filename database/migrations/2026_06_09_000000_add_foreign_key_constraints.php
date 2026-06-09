<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds missing foreign key constraints to enforce referential integrity.
 *
 * Constraint behaviors:
 * - CASCADE: Child records deleted when parent is deleted
 * - RESTRICT: Prevents parent deletion if children exist
 * - SET NULL: Sets FK to null when parent is deleted (for nullable columns)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip in test environment due to schema mismatches with SQLite
        if (app()->environment('testing')) {
            return;
        }

        // Orders table - core business entity
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('restrict')  // Cannot delete user with existing orders
                ->onUpdate('cascade');

            $table->foreign('showtime_id')
                ->references('id')->on('showtimes')
                ->onDelete('restrict')  // Cannot delete showtime with existing orders
                ->onUpdate('cascade');
        });

        // Order items - must cascade delete with order
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->onDelete('cascade')  // Delete items when order is deleted
                ->onUpdate('cascade');
        });

        // Showtimes - core scheduling entity
        Schema::table('showtimes', function (Blueprint $table) {
            $table->foreign('movie_id')
                ->references('id')->on('movies')
                ->onDelete('restrict')  // Cannot delete movie with scheduled showtimes
                ->onUpdate('cascade');

            $table->foreign('screen_id')
                ->references('id')->on('screens')
                ->onDelete('restrict')  // Cannot delete screen with scheduled showtimes
                ->onUpdate('cascade');

            $table->foreign('format_id')
                ->references('id')->on('formats')
                ->onDelete('set null')  // Optional: clear format if deleted
                ->onUpdate('cascade');

            $table->foreign('subtitle_id')
                ->references('id')->on('subtitles')
                ->onDelete('set null')  // Optional: clear subtitle if deleted
                ->onUpdate('cascade');
        });

        // Seats - tied to screen and seat type
        Schema::table('seats', function (Blueprint $table) {
            $table->foreign('screen_id')
                ->references('id')->on('screens')
                ->onDelete('cascade')  // Delete seats when screen is deleted
                ->onUpdate('cascade');

            $table->foreign('seat_type_id')
                ->references('id')->on('seat_types')
                ->onDelete('restrict')  // Cannot delete seat type in use
                ->onUpdate('cascade');
        });

        // Seat holds - temporary reservations
        Schema::table('seat_holds', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade')  // Delete holds when user is deleted
                ->onUpdate('cascade');

            $table->foreign('showtime_id')
                ->references('id')->on('showtimes')
                ->onDelete('cascade')  // Delete holds when showtime is deleted
                ->onUpdate('cascade');
        });

        // Screens - part of theater
        Schema::table('screens', function (Blueprint $table) {
            $table->foreign('theater_id')
                ->references('id')->on('theaters')
                ->onDelete('cascade')  // Delete screens when theater is deleted
                ->onUpdate('cascade');
        });

        // Theaters - part of branch
        Schema::table('theaters', function (Blueprint $table) {
            $table->foreign('branch_id')
                ->references('id')->on('branches')
                ->onDelete('restrict')  // Cannot delete branch with existing theaters
                ->onUpdate('cascade');
        });

        // Payments - linked to orders
        if (Schema::hasColumn('payments', 'order_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreign('order_id')
                    ->references('id')->on('orders')
                    ->onDelete('restrict')  // Cannot delete order with payment record
                    ->onUpdate('cascade');
            });
        }

        // Categories-Movies pivot (many-to-many)
        if (Schema::hasTable('categories_movies')) {
            Schema::table('categories_movies', function (Blueprint $table) {
                if (Schema::hasColumn('categories_movies', 'category_id')) {
                    $table->foreign('category_id')
                        ->references('id')->on('categories')
                        ->onDelete('cascade')
                        ->onUpdate('cascade');
                }

                if (Schema::hasColumn('categories_movies', 'movie_id')) {
                    $table->foreign('movie_id')
                        ->references('id')->on('movies')
                        ->onDelete('cascade')
                        ->onUpdate('cascade');
                }
            });
        }

        // Role-User pivot (many-to-many)
        if (Schema::hasTable('role_user')) {
            Schema::table('role_user', function (Blueprint $table) {
                if (Schema::hasColumn('role_user', 'role_id')) {
                    $table->foreign('role_id')
                        ->references('id')->on('roles')
                        ->onDelete('cascade')
                        ->onUpdate('cascade');
                }

                if (Schema::hasColumn('role_user', 'user_id')) {
                    $table->foreign('user_id')
                        ->references('id')->on('users')
                        ->onDelete('cascade')
                        ->onUpdate('cascade');
                }
            });
        }

        // Permission-Role pivot (many-to-many)
        if (Schema::hasTable('permission_role')) {
            Schema::table('permission_role', function (Blueprint $table) {
                if (Schema::hasColumn('permission_role', 'permission_id')) {
                    $table->foreign('permission_id')
                        ->references('id')->on('permissions')
                        ->onDelete('cascade')
                        ->onUpdate('cascade');
                }

                if (Schema::hasColumn('permission_role', 'role_id')) {
                    $table->foreign('role_id')
                        ->references('id')->on('roles')
                        ->onDelete('cascade')
                        ->onUpdate('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        // Skip in test environment
        if (app()->environment('testing')) {
            return;
        }

        // Drop foreign keys in reverse order to avoid dependency issues

        if (Schema::hasTable('permission_role')) {
            Schema::table('permission_role', function (Blueprint $table) {
                $table->dropForeign(['permission_id']);
                $table->dropForeign(['role_id']);
            });
        }

        if (Schema::hasTable('role_user')) {
            Schema::table('role_user', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
                $table->dropForeign(['user_id']);
            });
        }

        if (Schema::hasTable('categories_movies')) {
            Schema::table('categories_movies', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropForeign(['movie_id']);
            });
        }

        if (Schema::hasColumn('payments', 'order_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['order_id']);
            });
        }

        Schema::table('theaters', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->dropForeign(['theater_id']);
        });

        Schema::table('seat_holds', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['showtime_id']);
        });

        Schema::table('seats', function (Blueprint $table) {
            $table->dropForeign(['screen_id']);
            $table->dropForeign(['seat_type_id']);
        });

        Schema::table('showtimes', function (Blueprint $table) {
            $table->dropForeign(['movie_id']);
            $table->dropForeign(['screen_id']);
            $table->dropForeign(['format_id']);
            $table->dropForeign(['subtitle_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['showtime_id']);
        });
    }
};
