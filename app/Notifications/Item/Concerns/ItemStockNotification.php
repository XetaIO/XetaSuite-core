<?php

declare(strict_types=1);

namespace XetaSuite\Notifications\Item\Concerns;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use XetaSuite\Models\Item;

abstract class ItemStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Item $item,
        public int $currentStock
    ) {
    }

    /**
     * Get the URL pointing to the item in the SPA.
     */
    protected function itemUrl(): string
    {
        return config('app.spa_url', config('app.url')).'/items/'.$this->item->id;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    abstract public function via(object $notifiable): array;
}
