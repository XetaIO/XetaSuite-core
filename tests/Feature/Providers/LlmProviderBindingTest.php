<?php

declare(strict_types=1);

use XetaSuite\Contracts\Ai\LlmProvider;
use XetaSuite\Services\Ai\GroqProvider;
use XetaSuite\Services\Ai\OpenAiProvider;

describe('LlmProvider binding', function (): void {
    it('resolves to GroqProvider by default', function (): void {
        config(['services.ai.provider' => 'groq']);

        expect(app(LlmProvider::class))->toBeInstanceOf(GroqProvider::class);
    });

    it('resolves to OpenAiProvider when AI_PROVIDER=openai', function (): void {
        config(['services.ai.provider' => 'openai']);

        expect(app(LlmProvider::class))->toBeInstanceOf(OpenAiProvider::class);
    });

    it('falls back to GroqProvider for unknown provider value', function (): void {
        config(['services.ai.provider' => 'unknown']);

        expect(app(LlmProvider::class))->toBeInstanceOf(GroqProvider::class);
    });
});
