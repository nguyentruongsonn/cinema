<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel: chỉ user sở hữu đơn hàng mới được lắng nghe
Broadcast::channel('order.{orderCode}', function ($user, $orderCode) {
    return \App\Models\Order::where('gateway_order_code', (int) $orderCode)
        ->where('user_id', $user->id)
        ->exists();
});
