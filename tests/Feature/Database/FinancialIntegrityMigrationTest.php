<?php

namespace Tests\Feature\Database;

use App\Models\Order;
use App\Models\Promotion;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class FinancialIntegrityMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_07_17_121000_add_financial_foreign_keys_and_user_promotion_unique.php';

    #[Test]
    public function foreign_keys_reject_orphan_financial_rows(): void
    {
        $user = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
        ]);

        $this->assertQueryFails(fn () => DB::table('orders')->insert($this->orderRow(999999, $showtime->id)));
        $this->assertQueryFails(fn () => DB::table('orders')->insert($this->orderRow($user->id, 999999)));
        $this->assertQueryFails(fn () => DB::table('payments')->insert($this->paymentRow(999999, $user->id)));
        $this->assertQueryFails(fn () => DB::table('payments')->insert($this->paymentRow($order->id, 999999)));
        $this->assertQueryFails(fn () => DB::table('seat_holds')->insert($this->seatHoldRow(999999, $showtime->id)));
        $this->assertQueryFails(fn () => DB::table('seat_holds')->insert($this->seatHoldRow($user->id, 999999)));
    }

    #[Test]
    public function foreign_key_delete_rules_preserve_finance_and_clean_ephemeral_holds(): void
    {
        $orderOwner = User::factory()->create();
        $paymentUser = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $orderOwner->id,
            'showtime_id' => $showtime->id,
        ]);

        DB::table('payments')->insert($this->paymentRow($order->id, $paymentUser->id));
        DB::table('seat_holds')->insert($this->seatHoldRow($paymentUser->id, $showtime->id));

        $this->assertQueryFails(fn () => DB::table('orders')->where('id', $order->id)->delete());
        DB::table('users')->where('id', $paymentUser->id)->delete();

        $this->assertNull(DB::table('payments')->value('user_id'));
        $this->assertDatabaseCount('seat_holds', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    #[Test]
    public function user_promotion_pair_is_unique(): void
    {
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create();
        $row = $this->userPromotionRow($user->id, $promotion->id);

        DB::table('user_promotion')->insert($row);

        $this->assertQueryFails(fn () => DB::table('user_promotion')->insert($row));
    }

    #[Test]
    public function preflight_aborts_before_ddl_when_legacy_data_is_invalid(): void
    {
        $migration = $this->migration();
        $migration->down();

        $user = User::factory()->create();
        $promotion = Promotion::factory()->create();
        DB::table('orders')->insert($this->orderRow(999999, 999999));
        DB::table('user_promotion')->insert($this->userPromotionRow($user->id, $promotion->id));
        DB::table('user_promotion')->insert($this->userPromotionRow($user->id, $promotion->id));

        try {
            $migration->up();
            $this->fail('Expected the legacy-data preflight to abort the migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No schema changes were applied', $exception->getMessage());
            $this->assertStringContainsString('orders_user_fk', $exception->getMessage());
            $this->assertStringContainsString('orders_showtime_fk', $exception->getMessage());
            $this->assertStringContainsString('user_promotion_user_id_promotion_id_unique', $exception->getMessage());
        }

        $this->assertFalse($this->foreignKeyExists('orders', 'orders_user_fk', 'user_id', 'users'));
        $this->assertFalse(Schema::hasIndex('user_promotion', 'user_promotion_user_id_promotion_id_unique', 'unique'));
    }

    #[Test]
    public function rollback_removes_only_the_new_constraints(): void
    {
        $migration = $this->migration();
        $migration->down();

        $this->assertFalse($this->foreignKeyExists('orders', 'orders_user_fk', 'user_id', 'users'));
        $this->assertFalse($this->foreignKeyExists('payments', 'payments_order_fk', 'order_id', 'orders'));
        $this->assertFalse($this->foreignKeyExists('seat_holds', 'seat_holds_user_fk', 'user_id', 'users'));
        $this->assertFalse(Schema::hasIndex('user_promotion', 'user_promotion_user_id_promotion_id_unique', 'unique'));

        DB::table('orders')->insert($this->orderRow(999999, 999999));
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create();
        $row = $this->userPromotionRow($user->id, $promotion->id);
        DB::table('user_promotion')->insert($row);
        DB::table('user_promotion')->insert($row);

        $this->assertDatabaseCount('user_promotion', 2);
    }

    private function migration(): object
    {
        return require database_path('migrations/' . self::MIGRATION);
    }

    private function foreignKeyExists(string $table, string $name, string $column, string $parentTable): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool =>
                $foreignKey['name'] === $name ||
                ($foreignKey['columns'] === [$column] && $foreignKey['foreign_table'] === $parentTable)
            );
    }

    private function assertQueryFails(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a database integrity constraint violation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    private function orderRow(int $userId, int $showtimeId): array
    {
        return [
            'code' => 'TEST-' . fake()->unique()->numerify('########'),
            'gateway_order_code' => fake()->unique()->numberBetween(10000000, 99999999),
            'user_id' => $userId,
            'showtime_id' => $showtimeId,
            'total_amount' => 100000,
            'status' => 1,
            'payment_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function paymentRow(int $orderId, ?int $userId): array
    {
        return [
            'order_id' => $orderId,
            'user_id' => $userId,
            'method' => 'payos',
            'amount' => 100000,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function seatHoldRow(int $userId, int $showtimeId): array
    {
        return [
            'showtime_id' => $showtimeId,
            'user_id' => $userId,
            'seat_ids' => null,
            'held_until' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function userPromotionRow(int $userId, int $promotionId): array
    {
        return [
            'user_id' => $userId,
            'promotion_id' => $promotionId,
            'status' => 1,
            'usage_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
