<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly ContactMessage $contactMessage) {}

    public function envelope(): Envelope
    {
        $subject = $this->contactMessage->subject
            ? 'New contact message: '.$this->contactMessage->subject
            : 'New contact message';

        return new Envelope(
            subject: $subject,
            replyTo: [new Address($this->contactMessage->email, $this->contactMessage->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-message-notification',
            with: [
                'contactMessage' => $this->contactMessage,
            ],
        );
    }
}
