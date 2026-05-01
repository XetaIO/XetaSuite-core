<?php

declare(strict_types=1);

use XetaSuite\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('mobile-login is throttled after 5 failed attempts for the same email', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/mobile-login', [
            'email' => $this->user->email,
            'password' => 'wrong',
            'device_name' => 'iPhone',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/mobile-login', [
        'email' => $this->user->email,
        'password' => 'wrong',
        'device_name' => 'iPhone',
    ])->assertStatus(429);
});

test('mobile-login PAT has mobile ability and an expires_at in the future', function () {
    $response = $this->postJson('/api/v1/auth/mobile-login', [
        'email' => $this->user->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ]);

    $response->assertCreated();

    expect($response->json('token.expires_at'))->not->toBeNull();

    $token = $this->user->tokens()->latest()->first();
    expect($token->abilities)->toBe(['mobile']);
    expect($token->expires_at)->not->toBeNull();
    expect($token->expires_at->isFuture())->toBeTrue();
});
