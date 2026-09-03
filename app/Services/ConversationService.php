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
        return DB::transaction(function () use ($user, $otherUser): Conversation {
            if ($user->is($otherUser)) {
                /** @var \Throwable $error */
                $error = new \InvalidArgumentException(
                    'A user cannot start a conversation with themselves.'
                );

                throw $error;
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

    public function createGroup(
        User $creator,
        string $name,
        array $memberIds
    ): Conversation {
        return DB::transaction(function () use ($creator, $name, $memberIds): Conversation {
            $memberIds = array_values(array_unique([
                $creator->id,
                ...$memberIds,
            ]));

            $friendIds = $creator->friendships()
                ->where('status', 'accepted')
                ->whereIn('friend_id', $memberIds)
                ->pluck('friend_id')
                ->all();

            if (count($friendIds) !== count($memberIds) - 1) {
                throw new AuthorizationException(
                    'You can only add users from your friend list.'
                );
            }

            $conversation = Conversation::create([
                'name' => $name,
            ]);

            $conversation->users()->attach($memberIds);

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

    public function addGroupMembers(
        Conversation $conversation,
        User $user,
        array $memberIds
    ): Conversation {
        if (! $conversation->name) {
            throw new AuthorizationException(
                'Members can only be added to group conversations.'
            );
        }

        if (! $conversation->users()->whereKey($user->id)->exists()) {
            throw new AuthorizationException(
                'You are not a member of this conversation.'
            );
        }

        $memberIds = array_values(array_unique($memberIds));
        $friendIds = $user->friendships()
            ->where('status', 'accepted')
            ->whereIn('friend_id', $memberIds)
            ->pluck('friend_id')
            ->all();

        if (count($friendIds) !== count($memberIds)) {
            throw new AuthorizationException(
                'You can only add users from your friend list.'
            );
        }

        $conversation->users()->syncWithoutDetaching($memberIds);

        return $conversation->load('users');
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