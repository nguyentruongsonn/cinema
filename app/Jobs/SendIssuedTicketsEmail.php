<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\TicketsIssuedMail;
use App\Models\Combo;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendIssuedTicketsEmail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $orderId) {}

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $order = Order::query()
            ->with([
                'user:id,name,email,phone',
                'servedBy:id,name',
                'showtime:id,scheduled_at,screen_id,movie_id,format_id,version_type_id',
                'showtime.movie:id,title,duration,age_rating',
                'showtime.format:id,name',
                'showtime.versionType:id,name',
                'showtime.screen:id,name,theater_id',
                'showtime.screen.theater:id,name,address,phone',
                'tickets:id,order_id,ticket_code,seat_id,status',
                'tickets.seat:id,label',
                'orderItems:id,order_id,item_type,item_id,quantity,unit_price,total_price,metadata,fulfillment_status',
                'payment:id,order_id,method',
            ])
            ->find($this->orderId);

        $hasConcessions = $order?->orderItems?->contains(
            fn (OrderItem $item): bool => in_array($item->item_type, [Product::class, Combo::class], true)
        ) ?? false;

        if (! $order || $order->ticket_email_sent_at || ! $order->user?->email || ($order->tickets->isEmpty() && ! $hasConcessions)) {
            Log::info('Issued tickets email skipped', [
                'order_id' => $this->orderId,
                'has_order' => (bool) $order,
                'already_sent' => (bool) $order?->ticket_email_sent_at,
                'has_email' => (bool) $order?->user?->email,
                'tickets_count' => $order?->tickets?->count() ?? 0,
                'has_concessions' => $hasConcessions,
            ]);

            return;
        }

        Mail::to($order->user->email)->send(new TicketsIssuedMail($order));

        Order::query()
            ->whereKey($order->id)
            ->whereNull('ticket_email_sent_at')
            ->update(['ticket_email_sent_at' => now()]);

        Log::info('Issued tickets email sent', [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'tickets_count' => $order->tickets->count(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Failed to send issued tickets email', [
            'order_id' => $this->orderId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
