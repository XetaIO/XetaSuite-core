<?php

declare(strict_types=1);

namespace XetaSuite\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantConversation extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'site_id',
        'title',
        'provider',
        'model',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantConversationMessage::class, 'conversation_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Conversations belonging to a user on a given site, most recent first.
     */
    public function scopeForUserOnSite(Builder $query, int $userId, ?int $siteId): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('site_id', $siteId)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }
}
