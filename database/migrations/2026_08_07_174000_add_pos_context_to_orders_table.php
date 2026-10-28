<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'theater_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('theater_id')->nullable()->after('showtime_id')->constrained('theaters')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'served_by_user_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('served_by_user_id')->nullable()->after('theater_id')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'source')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('source', 32)->default('web')->after('served_by_user_id');
            });
        }

        if (! Schema::hasColumn('orders', 'payment_method')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('payment_method', 32)->nullable()->after('source');
            });
        }

        $indexNames = collect(Schema::getIndexes('orders'))->pluck('name')->all();
        Schema::table('orders', function (Blueprint $table) use ($indexNames): void {
            if (! in_array('orders_theater_created_idx', $indexNames, true)) {
                $table->index(['theater_id', 'created_at'], 'orders_theater_created_idx');
            }
            if (! in_array('orders_served_by_created_idx', $indexNames, true)) {
                $table->index(['served_by_user_id', 'created_at'], 'orders_served_by_created_idx');
            }
            if (! in_array('orders_source_status_created_idx', $indexNames, true)) {
                $table->index(['source', 'status', 'created_at'], 'orders_source_status_created_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $indexes = collect(Schema::getIndexes('orders'))->pluck('name')->all();
            foreach (['orders_theater_created_idx', 'orders_served_by_created_idx', 'orders_source_status_created_idx'] as $index) {
                if (in_array($index, $indexes, true)) {
                    $table->dropIndex($index);
                }
            }
        });

        if (Schema::hasColumn('orders', 'served_by_user_id')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('served_by_user_id'));
        }
        if (Schema::hasColumn('orders', 'theater_id')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('theater_id'));
        }
        $columns = array_values(array_filter(['source', 'payment_method'], fn (string $column): bool => Schema::hasColumn('orders', $column)));
        if ($columns !== []) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
