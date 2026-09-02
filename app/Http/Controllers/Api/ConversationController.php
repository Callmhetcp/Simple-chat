<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $conversations = $this->conversationService
            ->listForUser($request->user());

        return response()->json([
            'message' => 'Conversations retrieved.',
            'data' => $conversations,
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        $search = $validated['search'];

        $users = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->select([
                'id',
                'name',
                'username',
                'email',
            ])
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'message' => 'Users retrieved.',
            'data' => $users,
        ]);
    }
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $otherUser = User::findOrFail($validated['user_id']);

        try {
            $conversation = $this->conversationService
                ->findOrCreatePrivateConversation($user, $otherUser);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Conversation ready.',
            'conversation' => $conversation->load('users'),
        ], 201);
    }

    public function show(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $conversation = $this->conversationService->findForUser(
            $conversation,
            $request->user()
        );

        return response()->json([
            'message' => 'Conversation retrieved.',
            'data' => $conversation,
        ]);
    }
}
