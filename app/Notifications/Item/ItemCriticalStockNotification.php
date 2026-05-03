<?php

declare(strict_types=1);

namespace XetaSuite\Notifications\Item;

use Illuminate\Notifications\Messages\MailMessage;
use XetaSuite\Notifications\Item\Concerns\ItemStockNotification;

class ItemCriticalStockNotification extends ItemStockNotification
{
    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(__('items.notifications.critical_stock_subject', ['name' => $this->item->name]))
            ->error()
            ->greeting(__('items.notifications.critical_stock_greeting'))
            ->line(__('items.notifications.critical_stock_line1', [
                'name' => $this->item->name,
                'reference' => $this->item->reference ?? '-',
            ]))
            ->line(__('items.notifications.critical_stock_line2', [
                'current' => $this->currentStock,
                'minimum' => $this->item->number_critical_minimum,
            ]))
            ->action(__('items.notifications.view_item'), $this->itemUrl())
            ->line(__('items.notifications.critical_stock_line3'));
    }
}
