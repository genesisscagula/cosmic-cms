<?php

namespace App\Mail;

use App\Models\BillingTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BillingReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BillingTransaction $billingTransaction)
    {
    }

    public function build(): self
    {
        return $this->subject('Your Cosmic CMS payment receipt')
            ->view('emails.billing-receipt');
    }
}
