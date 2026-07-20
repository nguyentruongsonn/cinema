<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $users = DB::table('users')->select(['id', 'email'])->orderBy('id')->get();

            foreach ($users as $user) {
                $normalizedEmail = mb_strtolower(trim((string) $user->email));

                if ($normalizedEmail === '' || mb_strlen($normalizedEmail) > 255) {
                    throw new RuntimeException("User {$user->id} has an invalid email that cannot be normalized safely.");
                }

                if ($normalizedEmail !== $user->email) {
                    DB::table('users')->where('id', $user->id)->update(['email' => $normalizedEmail]);
                }
            }

            $duplicates = DB::table('users')
                ->select('email')
                ->groupBy('email')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('email');

            if ($duplicates->isNotEmpty()) {
                throw new RuntimeException(
                    'Cannot add users email uniqueness until duplicate normalized emails are resolved: '
                    . $duplicates->implode(', ')
                );
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('email', 'users_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_unique');
        });
    }
};
