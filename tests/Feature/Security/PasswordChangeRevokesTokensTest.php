<?php

declare(strict_types=1);

use XetaSuite\Models\User;

test('updating password revokes all personal access tokens (web stateful context)', function () {
    $user = User::factory()->create();

    $user->createToken('Device A', ['mobile']);
    $user->createToken('Device B', ['mobile']);

    expect($user->tokens()->count())->toBe(2);

    $response = $this->actingAs($user)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'NewStrongPassw0rd!',
            'password_confirmation' => 'NewStrongPassw0rd!',
        ]);

    $response->assertOk();

    // In stateful context, currentAccessToken() is null so all PATs are revoked.
    expect($user->tokens()->count())->toBe(0);
});
