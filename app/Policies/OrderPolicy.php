<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine if user can view any orders (their own).
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can see their own order list
    }

    /**
     * Determine if user can view the order.
     *
     * IDOR Protection: User must own the order OR be admin.
     */
    public function view(User $user, Order $order): bool
    {
        // User owns the order
        if ($order->user_id === $user->id) {
            return true;
        }

        // Admin can view any order
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Staff with order viewing permission
        if ($user->hasPermission('view_all_orders')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can create orders.
     */
    public function create(User $user): bool
    {
        // Must be authenticated and active
        return $this->isActiveUser($user);
    }

    private function isActiveUser(User $user): bool
    {
        return in_array($user->status, [true, 1, '1', 'active'], true);
    }

    /**
     * Determine if user can cancel the order.
     *
     * Business rules:
     * - User must own the order
     * - Order must be in cancellable status
     * - Order must not be expired
     */
    public function cancel(User $user, Order $order): bool
    {
        // User must own the order
        if ($order->user_id !== $user->id) {
            // Admin can cancel any order
            if (!$user->hasAnyRole(['admin', 'super-admin']) && !$user->hasPermission('cancel_orders')) {
                return false;
            }
        }

        // Order must be in pending status
        if ($order->status !== Order::STATUS_PENDING) {
            return false;
        }

        // Payment must not be completed
        if ($order->payment_status === 'paid') {
            return false;
        }

        return true;
    }

    /**
     * Determine if user can update the order.
     *
     * Only admin can update orders.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Determine if user can delete the order.
     *
     * Only admin can delete orders (and only if safe to do so).
     */
    public function delete(User $user, Order $order): bool
    {
        if (!$user->hasAnyRole(['admin', 'super-admin'])) {
            return false;
        }

        // Cannot delete confirmed or paid orders
        if (in_array($order->status, [Order::STATUS_CONFIRMED]) || $order->payment_status === 'paid') {
            return false;
        }

        return true;
    }

    /**
     * Determine if user can view order history.
     */
    public function viewHistory(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }

    /**
     * Determine if user can refund the order.
     *
     * Only admin with specific permission.
     */
    public function refund(User $user, Order $order): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('refund_orders');
    }
}
