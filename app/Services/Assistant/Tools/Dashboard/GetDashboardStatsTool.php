<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant\Tools\Dashboard;

use XetaSuite\Models\Incident;
use XetaSuite\Models\Item;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\User;
use XetaSuite\Services\Assistant\Tools\AbstractAssistantTool;

class GetDashboardStatsTool extends AbstractAssistantTool
{
    public function name(): string
    {
        return 'get_dashboard_stats';
    }

    public function permission(): ?string
    {
        return 'assistant.use';
    }

    protected function description(): string
    {
        return 'Récupère les statistiques du tableau de bord du site (incidents ouverts, maintenances en attente, stocks bas)';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    public function execute(User $user, array $args): array
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

        return [
            'open_incidents' => $openIncidents,
            'pending_maintenances' => $pendingMaintenances,
            'low_stock_items' => $lowStockItems,
        ];
    }
}
