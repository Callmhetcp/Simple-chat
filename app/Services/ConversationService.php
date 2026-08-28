<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function findOrCreatePrivateConversation(
        User $user,
        User $otherUser
    ): Conversation {
        return DB::transaction(function () use ($user, $otherUser) {
            if ($user->is($otherUser)) {
                throw new \InvalidArgumentException(
                    'A user cannot start a conversation with themselves.'
                );
            }

            $conversation = $user->conversations()
                ->whereHas('users', function ($query) use ($otherUser) {
                    $query->whereKey($otherUser->id);
                })
                ->withCount('users')
                ->having('users_count', 2)
                ->first();

            if ($conversation) {
                return $conversation;
            }

            $conversation = Conversation::create();

            $conversation->users()->attach([
                $user->id,
                $otherUser->id,
            ]);

            return $conversation;
        });
    }
}
