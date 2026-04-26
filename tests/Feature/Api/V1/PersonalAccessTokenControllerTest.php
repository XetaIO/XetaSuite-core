<?php

declare(strict_types=1);

use Laravel\Sanctum\PersonalAccessToken;
use XetaSuite\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('index', function () {
    test('authenticated user can list their tokens', function () {
        $this->user->createToken('test-token');

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/tokens');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']]]);
    });

    test('guest cannot list tokens', function () {
        $response = $this->getJson('/api/v1/tokens');

        $response->assertUnauthorized();
    });
});

describe('store', function () {
    test('authenticated user can create a token', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/tokens', [
                'name' => 'My MCP Token',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['token' => ['id', 'name', 'expires_at', 'created_at'], 'plain_text_token']);

        expect(PersonalAccessToken::where('name', 'My MCP Token')->exists())->toBeTrue();
    });

    test('authenticated user can create a token with expiry date', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/tokens', [
                'name' => 'Expiring Token',
                'expires_at' => now()->addYear()->toDateTimeString(),
            ]);

        $response->assertCreated();
        expect($response->json('token.expires_at'))->not->toBeNull();
    });

    test('token name is required', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/tokens', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    test('expires_at must be in the future', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/tokens', [
                'name' => 'Bad Token',
                'expires_at' => now()->subDay()->toDateTimeString(),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_at']);
    });

    test('guest cannot create a token', function () {
        $response = $this->postJson('/api/v1/tokens', ['name' => 'token']);

        $response->assertUnauthorized();
    });
});

describe('destroy', function () {
    test('authenticated user can revoke their own token', function () {
        $token = $this->user->createToken('to-revoke');
        $tokenId = $token->accessToken->id;

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/tokens/{$tokenId}");

        $response->assertNoContent();
        expect(PersonalAccessToken::find($tokenId))->toBeNull();
    });

    test('user cannot revoke another user token', function () {
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('other-token');
        $tokenId = $token->accessToken->id;

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/tokens/{$tokenId}");

        $response->assertNotFound();
        expect(PersonalAccessToken::find($tokenId))->not->toBeNull();
    });

    test('guest cannot revoke a token', function () {
        $token = $this->user->createToken('token');
        $tokenId = $token->accessToken->id;

        $response = $this->deleteJson("/api/v1/tokens/{$tokenId}");

        $response->assertUnauthorized();
    });
});
