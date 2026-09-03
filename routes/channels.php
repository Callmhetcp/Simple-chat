<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'conversation.{conversation}',
    function ($user, Conversation $conversation) {
        $isParticipant = $conversation->users()
            ->whereKey($user->id)
            ->exists();

        if (! $isParticipant) {
            return false;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
        ];
    }
);