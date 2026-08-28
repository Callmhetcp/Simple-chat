<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class MessageService
{
    public function send(
        Conversation $conversation,
        User $sender,
        string $body
    ): Message {
        return DB::transaction(function () use ($conversation, $sender, $body) {
            $isParticipant = $conversation->users()
                ->whereKey($sender->id)
                ->exists();

            if (! $isParticipant) {
                throw new AuthorizationException(
                    'You are not a member of this conversation.'
                );
            }

            return $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $body,
            ]);
        });
    }
}
