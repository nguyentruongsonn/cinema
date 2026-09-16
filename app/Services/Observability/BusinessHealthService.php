<?php

declare(strict_types=1);

namespace App\Services\Observability;

use App\Models\Order;

final class BusinessHealthService
{
    /**
     * @return array{
     *     healthy: bool,
     *     lookback_hours: int,
     *     checks: array<string, array{count: int, threshold: int, healthy: bool}>
     * }
     */
    public function snapshot(): array
    {
        $lookbackHours = max(1, (int) config('observability.operations.lookback_hours', 24));
        $emailMaxAgeSeconds = max(60, (int) config('observability.operations.email_max_age_seconds', 600));
        $maxOverduePayments = max(0, (int) config('observability.operations.max_overdue_payments', 0));
        $maxUnsentEmails = max(0, (int) config('observability.operations.max_unsent_ticket_emails', 5));
        $lookbackStart = now()->subHours($lookbackHours);

        $overduePayments = Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->where('payment_status', 'pending')
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now())
            ->where('created_at', '>=', $lookbackStart)
            ->count();

        $unsentTicketEmails = Order::query()
            ->where(function ($query): void {
                $query->where('status', Order::STATUS_CONFIRMED)
                    ->orWhere('payment_status', 'paid');
            })
            ->whereNull('ticket_email_sent_at')
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', now()->subSeconds($emailMaxAgeSeconds))
            ->where('created_at', '>=', $lookbackStart)
            ->whereHas('user', function ($query): void {
                $query->whereNotNull('email')->where('email', '!=', '');
            })
            ->count();

        $checks = [
            'overdue_pending_payments' => [
                'count' => $overduePayments,
                'threshold' => $maxOverduePayments,
                'healthy' => $overduePayments <= $maxOverduePayments,
            ],
            'unsent_ticket_emails' => [
                'count' => $unsentTicketEmails,
                'threshold' => $maxUnsentEmails,
                'healthy' => $unsentTicketEmails <= $maxUnsentEmails,
            ],
        ];

        return [
            'healthy' => collect($checks)->every(fn (array $check): bool => $check['healthy']),
            'lookback_hours' => $lookbackHours,
            'checks' => $checks,
        ];
    }
}
