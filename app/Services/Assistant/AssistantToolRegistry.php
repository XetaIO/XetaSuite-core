<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant;

use Illuminate\Support\Facades\Log;
use Throwable;
use XetaSuite\Contracts\Assistant\AssistantTool;
use XetaSuite\Models\User;

class AssistantToolRegistry
{
    /**
     * @var array<string, AssistantTool>
     */
    private array $tools = [];

    /**
     * @param  iterable<AssistantTool>  $tools
     */
    public function __construct(iterable $tools = [])
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * Register an additional tool at runtime (mostly used in tests).
     */
    public function register(AssistantTool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function find(string $name): ?AssistantTool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<string, AssistantTool>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Return the OpenAI function-calling definitions for tools the user is allowed to use.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitionsForLlm(User $user): array
    {
        return array_values(array_map(
            fn (AssistantTool $tool) => $tool->definition(),
            array_filter($this->tools, fn (AssistantTool $tool) => $this->isAllowed($user, $tool)),
        ));
    }

    /**
     * Execute a tool by name and return the JSON-encoded result string for the LLM.
     *
     * @param  array<string, mixed>  $args
     */
    public function execute(User $user, string $name, array $args): string
    {
        $tool = $this->find($name);

        if (! $tool instanceof AssistantTool) {
            return (string) json_encode(['error' => "Outil inconnu : {$name}"]);
        }

        if (! $this->isAllowed($user, $tool)) {
            $this->logCall($user, $name, $args, 'denied');

            return (string) json_encode(['error' => 'permission_denied']);
        }

        try {
            $result = $tool->execute($user, $args);
            $this->logCall($user, $name, $args, 'ok');

            return (string) json_encode($result);
        } catch (Throwable $e) {
            $this->logCall($user, $name, $args, 'error', $e->getMessage());

            return (string) json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Audit log for assistant tool calls (sensitive actions: create/update/delete).
     *
     * @param  array<string, mixed>  $args
     */
    private function logCall(User $user, string $name, array $args, string $outcome, ?string $error = null): void
    {
        Log::info('assistant.tool_call', [
            'user_id' => $user->getKey(),
            'site_id' => $user->current_site_id,
            'tool' => $name,
            'args' => $args,
            'outcome' => $outcome,
            'error' => $error,
        ]);
    }

    private function isAllowed(User $user, AssistantTool $tool): bool
    {
        $permission = $tool->permission();

        if ($permission === null) {
            return true;
        }

        return $user->can($permission);
    }
}
