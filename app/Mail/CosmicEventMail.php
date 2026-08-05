<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
class CosmicEventMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public string $mailSubject, public string $heading, public string $messageText, public ?string $actionLabel = null, public ?string $actionUrl = null) {}
    public function build(): self
    {
        return $this->subject($this->mailSubject)->view('emails.cosmic-event');
    }
}
