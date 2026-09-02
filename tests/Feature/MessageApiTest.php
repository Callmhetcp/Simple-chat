<?php

use App\Models\Conversation;
use App\Models\User;

it('allows an authenticated user to update their own message in a conversation', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'username' => 'alice',
    ]);

    $otherUser = User::factory()->create([
        'username' => 'bob',
    ]);

    $conversation = Conversation::create();
    $conversation->users()->attach([$user->id, $otherUser->id]);

    $message = $conversation->messages()->create([
        'sender_id' => $user->id,
        'body' => 'Original message',
    ]);

    $response = $this
        ->actingAs($user, 'sanctum')
        ->patchJson("/api/conversations/{$conversation->id}/messages/{$message->id}", [
            'body' => 'Updated message',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Message updated.')
        ->assertJsonPath('data.body', 'Updated message');

    $this->assertDatabaseHas('messages', [
        'id' => $message->id,
        'body' => 'Updated message',
    ]);
});
