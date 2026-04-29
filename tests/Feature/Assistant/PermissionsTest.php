<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use XetaSuite\Contracts\Ai\LlmProvider;
use XetaSuite\Models\Item;
use XetaSuite\Models\Site;
use XetaSuite\Services\Assistant\AssistantToolRegistry;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->site = Site::factory()->create(['is_headquarters' => false]);
    Permission::findOrCreate('assistant.use', 'web');
    Permission::findOrCreate('item.viewAny', 'web');
    Permission::findOrCreate('item.delete', 'web');
});

test('user without assistant.use cannot access the chat endpoint', function (): void {
    $role = Role::create(['name' => 'no-assistant', 'guard_name' => 'web']);
    $user = createUserOnRegularSite($this->site, $role);

    $response = $this->actingAs($user)->postJson('/api/v1/assistant/chat', [
        'message' => 'Bonjour',
        'history' => [],
    ]);

    $response->assertForbidden();
});

test('user without current_site_id cannot access the chat endpoint', function (): void {
    Permission::findOrCreate('assistant.use', 'web');
    $role = Role::create(['name' => 'assistant-only', 'guard_name' => 'web']);
    $role->syncPermissions(['assistant.use']);
    $user = createUserOnRegularSite($this->site, $role);
    $user->update(['current_site_id' => null]);
    $user->refresh();

    $response = $this->actingAs($user)->postJson('/api/v1/assistant/chat', [
        'message' => 'Bonjour',
        'history' => [],
    ]);

    $response->assertForbidden();
});

test('definitionsForLlm only includes tools the user has permission for', function (): void {
    $role = Role::create(['name' => 'limited', 'guard_name' => 'web']);
    $role->syncPermissions(['assistant.use', 'item.viewAny']);
    $user = createUserOnRegularSite($this->site, $role);

    $registry = app(AssistantToolRegistry::class);
    $definitions = $registry->definitionsForLlm($user);

    $names = array_map(fn ($def) => $def['function']['name'], $definitions);

    expect($names)->toContain('list_items');
    expect($names)->not->toContain('list_incidents');
    expect($names)->not->toContain('delete_item');
});

test('execute returns permission_denied for tools the user cannot use', function (): void {
    $role = Role::create(['name' => 'no-delete', 'guard_name' => 'web']);
    $role->syncPermissions(['assistant.use', 'item.viewAny']);
    $user = createUserOnRegularSite($this->site, $role);
    $item = Item::factory()->forSite($this->site)->create();

    $registry = app(AssistantToolRegistry::class);
    $result = $registry->execute($user, 'delete_item', ['item_id' => $item->id]);

    expect($result)->toContain('permission_denied');
    expect(Item::find($item->id))->not->toBeNull();
});

test('LLM only receives tool definitions allowed for the user', function (): void {
    $role = Role::create(['name' => 'view-items-only', 'guard_name' => 'web']);
    $role->syncPermissions(['assistant.use', 'item.viewAny']);
    $user = createUserOnRegularSite($this->site, $role);

    $capturedTools = null;
    $mock = $this->mock(LlmProvider::class);
    $mock->shouldReceive('chat')
        ->once()
        ->andReturnUsing(function (array $messages, array $tools) use (&$capturedTools): array {
            $capturedTools = $tools;

            return [
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['role' => 'assistant', 'content' => 'OK'],
                ]],
            ];
        });

    $this->actingAs($user)->postJson('/api/v1/assistant/chat', [
        'message' => 'Liste les articles',
        'history' => [],
    ])->assertOk();

    $names = array_map(fn ($def) => $def['function']['name'], $capturedTools);
    expect($names)->toContain('list_items');
    expect($names)->not->toContain('delete_item');
    expect($names)->not->toContain('list_incidents');
});
