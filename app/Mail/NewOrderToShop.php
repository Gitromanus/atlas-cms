<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOrderToShop extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Tenant $tenant,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Новый заказ №'.$this->order->number.' — '.$this->tenant->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.new-order-shop',
            with: [
                'order' => $this->order,
                'tenant' => $this->tenant,
                'adminUrl' => url('/shop/orders/'.$this->order->id.'/edit'),
            ],
        );
    }
}
