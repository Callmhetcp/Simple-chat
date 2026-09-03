<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel(
                'conversation.' . $this->message->conversation_id
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'body' => $this->message->body,
            'attachment_path' => $this->message->attachment_path,
            'attachment_name' => $this->message->attachment_name,
            'attachment_mime' => $this->message->attachment_mime,
            'attachment_size' => $this->message->attachment_size,
            'attachment_url' => $this->message->attachment_url,
            'read_at' => $this->message->read_at,
            'created_at' => $this->message->created_at,
            'updated_at' => $this->message->updated_at,
            'sender' => $this->message->load('sender')->sender,
        ];
    }
}