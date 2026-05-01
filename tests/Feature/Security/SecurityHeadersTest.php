<?php

declare(strict_types=1);

test('security headers are present on every response', function () {
    $response = $this->get('/up');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy');
});

test('HSTS header is not set on non-secure (http) responses', function () {
    $response = $this->get('/up');

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});
