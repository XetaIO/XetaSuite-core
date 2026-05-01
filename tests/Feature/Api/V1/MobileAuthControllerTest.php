<?php

declare(strict_types=1);

use XetaSuite\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('mobile-login', function () {
    test('returns a PAT with valid credentials', function () {
        $response = $this->postJson('/api/v1/auth/mobile-login', [
            'email' => $this->user->email,
            'password' => 'password',
            'device_name' => 'iPhone de Test',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'plain_text_token',
                'token' => ['id', 'name', 'expires_at', 'created_at'],
            ]);

        expect($response->json('token.name'))->toBe('iPhone de Test');
        expect($response->json('plain_text_token'))->toBeString()->not->toBeEmpty();
    });

    test('returns 401 with invalid password', function () {
        $response = $this->postJson('/api/v1/auth/mobile-login', [
            'email' => $this->user->email,
            'password' => 'wrong-password',
            'device_name' => 'iPhone de Test',
        ]);

        $response->assertUnauthorized()
            ->assertJson(['message' => 'The provided credentials are incorrect.']);
    });

    test('returns 401 with unknown email', function () {
        $response = $this->postJson('/api/v1/auth/mobile-login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
            'device_name' => 'iPhone de Test',
        ]);

        $response->assertUnauthorized();
    });

    test('returns 422 when email is missing', function () {
        $response = $this->postJson('/api/v1/auth/mobile-login', [
            'password' => 'password',
            'device_name' => 'iPhone de Test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    test('returns 422 when device_name is missing', function () {
        $response = $this->postJson('/api/v1/auth/mobile-login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['device_name']);
    });

    test('returns 422 when password is missing', function () {
        $response = $this->postJson('/api/v1/auth/mobile-login', [
            'email' => $this->user->email,
            'device_name' => 'iPhone de Test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

    test('the PAT can be used to call authenticated endpoints', function () {
        $loginResponse = $this->postJson('/api/v1/auth/mobile-login', [
            'email' => $this->user->email,
            'password' => 'password',
            'device_name' => 'iPhone de Test',
        ]);

        $token = $loginResponse->json('plain_text_token');

        $userResponse = $this->withToken($token)
            ->getJson('/api/v1/auth/user');

        $userResponse->assertOk()
            ->assertJsonPath('data.email', $this->user->email);
    });

    test('guest cannot access authenticated endpoint without token', function () {
        $this->getJson('/api/v1/auth/user')
            ->assertUnauthorized();
    });
});
