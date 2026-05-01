<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use XetaSuite\Contracts\Ai\LlmProvider;
use XetaSuite\Models\Site;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->site = Site::factory()->create(['is_headquarters' => false]);
    Permission::findOrCreate('assistant.use', 'web');
    $role = Role::create(['name' => 'rate-test', 'guard_name' => 'web']);
    $role->syncPermissions(['assistant.use']);
    $this->user = createUserOnRegularSite($this->site, $role);

    RateLimiter::clear('assistant-chat:'.$this->user->id);

    $this->mock(LlmProvider::class)
        ->shouldReceive('chat')
        ->andReturn([
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['role' => 'assistant', 'content' => 'OK'],
            ]],
        ]);
});

test('the assistant chat endpoint enforces a per-minute rate limit', function (): void {
    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($this->user)
            ->postJson('/api/v1/assistant/chat', ['message' => 'Hi', 'history' => []])
            ->assertOk();
    }

    $this->actingAs($this->user)
        ->postJson('/api/v1/assistant/chat', ['message' => 'Hi', 'history' => []])
        ->assertStatus(429);
});
