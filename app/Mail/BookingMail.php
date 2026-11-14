<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** One Mailable for every booking email: a Markdown view plus its data. */
class BookingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $markdownView,
        public array $data = [],
        public ?string $replyToAddress = null,
        /** PDF files to attach: file name => contents. */
        public array $pdfs = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: $this->replyToAddress ? [$this->replyToAddress] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: $this->markdownView, with: $this->data);
    }

    public function attachments(): array
    {
        return collect($this->pdfs)
            ->map(fn (string $data, string $name) => Attachment::fromData(fn () => $data, $name)->withMime('application/pdf'))
            ->values()->all();
    }
}
