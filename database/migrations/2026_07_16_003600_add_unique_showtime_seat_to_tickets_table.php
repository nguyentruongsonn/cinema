<?php

use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->softDeleteDuplicateTickets();

        Schema::table('tickets', function (Blueprint $table) {
            $table->unique(['showtime_id', 'seat_id'], 'tickets_showtime_seat_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique('tickets_showtime_seat_unique');
        });
    }

    private function softDeleteDuplicateTickets(): void
    {
        if (!Schema::hasColumn('tickets', 'deleted_at')) {
            return;
        }

        $duplicateGroups = DB::table('tickets')
            ->select('showtime_id', 'seat_id', DB::raw('MIN(id) as keep_id'))
            ->whereNull('deleted_at')
            ->groupBy('showtime_id', 'seat_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            DB::table('tickets')
                ->where('showtime_id', $group->showtime_id)
                ->where('seat_id', $group->seat_id)
                ->where('id', '<>', $group->keep_id)
                ->whereNull('deleted_at')
                ->update([
                    'status' => Ticket::STATUS_CANCELLED,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }
};
