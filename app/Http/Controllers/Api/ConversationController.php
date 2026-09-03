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

    public function friends(Request $request): JsonResponse
    {
        $friends = $request->user()->friendships()
            ->where('status', 'accepted')
            ->with('friend:id,name,username,email')
            ->get()
            ->pluck('friend');

        return response()->json([
            'message' => 'Friends retrieved.',
            'data' => $friends->values(),
        ]);
    }

    public function addFriend(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return response()->json([
                'message' => 'You cannot add yourself as a friend.',
            ], 422);
        }

        $friendship = $request->user()->friendships()->firstOrCreate(
            ['friend_id' => $user->id],
            ['status' => 'accepted']
        );

        return response()->json([
            'message' => 'Friend added.',
            'friend' => $friendship->load('friend')->friend,
        ], 201);
    }
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:user_ids'],
            'name' => ['nullable', 'string', 'max:100', 'required_with:user_ids'],
            'user_ids' => ['nullable', 'array', 'min:1', 'required_without:user_id'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $user = $request->user();

        if (! empty($validated['user_ids'])) {
            $memberIds = array_values(array_filter(
                $validated['user_ids'],
                fn (int $memberId): bool => $memberId !== $user->id
            ));

            $conversation = $this->conversationService->createGroup(
                $user,
                $validated['name'],
                $memberIds
            );

            return response()->json([
                'message' => 'Group conversation created.',
                'conversation' => $conversation->load('users'),
            ], 201);
        }

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

    public function addMembers(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $conversation = $this->conversationService->addGroupMembers(
            $conversation,
            $request->user(),
            $validated['user_ids']
        );

        return response()->json([
            'message' => 'Group members added.',
            'conversation' => $conversation,
        ]);
    }
}
