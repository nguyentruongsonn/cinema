<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoyaltyHistory;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PosCustomerService
 *
 * Handles customer lookup, walk-in creation, and loyalty points for POS kiosk.
 */
class PosCustomerService
{
    /** 1 điểm = 1.000 VNĐ giảm giá */
    public const POINTS_TO_VND = 1000;

    /** Tích 1 điểm per 10.000đ chi tiêu */
    public const EARN_RATE = 10000;

    /** Cần ít nhất 10 điểm mới được dùng */
    public const MIN_REDEEM_POINTS = 10;

    /**
     * Tìm khách hàng theo SĐT, chỉ trả về customer role.
     */
    public function lookupByPhone(string $phone): ?User
    {
        $phoneVariants = $this->phoneVariants($phone);

        return User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['customer', 'user']))
            ->whereIn('phone', $phoneVariants)
            ->where('status', 1)
            ->whereNull('system_key')
            ->first();
    }

    /**
     * Tạo khách vãng lai từ SĐT và tên (Không tạo email/password giả).
     */
    public function createWalkInCustomer(string $phone, string $name): User
    {
        $phone = $this->normalizePhone($phone);
        $customerRole = Role::where('slug', 'customer')->first();

        if (!$customerRole) {
            throw new \RuntimeException('Role customer không tồn tại trong hệ thống.');
        }

        // Check duplicate
        $existing = $this->lookupByPhone($phone);
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($phone, $name, $customerRole): User {
            $user = new User();
            $user->forceFill([
                'name'              => $name,
                'phone'             => $phone,
                'username'          => null,
                'email'             => null,
                'password'          => null,
                'role_id'           => $customerRole->id,
                'status'            => 1,
                'account_status'    => 'unclaimed',
                'loyalty_points'    => 0,
            ]);
            $user->save();

            Log::info('POS: Walk-in customer created (unclaimed)', [
                'user_id' => $user->id,
                'phone'   => $phone,
                'name'    => $name,
            ]);

            return $user;
        });
    }

    public function resolveGuestCustomer(int $theaterId): User
    {
        $customerRole = Role::query()->where('slug', 'customer')->firstOrFail();
        $systemKey = 'pos_guest:' . $theaterId;

        return DB::transaction(function () use ($customerRole, $systemKey, $theaterId): User {
            $guest = User::query()->where('system_key', $systemKey)->lockForUpdate()->first();
            if ($guest) {
                return $guest;
            }

            $guest = new User();
            $guest->forceFill([
                'name' => 'Khách vãng lai - Rạp ' . $theaterId,
                'email' => null,
                'username' => null,
                'phone' => null,
                'password' => null,
                'role_id' => $customerRole->id,
                'status' => 1,
                'account_status' => 'system_guest',
                'system_key' => $systemKey,
                'loyalty_points' => 0,
            ]);
            $guest->save();

            Log::notice('POS: system guest customer provisioned', [
                'user_id' => $guest->id,
                'theater_id' => $theaterId,
            ]);

            return $guest;
        });
    }

    /**
     * Trả về thông tin loyalty.
     */
    public function getLoyaltyInfo(User $customer): array
    {
        if ($customer->isSystemGuest()) {
            return ['points' => 0, 'value_vnd' => 0, 'can_redeem' => false];
        }

        $points = (int) ($customer->loyalty_points ?? 0);

        return [
            'points'     => $points,
            'value_vnd'  => $points * self::POINTS_TO_VND,
            'can_redeem' => $points >= self::MIN_REDEEM_POINTS,
        ];
    }

    /**
     * Tính số tiền giảm giá từ điểm tích lũy.
     */
    public function calculatePointsDiscount(User $customer, int $pointsToUse): int
    {
        if ($customer->isSystemGuest()) {
            throw new \RuntimeException('Khách vãng lai không sử dụng được điểm tích lũy.');
        }

        $available = (int) ($customer->loyalty_points ?? 0);

        if ($pointsToUse <= 0) {
            return 0;
        }

        if ($pointsToUse > $available) {
            throw new \RuntimeException("Không đủ điểm. Hiện có {$available}, yêu cầu {$pointsToUse}.");
        }

        return $pointsToUse * self::POINTS_TO_VND;
    }

    /**
     * Tính số điểm tích được từ tổng đơn hàng.
     */
    public function calculateEarnedPoints(float $orderTotal): int
    {
        if ($orderTotal <= 0) {
            return 0;
        }

        return (int) floor($orderTotal / self::EARN_RATE);
    }

    /**
     * Trừ điểm khi khách dùng để thanh toán (trong transaction).
     */
    public function redeemPoints(User $customer, int $pointsToUse, Order $order): void
    {
        if ($pointsToUse <= 0) {
            return;
        }

        if ($customer->isSystemGuest()) {
            throw new \RuntimeException('Khách vãng lai không sử dụng được điểm tích lũy.');
        }

        $customer = User::query()->lockForUpdate()->findOrFail($customer->id);
        $available = (int) ($customer->loyalty_points ?? 0);

        if ($pointsToUse > $available) {
            throw new \RuntimeException("Không đủ điểm tích lũy.");
        }

        $customer->decrement('loyalty_points', $pointsToUse);

        LoyaltyHistory::create([
            'user_id'     => $customer->id,
            'order_id'    => $order->id,
            'type'        => 'redeem',
            'points'      => $pointsToUse,
            'description' => "Trừ điểm giảm giá cho đơn hàng #{$order->code}",
        ]);

        // Lưu vào order payload
        $payload = (array) $order->payload;
        $payload['loyalty_points_used'] = $pointsToUse;
        $order->forceFill(['payload' => $payload])->save();

        Log::info('POS: Loyalty points redeemed', [
            'customer_id'  => $customer->id,
            'points_used'  => $pointsToUse,
            'discount_vnd' => $pointsToUse * self::POINTS_TO_VND,
            'order_id'     => $order->id,
            'remaining'    => $customer->loyalty_points,
        ]);
    }

    /**
     * Cộng điểm sau khi thanh toán xong (trong transaction).
     */
    public function awardPoints(User $customer, Order $order): void
    {
        if ($customer->isSystemGuest()) {
            return;
        }

        $totalAmount = (float) $order->total_amount;
        $pointsEarned = $this->calculateEarnedPoints($totalAmount);

        if ($pointsEarned <= 0) {
            return;
        }

        $customer = User::query()->lockForUpdate()->findOrFail($customer->id);
        $customer->increment('loyalty_points', $pointsEarned);

        LoyaltyHistory::create([
            'user_id'     => $customer->id,
            'order_id'    => $order->id,
            'type'        => 'earn',
            'points'      => $pointsEarned,
            'description' => "Tích điểm đơn hàng #{$order->code}",
        ]);

        // Lưu vào order payload
        $payload = (array) $order->payload;
        $payload['loyalty_points_earned'] = $pointsEarned;
        $order->forceFill(['payload' => $payload])->save();

        Log::info('POS: Loyalty points awarded', [
            'customer_id'   => $customer->id,
            'points_earned' => $pointsEarned,
            'order_total'   => $totalAmount,
            'order_id'      => $order->id,
            'new_balance'   => $customer->loyalty_points,
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '84')) {
            $phone = '0' . substr($phone, 2);
        }

        return $phone;
    }

    private function phoneVariants(string $phone): array
    {
        $normalized = $this->normalizePhone($phone);
        $local = ltrim($normalized, '0');

        return array_values(array_unique(array_filter([
            $normalized,
            $local,
            $local !== '' ? '0' . $local : null,
            $local !== '' ? '84' . $local : null,
        ])));
    }
}
