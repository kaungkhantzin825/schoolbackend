<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A message submitted through the public contact form.
 *
 * Sent *to* the support mailbox and *from* the platform address, with the
 * visitor's address as Reply-To — the SMTP account is not allowed to send on
 * behalf of arbitrary domains, and forging From would land the mail in spam.
 */
class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array $data    validated form fields
     * @param array $context ip / country of the sender
     */
    public function __construct(
        public array $data,
        public array $context = [],
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->data['subject'] ?? 'General enquiry';

        return new Envelope(
            subject: 'ACVR contact form — ' . $subject,
            replyTo: [new Address($this->data['email'], $this->data['name'])],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-message',
            with: [
                'name'         => $this->data['name'] ?? '',
                'email'        => $this->data['email'] ?? '',
                'country'      => $this->data['country'] ?? null,
                'organization' => $this->data['organization'] ?? null,
                'subjectLine'  => $this->data['subject'] ?? '',
                'body'         => $this->data['message'] ?? '',
                'ip'           => $this->context['ip'] ?? null,
                'ipCountry'    => $this->context['country'] ?? null,
            ],
        );
    }
}
