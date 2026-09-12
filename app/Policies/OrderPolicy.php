<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        if ($order->user_id === $user->id) {
            return true;
        }

        if ($user->hasPermission('orders.view_all')) {
            return true;
        }

        if ($user->hasPermission('orders.view_theater')) {
            return $this->canAccessOrderTheater($user, $order);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function viewAtPos(User $user, Order $order): bool
    {
        return $order->isPos()
            && $user->hasPermission('orders.view_theater')
            && $this->canAccessOrderTheater($user, $order);
    }

    public function cancelAtPos(User $user, Order $order): bool
    {
        return $order->isPos()
            && $user->hasPermission('orders.cancel')
            && $this->canAccessOrderTheater($user, $order)
            && $order->isPending()
            && ! $order->isPaid();
    }

    public function confirmCash(User $user, Order $order): bool
    {
        return $order->isPos()
            && ($user->hasPermission('payments.process_cash') || $user->hasPermission('payments.process'))
            && $this->canAccessOrderTheater($user, $order)
            && $order->isPending()
            && data_get($order->payload, 'payment_method') === 'cash';
    }

    private function isActiveUser(User $user): bool
    {
        return in_array($user->status, [true, 1, '1', 'active'], true);
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($order->user_id !== $user->id) {
            if (! $user->hasPermission('orders.cancel')) {
                return false;
            }

            if (! $user->hasPermission('orders.view_all') && ! $this->canAccessOrderTheater($user, $order)) {
                return false;
            }
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return false;
        }

        if ($order->payment_status === 'paid') {
            return false;
        }

        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Order $order): bool
    {
        if (!$user->isAdmin()) {
            return false;
        }

        if (in_array($order->status, [Order::STATUS_CONFIRMED]) || $order->payment_status === 'paid') {
            return false;
        }

        return true;
    }

    public function viewHistory(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }

    public function refund(User $user, Order $order): bool
    {
        if (! $user->hasPermission('orders.refund')) {
            return false;
        }

        return $user->hasPermission('orders.view_all') || $this->canAccessOrderTheater($user, $order);
    }

    private function canAccessOrderTheater(User $user, Order $order): bool
    {
        if (! $user->requiresTheaterScope()) {
            return true;
        }

        $theaterId = $order->posTheaterId();

        return $theaterId !== null && $user->isAssignedToTheater($theaterId);
    }
}
