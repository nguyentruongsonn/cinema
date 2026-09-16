<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FOREIGN_KEYS = [
        'orders_user_fk' => ['orders', 'user_id', 'users', 'restrict'],
        'orders_showtime_fk' => ['orders', 'showtime_id', 'showtimes', 'restrict'],
        'payments_order_fk' => ['payments', 'order_id', 'orders', 'restrict'],
        'payments_user_fk' => ['payments', 'user_id', 'users', 'set null'],
        'seat_holds_user_fk' => ['seat_holds', 'user_id', 'users', 'cascade'],
        'seat_holds_showtime_fk' => ['seat_holds', 'showtime_id', 'showtimes', 'cascade'],
    ];

    private const USER_PROMOTION_UNIQUE = 'user_promotion_user_id_promotion_id_unique';

    public function up(): void
    {
        $this->assertLegacyDataIsCompatible();

        foreach (self::FOREIGN_KEYS as $name => [$table, $column, $parentTable, $onDelete]) {
            if ($this->foreignKeyExists($table, $name, $column, $parentTable)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name, $column, $parentTable, $onDelete) {
                $blueprint->foreign($column, $name)
                    ->references('id')
                    ->on($parentTable)
                    ->onDelete($onDelete)
                    ->onUpdate('cascade');
            });
        }

        if (!Schema::hasIndex('user_promotion', self::USER_PROMOTION_UNIQUE, 'unique')) {
            Schema::table('user_promotion', function (Blueprint $table) {
                $table->unique(['user_id', 'promotion_id'], self::USER_PROMOTION_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('user_promotion', self::USER_PROMOTION_UNIQUE, 'unique')) {
            Schema::table('user_promotion', function (Blueprint $table) {
                $table->dropUnique(self::USER_PROMOTION_UNIQUE);
            });
        }

        foreach (array_reverse(self::FOREIGN_KEYS, true) as $name => [$table, $column, $parentTable]) {
            if (!$this->foreignKeyExists($table, $name, $column, $parentTable)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name, $column) {
                $blueprint->dropForeign(DB::getDriverName() === 'sqlite' ? [$column] : $name);
            });
        }
    }

    private function assertLegacyDataIsCompatible(): void
    {
        $violations = [];

        foreach (self::FOREIGN_KEYS as $name => [$table, $column, $parentTable]) {
            $query = DB::table("{$table} as child")
                ->leftJoin("{$parentTable} as parent", "parent.id", '=', "child.{$column}")
                ->whereNotNull("child.{$column}")
                ->whereNull('parent.id');

            $count = (clone $query)->count();

            if ($count > 0) {
                $sampleIds = (clone $query)->orderBy('child.id')->limit(10)->pluck('child.id')->implode(', ');
                $violations[] = "{$name}: {$count} orphan row(s); child ids [{$sampleIds}]";
            }
        }

        $duplicatePromotions = DB::table('user_promotion')
            ->select('user_id', 'promotion_id', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('user_id', 'promotion_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('user_id')
            ->orderBy('promotion_id')
            ->limit(10)
            ->get();

        if ($duplicatePromotions->isNotEmpty()) {
            $samples = $duplicatePromotions
                ->map(fn ($row) => "user_id={$row->user_id}, promotion_id={$row->promotion_id}, rows={$row->duplicate_count}")
                ->implode('; ');
            $violations[] = self::USER_PROMOTION_UNIQUE . ": duplicate pair(s); {$samples}";
        }

        if ($violations !== []) {
            throw new RuntimeException(
                "Financial integrity preflight failed. No schema changes were applied. " .
                "Repair or archive the listed legacy rows before retrying:\n- " .
                implode("\n- ", $violations)
            );
        }
    }

    private function foreignKeyExists(string $table, string $name, string $column, string $parentTable): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(function (array $foreignKey) use ($name, $column, $parentTable): bool {
                if (DB::getDriverName() !== 'sqlite') {
                    return $foreignKey['name'] === $name;
                }

                return $foreignKey['columns'] === [$column] && $foreignKey['foreign_table'] === $parentTable;
            });
    }
};
