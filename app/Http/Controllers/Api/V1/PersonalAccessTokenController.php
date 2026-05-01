<?php

declare(strict_types=1);

namespace XetaSuite\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use XetaSuite\Http\Requests\V1\PersonalAccessToken\StorePersonalAccessTokenRequest;

class PersonalAccessTokenController extends Controller
{
    /**
     * List the authenticated user's Personal Access Tokens.
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
            ]);

        return response()->json(['data' => $tokens]);
    }

    /**
     * Create a new Personal Access Token.
     * The plain-text token is returned only once.
     */
    public function store(StorePersonalAccessTokenRequest $request): JsonResponse
    {
        $token = $request->user()->createToken(
            $request->validated('name'),
            ['*'],
            $request->validated('expires_at') ? \Carbon\Carbon::parse($request->validated('expires_at')) : null,
        );

        return response()->json([
            'token' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'expires_at' => $token->accessToken->expires_at,
                'created_at' => $token->accessToken->created_at,
            ],
            'plain_text_token' => $token->plainTextToken,
        ], 201);
    }

    /**
     * Revoke a Personal Access Token belonging to the authenticated user.
     */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()
            ->where('id', $tokenId)
            ->delete();

        abort_if(! $deleted, 404, 'Token not found.');

        return response()->json(status: 204);
    }
}
