<?php

declare(strict_types=1);

namespace XetaSuite\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use XetaSuite\Models\AssistantConversation;
use XetaSuite\Models\User;

class AssistantConversationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('assistant.use');
    }

    public function view(User $user, AssistantConversation $conversation): bool
    {
        return $user->can('assistant.use')
            && $conversation->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('assistant.use');
    }

    public function delete(User $user, AssistantConversation $conversation): bool
    {
        return $user->can('assistant.use')
            && $conversation->user_id === $user->id;
    }
}
