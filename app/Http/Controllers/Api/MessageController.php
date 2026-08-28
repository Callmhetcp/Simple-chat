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
}
