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

class PosCustomerService
{
    public const POINTS_TO_VND = 1000;

    public const EARN_RATE = 10000;

    public const MIN_REDEEM_POINTS = 10;

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

    public function lookupById(int $customerId): ?User
    {
        return User::query()
            ->whereKey($customerId)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', ['customer', 'user']))
            ->where('status', 1)
            ->whereNull('system_key')
            ->first();
    }

    public function createWalkInCustomer(string $phone, string $name): User
    {
        $phone = $this->normalizePhone($phone);
        $customerRole = Role::where('slug', 'customer')->first();

        if (!$customerRole) {
            throw new \RuntimeException('Role customer không tồn tại trong hệ thống.');
        }

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

    public function calculateEarnedPoints(float $orderTotal): int
    {
        if ($orderTotal <= 0) {
            return 0;
        }

        return (int) floor($orderTotal / self::EARN_RATE);
    }

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
