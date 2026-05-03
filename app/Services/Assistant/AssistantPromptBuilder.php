<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant;

use Carbon\Carbon;
use XetaSuite\Models\User;

/**
 * Builds the assistant system prompt and conversation titles from the
 * configurable template under `config/assistant.php`.
 */
class AssistantPromptBuilder
{
    /**
     * Render the system prompt for the given user.
     */
    public function buildSystemPrompt(User $user): string
    {
        $template = (string) config('assistant.system_prompt', '');
        $today = Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY');
        $siteName = $user->currentSite?->name ?? 'votre site';

        return strtr($template, [
            ':user_name' => $user->full_name,
            ':site_name' => $siteName,
            ':today' => $today,
        ]);
    }

    /**
     * Derive a conversation title from the first user message.
     */
    public function deriveTitle(string $message): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $message) ?? '');
        if ($clean === '') {
            return (string) config('assistant.title.fallback', 'Nouvelle conversation');
        }

        $maxLength = (int) config('assistant.title.max_length', 60);
        $truncateAt = (int) config('assistant.title.truncate_at', 57);

        return mb_strlen($clean) > $maxLength
            ? mb_substr($clean, 0, $truncateAt).'…'
            : $clean;
    }
}
