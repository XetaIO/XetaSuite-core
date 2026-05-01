<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use XetaSuite\Contracts\Ai\LlmProvider;
use XetaSuite\Models\AssistantConversation;
use XetaSuite\Models\AssistantConversationMessage;
use XetaSuite\Models\User;

/**
 * Orchestrates a single assistant chat turn: locate or create the conversation,
 * persist the user message, run the agentic tool-calling loop and persist the
 * assistant reply.
 */
class AssistantConversationService
{
    public function __construct(
        private readonly LlmProvider $llm,
        private readonly AssistantToolRegistry $registry,
        private readonly ArgumentCoercer $coercer,
        private readonly AssistantPromptBuilder $promptBuilder,
    ) {
    }

    /**
     * Handle a chat message for the given user.
     *
     * @return array{reply: string, conversation_id: int}
     *
     * @throws AuthorizationException when the conversation does not belong to
     *                                the authenticated user or is bound to a
     *                                different site.
     */
    public function handle(User $user, string $userMessage, ?int $conversationId): array
    {
        $conversation = $this->resolveConversation($user, $conversationId, $userMessage);

        AssistantConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
            'created_at' => now(),
        ]);

        $messages = [
            ['role' => 'system', 'content' => $this->promptBuilder->buildSystemPrompt($user)],
            ...$this->loadReplayHistory($conversation),
            ['role' => 'user', 'content' => $userMessage],
        ];

        $tools = $this->registry->definitionsForLlm($user);
        $maxIterations = (int) config('services.ai.max_iterations', 8);

        [$reply, $iterations, $toolCallsLog] = $this->runAgenticLoop($user, $messages, $tools, $maxIterations);

        AssistantConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reply,
            'tool_calls' => $toolCallsLog !== [] ? $toolCallsLog : null,
            'iteration' => $iterations,
            'created_at' => now(),
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'provider' => $conversation->provider,
            'model' => $conversation->model,
        ])->save();

        return [
            'reply' => $reply,
            'conversation_id' => $conversation->id,
        ];
    }

    private function resolveConversation(User $user, ?int $conversationId, string $userMessage): AssistantConversation
    {
        if ($conversationId !== null) {
            $conversation = AssistantConversation::query()->findOrFail($conversationId);

            if ($conversation->user_id !== $user->id || $conversation->site_id !== $user->current_site_id) {
                throw new AuthorizationException();
            }

            return $conversation;
        }

        $provider = (string) config('services.ai.provider', 'groq');
        $model = (string) config("services.{$provider}.model", 'unknown');

        return AssistantConversation::create([
            'user_id' => $user->id,
            'site_id' => $user->current_site_id,
            'provider' => $provider,
            'model' => $model,
            'title' => $this->promptBuilder->deriveTitle($userMessage),
        ]);
    }

    /**
     * Load past user/assistant messages to replay to the LLM. Excludes the
     * current turn's user message which the caller appends explicitly.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function loadReplayHistory(AssistantConversation $conversation): array
    {
        $limit = (int) config('assistant.history_replay_limit', 20);

        $rows = DB::table('assistant_conversation_messages')
            ->where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get(['role', 'content']);

        $items = $rows->reverse()->values()->all();

        if ($items !== []) {
            $last = end($items);
            if ($last && $last->role === 'user') {
                array_pop($items);
            }
        }

        return array_map(
            fn ($row) => ['role' => $row->role, 'content' => (string) $row->content],
            $items
        );
    }

    /**
     * Drive the LLM tool-calling loop until a final reply is reached or the
     * iteration budget is exhausted.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{0: string, 1: int, 2: array<int, array<string, mixed>>}
     */
    private function runAgenticLoop(User $user, array $messages, array $tools, int $maxIterations): array
    {
        $iterations = 0;
        $reply = null;
        $toolCallsLog = [];

        for ($i = 0; $i < $maxIterations; $i++) {
            $iterations = $i + 1;
            $data = $this->llm->chat($messages, $tools);
            $choice = $data['choices'][0] ?? null;

            if (! $choice) {
                break;
            }

            $finishReason = $choice['finish_reason'] ?? 'stop';
            $message = $choice['message'] ?? [];

            if ($finishReason === 'stop' || (isset($message['content']) && empty($message['tool_calls']))) {
                $reply = $message['content'] ?? "Je n'ai pas de réponse.";
                break;
            }

            if ($finishReason === 'tool_calls' && ! empty($message['tool_calls'])) {
                $messages[] = $message;

                foreach ($message['tool_calls'] as $toolCall) {
                    $name = $toolCall['function']['name'];
                    $args = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [];
                    $args = $this->coercer->coerce($args);

                    $result = $this->registry->execute($user, $name, $args);

                    $toolCallsLog[] = [
                        'iteration' => $iterations,
                        'name' => $name,
                        'arguments' => $args,
                        'result' => $result,
                    ];

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'name' => $name,
                        'content' => $result,
                    ];
                }

                continue;
            }

            $reply = $message['content'] ?? "Je n'ai pas pu traiter votre demande.";
            break;
        }

        return [$reply ?? "Désolé, je n'ai pas pu terminer cette action.", $iterations, $toolCallsLog];
    }
}
