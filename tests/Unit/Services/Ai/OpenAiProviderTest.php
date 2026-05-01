<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use XetaSuite\Exceptions\Ai\LlmRateLimitException;
use XetaSuite\Services\Ai\OpenAiProvider;

beforeEach(function (): void {
    config([
        'services.openai.api_key' => 'test-openai-key',
        'services.openai.base_url' => 'https://api.openai.com/v1',
        'services.openai.model' => 'gpt-4o-mini',
        'services.openai.max_tokens' => 1024,
        'services.openai.temperature' => 0.7,
    ]);
});

describe('OpenAiProvider', function (): void {
    it('sends configured model, max_tokens and temperature in payload', function (): void {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'hello'], 'finish_reason' => 'stop']],
            ]),
        ]);

        (new OpenAiProvider())->chat([['role' => 'user', 'content' => 'hi']]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->hasHeader('Authorization', 'Bearer test-openai-key')
                && $body['model'] === 'gpt-4o-mini'
                && $body['max_tokens'] === 1024
                && $body['temperature'] === 0.7
                && ! isset($body['tools']);
        });
    });

    it('includes tools and tool_choice when tools are provided', function (): void {
        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']]]),
        ]);

        $tools = [['type' => 'function', 'function' => ['name' => 'foo']]];
        (new OpenAiProvider())->chat([['role' => 'user', 'content' => 'hi']], $tools);

        Http::assertSent(fn ($request) => $request->data()['tool_choice'] === 'auto'
            && $request->data()['tools'] === $tools);
    });

    it('throws LlmRateLimitException on 429 response', function (): void {
        Http::fake([
            '*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        (new OpenAiProvider())->chat([['role' => 'user', 'content' => 'hi']]);
    })->throws(LlmRateLimitException::class);

    it('throws RuntimeException on other API errors', function (): void {
        Http::fake([
            '*' => Http::response(['error' => 'server error'], 500),
        ]);

        (new OpenAiProvider())->chat([['role' => 'user', 'content' => 'hi']]);
    })->throws(RuntimeException::class);

    it('returns the parsed JSON response on success', function (): void {
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => 'bonjour'], 'finish_reason' => 'stop']],
            ]),
        ]);

        $result = (new OpenAiProvider())->chat([['role' => 'user', 'content' => 'hi']]);

        expect($result['choices'][0]['message']['content'])->toBe('bonjour');
    });
});
