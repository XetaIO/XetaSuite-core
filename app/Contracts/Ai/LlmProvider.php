<?php

declare(strict_types=1);

namespace XetaSuite\Contracts\Ai;

interface LlmProvider
{
    /**
     * Send a chat completion request with optional tool definitions.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function chat(array $messages, array $tools = []): array;
}
