<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ReactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    public function __construct(
        protected ReactionService $reactionService
    ) {
    }

    public function store(
        Request $request,
        Conversation $conversation,
        Message $message
    ): JsonResponse {
        $validated = $request->validate([
            'type' => [
                'required',
                'string',
                'in:❤️,😂,😮,😢,😡,👍',
            ],
        ]);

        $reaction = $this->reactionService->add(
            $conversation,
            $message,
            $request->user(),
            $validated['type']
        );

        return response()->json([
            'message' => 'Reaction added.',
            'data' => $reaction,
        ], 201);
    }

    public function destroy(
        Request $request,
        Conversation $conversation,
        Message $message
    ): JsonResponse {
        $validated = $request->validate([
            'type' => [
                'required',
                'string',
                'in:❤️,😂,😮,😢,😡,👍',
            ],
        ]);

        $this->reactionService->remove(
            $conversation,
            $message,
            $request->user(),
            $validated['type']
        );

        return response()->json([
            'message' => 'Reaction removed.',
        ]);
    }
}
