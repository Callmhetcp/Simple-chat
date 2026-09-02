<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'conversation.{conversation}',
    function ($user, Conversation $conversation) {
        return $conversation->users()
            ->whereKey($user->id)
            ->exists();
    }
);