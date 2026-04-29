<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use XetaSuite\Contracts\Assistant\AssistantTool;
use XetaSuite\Models\User;

abstract class AbstractAssistantTool implements AssistantTool
{
    /**
     * Authorize the user against a specific model and ability via the policy layer.
     * Used by Get/Update/Delete tools to enforce site-scoping (cf. *Policy::view/update/delete).
     *
     * @throws AuthorizationException
     */
    protected function authorizeModel(User $user, string $ability, Model $model): void
    {
        if ($user->cannot($ability, $model)) {
            throw new AuthorizationException('permission_denied');
        }
    }

    abstract public function name(): string;

    abstract protected function description(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function parameters(): array;

    public function permission(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        $parameters = $this->parameters();

        // JSON Schema requires "properties" to be an object, not an empty array.
        // PHP encodes [] as JSON [] and (object) [] as JSON {}.
        if (isset($parameters['properties']) && $parameters['properties'] === []) {
            $parameters['properties'] = (object) [];
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $parameters,
            ],
        ];
    }
}
