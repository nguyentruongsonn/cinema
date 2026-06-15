<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'code')) {
                $table->dropColumn('code');
            }

            if (Schema::hasColumn('branches', 'description')) {
                $table->dropColumn('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'code')) {
                $table->string('code')->nullable()->after('name');
            }

            if (! Schema::hasColumn('branches', 'description')) {
                $table->text('description')->nullable()->after('code');
            }
        });
    }
};