<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Services\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
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
            subject: 'Hóa đơn thanh toán CINEMA - '.$this->order->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.issued',
            with: [
                'order' => $this->order,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => app(InvoicePdfService::class)->render($this->order),
                'hoa-don-'.$this->order->code.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
