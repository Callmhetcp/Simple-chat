<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\MessagesRead;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        protected MessageService $messageService
    ) {
    }

    public function store(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'max:102400', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mkv,webm,pdf,doc,docx,txt'],
        ]);

        $message = $this->messageService->send(
            $conversation,
            $request->user(),
            $validated['body'] ?? null,
            $request->file('attachment')
        );

        return response()->json([
            'message' => 'Message sent.',
            'data' => $message->load('sender'),
        ], 201);
    }

    public function index(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $messages = $this->messageService->list(
            $conversation,
            $request->user(),
            20,
            $validated['search'] ?? null
        );

        // Load reactions and the user who made each reaction.
        $messages->getCollection()->load('reactions.user');

        return response()->json([
            'message' => 'Messages retrieved.',
            'data' => $messages,
        ]);
    }

    public function update(
        Request $request,
        Conversation $conversation,
        Message $message
    ): JsonResponse {
        if ($message->conversation_id !== $conversation->id) {
            return response()->json([
                'message' => 'Message does not belong to this conversation.',
            ], 404);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $updatedMessage = $this->messageService->update(
            $message,
            $request->user(),
            $validated['body']
        );

        return response()->json([
            'message' => 'Message updated.',
            'data' => $updatedMessage,
        ]);
    }

    public function destroy(
        Request $request,
        Conversation $conversation,
        Message $message
    ): JsonResponse {
        if ($message->conversation_id !== $conversation->id) {
            return response()->json([
                'message' => 'Message does not belong to this conversation.',
            ], 404);
        }

        $this->messageService->delete(
            $message,
            $request->user()
        );

        return response()->json([
            'message' => 'Message deleted.',
        ]);
    }

    public function markAsRead(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $count = $this->messageService->markAsRead(
            $conversation,
            $request->user()
        );

        if ($count > 0) {
            broadcast(new MessagesRead(
                $conversation,
                $request->user()->id
            ))->toOthers();
        }

        return response()->json([
            'message' => 'Messages marked as read.',
            'data' => [
                'updated_count' => $count,
            ],
        ]);
    }
}
