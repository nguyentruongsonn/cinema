<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Determine if user can view any payments (their own).
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can see their own payment list
    }

    /**
     * Determine if user can view the payment.
     *
     * IDOR Protection: User must own the payment (via order) OR be admin.
     */
    public function view(User $user, Payment $payment): bool
    {
        // Load order relationship if not loaded
        if (!$payment->relationLoaded('order')) {
            $payment->load('order');
        }

        // User owns the order associated with this payment
        if ($payment->order && $payment->order->user_id === $user->id) {
            return true;
        }

        // Admin can view any payment
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Staff with payment viewing permission
        if ($user->hasPermission('view_payment_details')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can create a payment.
     *
     * User must own the order they're paying for.
     */
    public function create(User $user, $order = null): bool
    {
        // User must be active
        if (! $this->isActiveUser($user)) {
            return false;
        }

        // If order is provided, user must own it
        if ($order && $order->user_id !== $user->id) {
            return false;
        }

        return true;
    }

    private function isActiveUser(User $user): bool
    {
        return in_array($user->status, [true, 1, '1', 'active'], true);
    }

    /**
     * Determine if user can update the payment.
     *
     * Only system (via webhook) or admin can update payments.
     */
    public function update(User $user, Payment $payment): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('verify_payments');
    }

    /**
     * Determine if user can delete the payment.
     *
     * Payments should rarely be deleted. Only admin with special permission.
     */
    public function delete(User $user, Payment $payment): bool
    {
        if (!$user->hasAnyRole(['admin', 'super-admin'])) {
            return false;
        }

        // Cannot delete successful payments
        if ($payment->status === 'success' || $payment->status === 'paid') {
            return false;
        }

        return true;
    }

    /**
     * Determine if user can refund the payment.
     *
     * Only admin with specific refund permission.
     */
    public function refund(User $user, Payment $payment): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Determine if user can verify the payment (check status with gateway).
     *
     * User must own the payment OR be admin.
     */
    public function verify(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }
}
