<?php

declare(strict_types=1);

namespace XetaSuite\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use XetaSuite\Contracts\Ai\LlmProvider;
use XetaSuite\Exceptions\Ai\LlmRateLimitException;

class GroqProvider implements LlmProvider
{
    private string $apiKey;

    private string $baseUrl;

    private string $model;

    private int $maxTokens;

    private float $temperature;

    public function __construct()
    {
        $this->apiKey = (string) config('services.groq.api_key', '');
        $this->baseUrl = (string) config('services.groq.base_url', 'https://api.groq.com/openai/v1');
        $this->model = (string) config('services.groq.model', 'llama-3.3-70b-versatile');
        $this->maxTokens = (int) config('services.groq.max_tokens', 1024);
        $this->temperature = (float) config('services.groq.temperature', 0.7);
    }

    /**
     * Call the LLM chat completions endpoint.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     *
     * @throws RuntimeException|ConnectionException
     */
    public function chat(array $messages, array $tools = []): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if (! $response->successful()) {
            if ($response->status() === 429) {
                throw new LlmRateLimitException();
            }

            throw new RuntimeException("AI chat error ({$response->status()}): {$response->body()}");
        }

        return $response->json();
    }
}
