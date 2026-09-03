<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

class MessageService
{
    public function send(
        Conversation $conversation,
        User $sender,
        ?string $body,
        ?UploadedFile $attachment = null
    ): Message {
        return DB::transaction(function () use ($conversation, $sender, $body, $attachment) {
            $isParticipant = $conversation->users()
                ->whereKey($sender->id)
                ->exists();

            if (! $isParticipant) {
                throw new AuthorizationException(
                    'You are not a member of this conversation.'
                );
            }

            $attachmentPath = $attachment?->store('message-attachments', 'public');

            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $body,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachment?->getClientOriginalName(),
                'attachment_mime' => $attachment?->getMimeType(),
                'attachment_size' => $attachment?->getSize(),
            ]);

            broadcast(new MessageSent($message))->toOthers();

            return $message;
        });
    }

    public function list(
        Conversation $conversation,
        User $user,
        int $perPage = 20,
        ?string $search = null
    ): LengthAwarePaginator {
        $isParticipant = $conversation->users()
            ->whereKey($user->id)
            ->exists();

        if (! $isParticipant) {
            throw new AuthorizationException(
                'You are not a member of this conversation.'
            );
        }

        $messages = $conversation->messages()
            ->with('sender')
            ->when($search, function ($query) use ($search) {
                $query->where('body', 'like', '%' . $search . '%');
            })
            ->latest();

        return $messages->paginate($perPage);
    }

    public function markAsRead(
        Conversation $conversation,
        User $user
    ): int {
        $isParticipant = $conversation->users()
            ->whereKey($user->id)
            ->exists();

        if (! $isParticipant) {
            throw new AuthorizationException(
                'You are not a member of this conversation.'
            );
        }

        return $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->update([
                'read_at' => now(),
            ]);
    }

    public function delete(
        Message $message,
        User $user
    ): void {
        if ($message->sender_id !== $user->id) {
            throw new AuthorizationException(
                'You can only delete your own messages.'
            );
        }

        $message->delete();
    }

    public function update(
        Message $message,
        User $user,
        string $body
    ): Message {
        if ($message->sender_id !== $user->id) {
            throw new AuthorizationException(
                'You can only edit your own messages.'
            );
        }

        $message->update([
            'body' => $body,
        ]);

        return $message->fresh()->load('sender');
    }
}