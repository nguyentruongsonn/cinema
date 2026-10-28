<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Promotion;
use App\Models\User;

class PromotionService
{
    public function getUserRegisteredPromotions(User $user)
    {
        return $user->promotions()
            ->where('promotions.status', 1)
            ->where(function ($query) {
                $query->whereNull('promotions.start_date')
                    ->orWhere('promotions.start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('promotions.end_date')
                    ->orWhere('promotions.end_date', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('promotions.usage_limit')
                    ->orWhereColumn('promotions.usage_count', '<', 'promotions.usage_limit');
            })
            ->wherePivot('status', 1)
            ->where(function ($query) {
                $query->whereNull('user_promotion.used_at')
                    ->where(function ($pivotQuery) {
                        $pivotQuery->whereNull('user_promotion.usage_count')
                            ->orWhere('user_promotion.usage_count', 0);
                    });
            })
            ->withPivot(['status', 'used_at', 'order_id', 'usage_count', 'created_at'])
            ->orderByPivot('created_at', 'desc')
            ->get();
    }

    public function registerPromotionForUser(User $user, string $code): array
    {
        $promotion = Promotion::where('promotions.status', 1)
            ->where(function ($query) {
                $query->whereNull('promotions.start_date')
                    ->orWhere('promotions.start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('promotions.end_date')
                    ->orWhere('promotions.end_date', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('promotions.usage_limit')
                    ->orWhereColumn('promotions.usage_count', '<', 'promotions.usage_limit');
            })
            ->whereRaw('LOWER(promotions.code) = ?', [mb_strtolower(trim($code))])
            ->first();

        if (!$promotion) {
            return [
                'success' => false,
                'promotion' => null,
                'message' => 'Mã khuyến mãi không tồn tại hoặc đã hết hạn.',
            ];
        }

        $existing = $user->promotions()
            ->where('promotions.id', $promotion->id)
            ->first();

        if ($existing) {
            $pivotUsedAt = $existing->pivot->used_at ?? null;
            $pivotUsageCount = (int) ($existing->pivot->usage_count ?? 0);
            $pivotStatus = (int) ($existing->pivot->status ?? 1);

            if ($pivotUsedAt !== null || $pivotUsageCount > 0 || $pivotStatus !== 1) {
                return [
                    'success' => false,
                    'promotion' => null,
                    'message' => 'Mã khuyến mãi này đã được sử dụng hoặc không còn khả dụng.',
                ];
            }

            return [
                'success' => true,
                'promotion' => $promotion->fresh(),
                'message' => 'Mã khuyến mãi đã có trong Kho Voucher.',
            ];
        }

        $user->promotions()->attach($promotion->id, [
            'status' => 1,
            'usage_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'success' => true,
            'promotion' => $promotion,
            'message' => 'Đăng ký mã khuyến mãi thành công.',
        ];
    }

    public function validatePromotion(string $code, float $orderTotal, ?User $user = null): array
    {
        $promotionQuery = Promotion::where('promotions.status', 1)
            ->where(function ($query) {
                $query->whereNull('promotions.start_date')
                    ->orWhere('promotions.start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('promotions.end_date')
                    ->orWhere('promotions.end_date', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('promotions.usage_limit')
                    ->orWhereColumn('promotions.usage_count', '<', 'promotions.usage_limit');
            })
            ->whereRaw('LOWER(promotions.code) = ?', [mb_strtolower(trim($code))]);

        if ($user) {
            $promotionQuery->whereHas('users', function ($query) use ($user) {
                $query->where('users.id', $user->id)
                    ->where('user_promotion.status', 1)
                    ->whereNull('user_promotion.used_at')
                    ->where(function ($pivotQuery) {
                        $pivotQuery->whereNull('user_promotion.usage_count')
                            ->orWhere('user_promotion.usage_count', 0);
                    });
            });
        }

        $promotion = $promotionQuery->first();

        if (!$promotion) {
            return [
                'valid' => false,
                'promotion' => null,
                'discount_amount' => 0,
                'error' => $user ? 'Mã khuyến mãi chưa được đăng ký trong Kho Voucher.' : 'Promotion not found',
            ];
        }

        $minOrderValue = (float) ($promotion->min_order_value ?? 0);

        if ($orderTotal < $minOrderValue) {
            return [
                'valid' => false,
                'promotion' => $promotion,
                'discount_amount' => 0,
                'error' => 'Đơn hàng tối thiểu ' . number_format($minOrderValue, 0, ',', '.') . 'đ để áp dụng mã này.',
                'min_order_value' => $minOrderValue,
            ];
        }

        $discountAmount = $this->calculateDiscount($promotion, $orderTotal);

        return [
            'valid' => true,
            'promotion' => $promotion,
            'discount_amount' => $discountAmount,
        ];
    }

    public function calculateDiscount(Promotion $promotion, float $orderTotal): float
    {
        $discount = 0;

        if (in_array($promotion->discount_type, ['percentage', 'percent'])) {
            $discount = $orderTotal * ((float) $promotion->discount_value / 100);

            $maxDiscount = (float) ($promotion->max_discount_amount ?? 0);
            if ($maxDiscount > 0 && $discount > $maxDiscount) {
                $discount = $maxDiscount;
            }
        } elseif (in_array($promotion->discount_type, ['fixed_amount', 'amount'])) {
            $discount = (float) $promotion->discount_value;

            if ($discount > $orderTotal) {
                $discount = $orderTotal;
            }
        }

        return round($discount, 0);
    }
}
