<?php

declare(strict_types=1);

namespace XetaSuite\Services\VoiceTools;

use XetaSuite\Models\Incident;
use XetaSuite\Models\Item;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\User;

trait HandlesDashboard
{
    /**
     * @param  array<string, mixed>  $args
     */
    private function getDashboardStats(User $user, array $args): string
    {
        $openIncidents = Incident::query()
            ->forCurrentSite()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->count();

        $pendingMaintenances = Maintenance::query()
            ->forCurrentSite()
            ->whereNotIn('status', ['resolved', 'cancelled'])
            ->count();

        $lowStockItems = Item::query()
            ->forCurrentSite()
            ->whereColumn('item_entry_total', '<=', 'item_exit_total')
            ->count();

        return json_encode([
            'open_incidents' => $openIncidents,
            'pending_maintenances' => $pendingMaintenances,
            'low_stock_items' => $lowStockItems,
        ]);
    }
}
