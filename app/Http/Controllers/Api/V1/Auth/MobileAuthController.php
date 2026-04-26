<?php

declare(strict_types=1);

namespace XetaSuite\Http\Controllers\Api\V1\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use XetaSuite\Http\Controllers\Api\V1\Controller;
use XetaSuite\Http\Requests\V1\Auth\MobileLoginRequest;
use XetaSuite\Models\User;

class MobileAuthController extends Controller
{
    /**
     * Authenticate a mobile user and return a Personal Access Token.
     * This endpoint does not require CSRF protection (stateless Bearer auth).
     */
    public function login(MobileLoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        $token = $user->createToken($request->validated('device_name'), ['*']);

        return response()->json([
            'plain_text_token' => $token->plainTextToken,
            'token' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'expires_at' => $token->accessToken->expires_at,
                'created_at' => $token->accessToken->created_at,
            ],
        ], 201);
    }
}
