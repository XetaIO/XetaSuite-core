<?php

declare(strict_types=1);

namespace XetaSuite\Contracts\Assistant;

use XetaSuite\Models\User;

interface AssistantTool
{
    /**
     * Unique tool name exposed to the LLM (snake_case).
     */
    public function name(): string;

    /**
     * Full OpenAI function-calling definition.
     *
     * @return array<string, mixed>
     */
    public function definition(): array;

    /**
     * Spatie permission required to call this tool. Null = always allowed.
     */
    public function permission(): ?string;

    /**
     * Execute the tool. Return value will be JSON-encoded by the registry.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function execute(User $user, array $args): array;
}
