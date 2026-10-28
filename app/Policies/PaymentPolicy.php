<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payment $payment): bool
    {
        if (! $payment->relationLoaded('order')) {
            $payment->load('order');
        }

        $order = $payment->order;

        if ($order instanceof Order && $order->user_id === $user->id) {
            return true;
        }

        if ($user->hasPermission('payments.view')) {
            return true;
        }

        return false;
    }

    public function create(User $user, ?Order $order = null): bool
    {
        if (! $this->isActiveUser($user)) {
            return false;
        }

        if ($order && $order->user_id !== $user->id) {
            return false;
        }

        return true;
    }

    private function isActiveUser(User $user): bool
    {
        return in_array($user->status, [true, 1, '1', 'active'], true);
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.verify');
    }

    public function delete(User $user, Payment $payment): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        if ($payment->status === 'success' || $payment->status === 'paid') {
            return false;
        }

        return true;
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.refund');
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }
}
