<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use XetaSuite\Contracts\Ai\LlmProvider;
use XetaSuite\Models\AssistantConversation;
use XetaSuite\Models\AssistantConversationMessage;
use XetaSuite\Models\Site;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->site = Site::factory()->create(['is_headquarters' => false]);

    Permission::findOrCreate('assistant.use', 'web');
    $role = Role::create(['name' => 'member', 'guard_name' => 'web']);
    $role->syncPermissions(['assistant.use']);

    $this->user = createUserOnRegularSite($this->site, $role);
    $this->otherUser = createUserOnRegularSite($this->site, $role);
});

describe('chat endpoint with conversations', function (): void {
    test('a chat without conversation_id creates a new conversation', function (): void {
        $this->mock(LlmProvider::class)
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['role' => 'assistant', 'content' => 'Bonjour !'],
                ]],
            ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/assistant/chat', ['message' => 'Salut'])
            ->assertOk();

        $response->assertJsonPath('reply', 'Bonjour !');
        $conversationId = $response->json('conversation_id');
        expect($conversationId)->not->toBeNull();

        $conversation = AssistantConversation::find($conversationId);
        expect($conversation->user_id)->toBe($this->user->id);
        expect($conversation->site_id)->toBe($this->site->id);
        expect($conversation->title)->toBe('Salut');
        expect($conversation->messages()->count())->toBe(2);
    });

    test('a chat with conversation_id appends to the existing thread', function (): void {
        $conversation = AssistantConversation::create([
            'user_id' => $this->user->id,
            'site_id' => $this->site->id,
            'title' => 'Existing',
        ]);
        AssistantConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Premier message',
            'created_at' => now()->subMinute(),
        ]);
        AssistantConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Première réponse',
            'created_at' => now()->subMinute(),
        ]);

        $this->mock(LlmProvider::class)
            ->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['role' => 'assistant', 'content' => 'Suite'],
                ]],
            ]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/assistant/chat', [
                'message' => 'Suite ?',
                'conversation_id' => $conversation->id,
            ])
            ->assertOk()
            ->assertJsonPath('conversation_id', $conversation->id);

        expect($conversation->fresh()->messages()->count())->toBe(4);
        expect(AssistantConversation::count())->toBe(1);
    });

    test('cannot send a chat to a conversation owned by another user', function (): void {
        $conversation = AssistantConversation::create([
            'user_id' => $this->otherUser->id,
            'site_id' => $this->site->id,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/assistant/chat', [
                'message' => 'Hack',
                'conversation_id' => $conversation->id,
            ])
            ->assertForbidden();
    });
});

describe('list conversations', function (): void {
    test('returns the user conversations on the current site, most recent first', function (): void {
        $older = AssistantConversation::create([
            'user_id' => $this->user->id,
            'site_id' => $this->site->id,
            'title' => 'Older',
            'last_message_at' => now()->subDays(2),
        ]);
        $newer = AssistantConversation::create([
            'user_id' => $this->user->id,
            'site_id' => $this->site->id,
            'title' => 'Newer',
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/assistant/conversations')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        expect($ids)->toBe([$newer->id, $older->id]);
    });

    test('respects the limit query parameter', function (): void {
        for ($i = 0; $i < 5; $i++) {
            AssistantConversation::create([
                'user_id' => $this->user->id,
                'site_id' => $this->site->id,
                'title' => "C{$i}",
                'last_message_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/assistant/conversations?limit=3')
            ->assertOk();

        expect($response->json('data'))->toHaveCount(3);
    });

    test('does not leak conversations from other users or other sites', function (): void {
        $otherSite = Site::factory()->create(['is_headquarters' => false]);

        AssistantConversation::create([
            'user_id' => $this->otherUser->id,
            'site_id' => $this->site->id,
            'title' => 'Other user',
        ]);
        AssistantConversation::create([
            'user_id' => $this->user->id,
            'site_id' => $otherSite->id,
            'title' => 'Other site',
        ]);
        AssistantConversation::create([
            'user_id' => $this->user->id,
            'site_id' => $this->site->id,
            'title' => 'Mine',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/assistant/conversations')
            ->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->all();
        expect($titles)->toBe(['Mine']);
    });
});

describe('show conversation', function (): void {
    test('returns the conversation with its messages', function (): void {
        $conversation = AssistantConversation::create([
            'user_id' => $this->user->id,
            'site_id' => $this->site->id,
            'title' => 'Hello',
        ]);
        AssistantConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Question',
            'created_at' => now(),
        ]);
        AssistantConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Réponse',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/assistant/conversations/{$conversation->id}")
            ->assertOk();

        expect($response->json('data.messages'))->toHaveCount(2);
        expect($response->json('data.messages.0.role'))->toBe('user');
        expect($response->json('data.messages.1.role'))->toBe('assistant');
    });

    test('cannot view another user conversation', function (): void {
        $conversation = AssistantConversation::create([
            'user_id' => $this->otherUser->id,
            'site_id' => $this->site->id,
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/assistant/conversations/{$conversation->id}")
            ->assertForbidden();
    });
});

describe('delete conversation', function (): void {
    test('removes the conversation and its messages', function (): void {
        $conversation = AssistantConversation::create([
            'user_id' => $this->user->id,
            'site_id' => $this->site->id,
        ]);
        AssistantConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Test',
            'created_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/assistant/conversations/{$conversation->id}")
            ->assertNoContent();

        expect(AssistantConversation::find($conversation->id))->toBeNull();
        expect(AssistantConversationMessage::where('conversation_id', $conversation->id)->count())->toBe(0);
    });

    test('cannot delete another user conversation', function (): void {
        $conversation = AssistantConversation::create([
            'user_id' => $this->otherUser->id,
            'site_id' => $this->site->id,
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/assistant/conversations/{$conversation->id}")
            ->assertForbidden();

        expect(AssistantConversation::find($conversation->id))->not->toBeNull();
    });
});

describe('store conversation', function (): void {
    test('creates an empty conversation for the current user', function (): void {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/assistant/conversations')
            ->assertCreated();

        $id = $response->json('data.id');
        $row = AssistantConversation::find($id);
        expect($row->user_id)->toBe($this->user->id);
        expect($row->site_id)->toBe($this->site->id);
        expect($row->messages()->count())->toBe(0);
    });
});
