<?php

declare(strict_types=1);

namespace XetaSuite\Notifications\Item;

use XetaSuite\Enums\Notifications\NotificationType;
use XetaSuite\Notifications\Item\Concerns\ItemStockNotification;

class ItemWarningStockNotification extends ItemStockNotification
{
    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $type = NotificationType::ItemWarningStock;

        return [
            'alert_type' => $type->value,
            'title' => __('notifications.item_warning_stock.title'),
            'message' => __('notifications.item_warning_stock.message', [
                'item' => $this->item->name,
                'current_stock' => $this->currentStock,
                'minimum' => $this->item->number_warning_minimum,
            ]),
            'icon' => $type->icon(),
            'link' => '/items/'.$this->item->id,
        ];
    }
}
