<?php

declare(strict_types=1);

namespace XetaSuite\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add baseline security headers to every HTTP response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = [
            // Prevent MIME sniffing (important for XSS protection).
            'X-Content-Type-Options' => 'nosniff',
            // Prevent clickjacking by disallowing framing.
            'X-Frame-Options' => 'DENY',
            // Referrer policy to limit referrer leakage while maintaining analytics.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Permissions policy to control access to powerful features.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ];

        // HSTS only when served over HTTPS (avoids breaking local http dev).
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
