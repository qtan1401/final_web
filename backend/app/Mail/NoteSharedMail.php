<?php

namespace App\Mail;

use App\Models\Note;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NoteSharedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Note $note,
        public User $sharedBy,
        public string $permission
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->sharedBy->display_name . ' shared a note with you - ' . $this->note->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.note-shared',
        );
    }
}
