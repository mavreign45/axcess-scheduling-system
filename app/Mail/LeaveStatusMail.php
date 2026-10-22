<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaveStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly LeaveRequest $leave,
        public readonly string $status,
        public readonly ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        $label = ucfirst($this->status);

        return new Envelope(
            subject: "Axcess — Leave request {$label}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.leave-status',
            with: [
                'leave' => $this->leave,
                'status' => $this->status,
                'note' => $this->note,
            ],
        );
    }
}
