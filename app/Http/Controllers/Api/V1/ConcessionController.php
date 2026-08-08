<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConcessionController extends Controller
{
    use ApiResponse;

    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        $theaterIds = $user->requiresTheaterScope()
            ? $user->theaters()->pluck('theaters.id')->all()
            : [];

        $items = OrderItem::query()
            ->where('fulfillment_status', OrderItem::FULFILLMENT_PENDING)
            ->whereIn('item_type', [Product::class, Combo::class])
            ->whereHas('order', function ($query) use ($theaterIds): void {
                $query->where('status', Order::STATUS_CONFIRMED)
                    ->where('payment_status', 'paid')
                    ->where(function ($source): void {
                        $source->where('source', 'pos')->orWhere('code', 'like', 'POS-%');
                    })
                    ->when($theaterIds !== [], fn ($scoped) => $scoped->whereIn('theater_id', $theaterIds));
            })
            ->with(['order:id,code,theater_id,created_at', 'item'])
            ->latest()
            ->paginate(min((int) $request->input('per_page', 20), 50));

        return $this->paginatedResponse($items, 'Pending concession items retrieved');
    }

    public function fulfill(Request $request, OrderItem $orderItem): JsonResponse
    {
        $user = $request->user();

        $fulfilled = DB::transaction(function () use ($user, $orderItem): OrderItem {
            $item = OrderItem::query()
                ->with('order')
                ->whereKey($orderItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(in_array($item->item_type, [Product::class, Combo::class], true), 422, 'Dòng hàng không phải concession.');
            abort_unless($item->order->isPaid() && $item->order->isPos(), 422, 'Đơn hàng chưa đủ điều kiện giao hàng.');
            abort_unless(! $user->requiresTheaterScope() || $user->isAssignedToTheater((int) $item->order->posTheaterId()), 403, 'Bạn không có quyền xử lý đơn tại rạp này.');

            if ($item->fulfillment_status === OrderItem::FULFILLMENT_FULFILLED) {
                return $item;
            }

            $item->forceFill([
                'fulfillment_status' => OrderItem::FULFILLMENT_FULFILLED,
                'fulfilled_by_user_id' => $user->id,
                'fulfilled_at' => now(),
            ])->save();

            return $item;
        });

        return $this->successResponse($fulfilled->fresh(['fulfilledBy:id,name']), 'Đã xác nhận giao concession.');
    }
}
