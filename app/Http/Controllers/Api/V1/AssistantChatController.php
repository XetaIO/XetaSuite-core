<?php

declare(strict_types=1);

namespace XetaSuite\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use XetaSuite\Contracts\Ai\LlmProvider;
use XetaSuite\Http\Requests\V1\Assistant\ChatRequest;
use XetaSuite\Models\AssistantConversation;
use XetaSuite\Models\AssistantConversationMessage;
use XetaSuite\Services\Assistant\ArgumentCoercer;
use XetaSuite\Services\Assistant\AssistantToolRegistry;

class AssistantChatController extends Controller
{
    /**
     * Number of past user/assistant exchanges replayed to the LLM
     * when reconstructing the prompt for an ongoing conversation.
     */
    private const HISTORY_REPLAY_LIMIT = 20;

    public function __construct(
        private readonly LlmProvider $llm,
        private readonly AssistantToolRegistry $registry,
        private readonly ArgumentCoercer $coercer,
    ) {
    }

    /**
     * Process an assistant chat message with agentic tool-calling loop.
     * Persists the exchange to the user's conversation and returns a French reply.
     */
    public function chat(ChatRequest $request): JsonResponse
    {
        $user = $request->user();
        $userMessage = (string) $request->input('message');
        $conversationId = $request->input('conversation_id');

        $conversation = $conversationId !== null
            ? AssistantConversation::query()->findOrFail($conversationId)
            : null;

        if ($conversation !== null) {
            if ($conversation->user_id !== $user->id) {
                throw new AuthorizationException();
            }

            // A conversation belongs to a (user, site) pair; refuse to mix sites.
            if ($conversation->site_id !== $user->current_site_id) {
                throw new AuthorizationException();
            }
        }

        $provider = (string) config('services.ai.provider', 'groq');
        $model = (string) config("services.{$provider}.model", 'unknown');

        if ($conversation === null) {
            $conversation = AssistantConversation::create([
                'user_id' => $user->id,
                'site_id' => $user->current_site_id,
                'provider' => $provider,
                'model' => $model,
                'title' => $this->deriveTitle($userMessage),
            ]);
        }

        AssistantConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
            'created_at' => now(),
        ]);

        $today = Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY');
        $siteName = $user->currentSite?->name ?? 'votre site';

        $systemPrompt = "Tu es l'assistant de XetaSuite, un ERP multi-sites.
Tu aides les utilisateurs à interagir avec le système : consulter les données, créer des maintenances, signaler des incidents, gérer les articles, etc.
Tu parles à {$user->full_name} sur le site « {$siteName} ».
Réponds toujours en français, de manière concise et naturelle.
Pour les dates, utilise le format ISO 8601 (ex: 2026-04-27T08:00:00).
La date d'aujourd'hui est {$today}.
IMPORTANT : tes réponses sont lues à voix haute par une synthèse vocale. Réponds uniquement en texte brut, sans aucune mise en forme. N'utilise jamais de Markdown (pas d'astérisques **, pas de tirets pour des listes, pas de #, pas de backticks, pas de tableaux, pas de liens). N'utilise pas de puces ni de listes numérotées : énumère naturellement les éléments dans des phrases. Écris les nombres et unités en toutes lettres quand c'est plus naturel à l'oral. Évite les abréviations et symboles techniques.";

        $history = $this->loadReplayHistory($conversation);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$history,
            ['role' => 'user', 'content' => $userMessage],
        ];

        $tools = $this->registry->definitionsForLlm($user);

        $toolCallsLog = [];
        $iterations = 0;
        $reply = null;
        $maxIterations = (int) config('services.ai.max_iterations', 8);

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

        if ($reply === null) {
            $reply = "Désolé, je n'ai pas pu terminer cette action.";
        }

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
            'provider' => $provider,
            'model' => $model,
        ])->save();

        return response()->json([
            'reply' => $reply,
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Load past user/assistant messages to replay to the LLM.
     * Excludes the user message we just persisted for the current turn,
     * which is appended explicitly by the caller.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function loadReplayHistory(AssistantConversation $conversation): array
    {
        $rows = DB::table('assistant_conversation_messages')
            ->where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit(self::HISTORY_REPLAY_LIMIT + 1)
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

    private function deriveTitle(string $message): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $message) ?? '');
        if ($clean === '') {
            return 'Nouvelle conversation';
        }

        return mb_strlen($clean) > 60
            ? mb_substr($clean, 0, 57) . '…'
            : $clean;
    }
}
