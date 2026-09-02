<?php

namespace App\Services;

use App\Events\ReactionAdded;
use App\Events\ReactionRemoved;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReactionService
{
    /**
     * Add a reaction to a message.
     */
    public function add(
        Conversation $conversation,
        Message $message,
        User $user,
        string $type
    ): MessageReaction {
        return DB::transaction(function () use (
            $conversation,
            $message,
            $user,
            $type
        ) {
            $this->authorizeMessage(
                $conversation,
                $message,
                $user
            );

            $reaction = MessageReaction::firstOrCreate([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'type' => $type,
            ]);

            if ($reaction->wasRecentlyCreated) {
                broadcast(new ReactionAdded(
                    $reaction->fresh()->load('user', 'message')
                ))->toOthers();
            }

            return $reaction->fresh()->load('user', 'message');
        });
    }

    /**
     * Remove a reaction from a message.
     */
    public function remove(
        Conversation $conversation,
        Message $message,
        User $user,
        string $type
    ): void {
        DB::transaction(function () use (
            $conversation,
            $message,
            $user,
            $type
        ) {
            $this->authorizeMessage(
                $conversation,
                $message,
                $user
            );

            $reaction = MessageReaction::query()
                ->where('message_id', $message->id)
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->first();

            if (! $reaction) {
                return;
            }

            $conversationId = $conversation->id;
            $messageId = $reaction->message_id;
            $userId = $reaction->user_id;
            $reactionType = $reaction->type;

            $reaction->delete();

            broadcast(new ReactionRemoved(
                $conversationId,
                $messageId,
                $userId,
                $reactionType
            ))->toOthers();
        });
    }

    /**
     * Ensure the user can access this message.
     */
    protected function authorizeMessage(
        Conversation $conversation,
        Message $message,
        User $user
    ): void {
        $isParticipant = $conversation->users()
            ->whereKey($user->id)
            ->exists();

        if (! $isParticipant) {
            throw new AuthorizationException(
                'You are not a member of this conversation.'
            );
        }

        if ($message->conversation_id !== $conversation->id) {
            throw new AuthorizationException(
                'This message does not belong to this conversation.'
            );
        }
    }
}
