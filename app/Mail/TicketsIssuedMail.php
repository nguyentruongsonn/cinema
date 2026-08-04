<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketsIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vé xem phim của bạn - ' . $this->order->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.issued',
            with: [
                'order' => $this->order,
                'tickets' => $this->order->tickets,
                'showtime' => $this->order->showtime,
                'movie' => $this->order->showtime?->movie,
                'screen' => $this->order->showtime?->screen,
                'theater' => $this->order->showtime?->screen?->theater,
            ],
        );
    }
}
