<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationToCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Tenant $tenant,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Заказ №'.$this->order->number.' принят — '.$this->tenant->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-confirmation-customer',
            with: [
                'order' => $this->order,
                'tenant' => $this->tenant,
                'trackUrl' => $this->tenant->url().'/track?number='.urlencode($this->order->number),
            ],
        );
    }
}
