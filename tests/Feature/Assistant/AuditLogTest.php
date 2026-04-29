<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use XetaSuite\Contracts\Ai\LlmProvider;
use XetaSuite\Models\AssistantConversation;
use XetaSuite\Models\AssistantConversationMessage;
use XetaSuite\Models\Item;
use XetaSuite\Models\Site;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->site = Site::factory()->create(['is_headquarters' => false]);
    foreach (['assistant.use', 'item.viewAny'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $role = Role::create(['name' => 'audit-test', 'guard_name' => 'web']);
    $role->syncPermissions(['assistant.use', 'item.viewAny']);
    $this->user = createUserOnRegularSite($this->site, $role);
});

test('a conversation is logged after a simple chat', function (): void {
    $this->mock(LlmProvider::class)
        ->shouldReceive('chat')
        ->once()
        ->andReturn([
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['role' => 'assistant', 'content' => 'Bonjour !'],
            ]],
        ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/assistant/chat', ['message' => 'Salut', 'history' => []])
        ->assertOk()
        ->assertJson(['reply' => 'Bonjour !']);

    expect(AssistantConversation::count())->toBe(1);

    $row = AssistantConversation::first();
    expect($row->user_id)->toBe($this->user->id);
    expect($row->site_id)->toBe($this->site->id);
    expect($row->provider)->toBe(config('services.ai.provider'));
    expect($row->title)->toBe('Salut');

    $messages = AssistantConversationMessage::where('conversation_id', $row->id)
        ->orderBy('id')
        ->get();
    expect($messages)->toHaveCount(2);
    expect($messages[0]->role)->toBe('user');
    expect($messages[0]->content)->toBe('Salut');
    expect($messages[1]->role)->toBe('assistant');
    expect($messages[1]->content)->toBe('Bonjour !');
    expect($messages[1]->iteration)->toBe(1);
});

test('tool calls are recorded in the audit log', function (): void {
    Item::factory()->forSite($this->site)->create();

    $this->mock(LlmProvider::class)
        ->shouldReceive('chat')
        ->twice()
        ->andReturn(
            [
                'choices' => [[
                    'finish_reason' => 'tool_calls',
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'list_items',
                                'arguments' => '{}',
                            ],
                        ]],
                    ],
                ]],
            ],
            [
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['role' => 'assistant', 'content' => 'Voici les articles.'],
                ]],
            ],
        );

    $this->actingAs($this->user)
        ->postJson('/api/v1/assistant/chat', ['message' => 'Liste les articles', 'history' => []])
        ->assertOk();

    $row = AssistantConversation::first();
    expect($row)->not->toBeNull();

    $assistantMessage = AssistantConversationMessage::where('conversation_id', $row->id)
        ->where('role', 'assistant')
        ->first();
    expect($assistantMessage)->not->toBeNull();
    expect($assistantMessage->iteration)->toBe(2);
    expect($assistantMessage->tool_calls)->toHaveCount(1);
    expect($assistantMessage->tool_calls[0]['name'])->toBe('list_items');
});
