<?php

declare(strict_types=1);

namespace XetaSuite\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use XetaSuite\Http\Resources\V1\Assistant\AssistantConversationResource;
use XetaSuite\Models\AssistantConversation;

class AssistantConversationController extends Controller
{
    /**
     * List the user's conversations on the current site, most recent first.
     * Accepts ?limit=3 (max 10) for the mobile dropdown.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AssistantConversation::class);

        $user = $request->user();
        $limit = (int) $request->integer('limit', 3);
        $limit = max(1, min($limit, 10));

        $conversations = AssistantConversation::query()
            ->forUserOnSite($user->id, $user->current_site_id)
            ->limit($limit)
            ->get();

        return AssistantConversationResource::collection($conversations);
    }

    /**
     * Show one conversation with its messages.
     */
    public function show(AssistantConversation $assistantConversation): AssistantConversationResource
    {
        $this->authorize('view', $assistantConversation);

        $assistantConversation->load('messages');

        return new AssistantConversationResource($assistantConversation);
    }

    /**
     * Create an empty conversation for the current user/site.
     * Useful when the client wants to explicitly start a new thread before
     * sending the first message.
     */
    public function store(Request $request): AssistantConversationResource
    {
        $this->authorize('create', AssistantConversation::class);

        $user = $request->user();

        $conversation = AssistantConversation::create([
            'user_id' => $user->id,
            'site_id' => $user->current_site_id,
            'provider' => (string) config('services.ai.provider', 'groq'),
            'model' => null,
            'title' => null,
        ]);

        $conversation->setRelation('messages', collect());

        return new AssistantConversationResource($conversation);
    }

    public function destroy(AssistantConversation $assistantConversation): JsonResponse
    {
        $this->authorize('delete', $assistantConversation);

        $assistantConversation->delete();

        return response()->json(null, 204);
    }
}
