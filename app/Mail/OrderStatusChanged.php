<?php
namespace App\Mail;
use App\Models\Order; use App\Models\Tenant;
use Illuminate\Bus\Queueable; use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content; use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
class OrderStatusChanged extends Mailable {
    use Queueable, SerializesModels;
    public function __construct(public Order $order, public Tenant $tenant, public string $statusName) {}
    public function envelope(): Envelope { return new Envelope(subject: 'Статус заказа №'.$this->order->number.': '.$this->statusName); }
    public function content(): Content { return new Content(markdown: 'emails.order-status-changed'); }
}
