<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
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
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $this->messageService->send(
            $conversation,
            $request->user(),
            $validated['body']
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
        $messages = $this->messageService->list(
            $conversation,
            $request->user()
        );

        return response()->json([
            'message' => 'Messages retrieved.',
            'data' => $messages,
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

        return response()->json([
            'message' => 'Messages marked as read.',
            'data' => [
                'updated_count' => $count,
            ],
        ]);
}
}
