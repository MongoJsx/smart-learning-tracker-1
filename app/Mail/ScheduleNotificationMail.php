<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduleNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $title;
    public readonly string $messageText;
    public readonly array $lines;

    public function __construct(string $title, string $messageText, ?array $lines = null)
    {
        $this->title = $title;
        $this->messageText = $messageText;
        $this->lines = $lines ?: $this->splitLines($messageText);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.schedule-notification',
            with: [
                'title' => $this->title,
                'messageText' => $this->messageText,
                'lines' => $this->lines,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function splitLines(string $message): array
    {
        $parts = preg_split("/\r\n|\r|\n|\s*\|\s*/", $message) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }
}
