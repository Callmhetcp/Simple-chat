<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Access\AuthorizationException;

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

    public function listForUser(User $user)
    {
        return $user->conversations()
            ->with([
                'users',
                'latestMessage.sender',
            ])
            ->withCount([
                'messages',
                'messages as unread_messages_count' => function ($query) use ($user) {
                    $query->whereNull('read_at')
                        ->where('sender_id', '!=', $user->id);
                },
            ])
            ->orderByDesc(
                DB::raw(
                    '(SELECT MAX(messages.created_at)
                     FROM messages
                     WHERE messages.conversation_id = conversations.id)'
                )
            )
            ->paginate(20);
    }

    public function findForUser(
    Conversation $conversation,
    User $user
): Conversation {
    $isParticipant = $conversation->users()
        ->whereKey($user->id)
        ->exists();

    if (! $isParticipant) {
        throw new AuthorizationException(
            'You are not a member of this conversation.'
        );
    }

    return $conversation
        ->load([
            'users',
            'latestMessage.sender',
        ])
        ->loadCount([
            'messages',
            'messages as unread_messages_count' => function ($query) use ($user) {
                $query->whereNull('read_at')
                    ->where('sender_id', '!=', $user->id);
            },
        ]);
}
}