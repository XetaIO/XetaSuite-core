<?php

declare(strict_types=1);

namespace XetaSuite\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use XetaSuite\Http\Requests\V1\Assistant\ChatRequest;
use XetaSuite\Services\Assistant\AssistantConversationService;

class AssistantChatController extends Controller
{
    public function __construct(private readonly AssistantConversationService $conversationService)
    {
    }

    /**
     * Process an assistant chat message with the agentic tool-calling loop.
     */
    public function chat(ChatRequest $request): JsonResponse
    {
        $payload = $this->conversationService->handle(
            $request->user(),
            (string) $request->input('message'),
            $request->input('conversation_id'),
        );

        return response()->json($payload);
    }
}
