<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DebitNoteEmailProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $current;        // Current email sent
    public int $total;          // Total emails to send
    public string $noteReference; // Reference number of last processed note
    public bool $success;       // Whether last email was sent successfully

    /**
     * Create a new event instance.
     */
    public function __construct(int $current, int $total, string $noteReference, bool $success = true)
    {
        $this->current = $current;
        $this->total = $total;
        $this->noteReference = $noteReference;
        $this->success = $success;
    }

    /**
     * The channels the event should broadcast on.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('debit-note-progress');
    }
}
