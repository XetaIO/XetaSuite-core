<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use XetaSuite\Contracts\Ai\LlmProvider;
use XetaSuite\Exceptions\Ai\LlmRateLimitException;
use XetaSuite\Models\Site;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->site = Site::factory()->create(['is_headquarters' => false]);

    $role = Role::create(['name' => 'member', 'guard_name' => 'web']);
    $this->user = createUserOnRegularSite($this->site, $role);
});

describe('chat', function (): void {
    test('guest cannot access voice chat', function (): void {
        $response = $this->postJson('/api/v1/voice/chat', [
            'message' => 'Bonjour',
            'history' => [],
        ]);

        $response->assertUnauthorized();
    });

    test('authenticated user gets a reply from the LLM', function (): void {
        $mock = $this->mock(LlmProvider::class);
        $mock->shouldReceive('chat')->once()->andReturn([
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['role' => 'assistant', 'content' => 'Bonjour !'],
            ]],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/voice/chat', [
                'message' => 'Bonjour',
                'history' => [],
            ]);

        $response->assertOk()->assertJson(['reply' => 'Bonjour !']);
    });

    test('returns 429 when LLM rate limit is reached', function (): void {
        $mock = $this->mock(LlmProvider::class);
        $mock->shouldReceive('chat')->once()->andThrow(new LlmRateLimitException());

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/voice/chat', [
                'message' => 'Bonjour',
                'history' => [],
            ]);

        $response->assertStatus(429)->assertJsonPath('message', 'Trop de demandes. Veuillez patienter quelques instants avant de réessayer.');
    });
});
