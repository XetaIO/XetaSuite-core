<?php

declare(strict_types=1);

namespace XetaSuite\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use XetaSuite\Http\Requests\V1\User\UpdatePasswordRequest;

class UserPasswordController extends Controller
{
    /**
     * Update the authenticated user's password.
     * Revokes all other Personal Access Tokens for security.
     */
    public function __invoke(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        // Revoke all other tokens (keep the current one if used) to log out other devices.
        $currentToken = $user->currentAccessToken();
        $currentTokenId = $currentToken instanceof PersonalAccessToken ? $currentToken->getKey() : null;
        $user->tokens()
            ->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->delete();

        return response()->json([
            'message' => __('user.password_updated'),
        ]);
    }
}
