<?php

namespace App\Mail;

use App\Models\CommerceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommerceOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CommerceOrder $order,
        public string $kind = 'confirmation',
        public ?int $amountMinor = null,
    ) {}

    public function build(): self
    {
        $store = $this->order->website?->name ?: 'Online Store';
        $subject = match ($this->kind) {
            'merchant_new_order' => 'New order '.$this->order->order_number.' · '.$store,
            'fulfilled' => 'Your order '.$this->order->order_number.' has been fulfilled',
            'tracking' => 'Shipping update for '.$this->order->order_number,
            'refund' => 'Refund issued for '.$this->order->order_number,
            default => 'Order confirmed · '.$this->order->order_number,
        };

        return $this->subject($subject)->view('emails.commerce-order');
    }
}
