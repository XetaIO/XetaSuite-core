<?php

declare(strict_types=1);

namespace XetaSuite\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use XetaSuite\Models\User;
use XetaSuite\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service)
    {
    }

    /**
     * Get dashboard statistics.
     *
     * On HQ: aggregated stats from all sites.
     * On regular site: stats for current site only.
     */
    public function stats(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json($this->service->stats($user));
    }

    /**
     * Get chart data for maintenances and incidents evolution (last 12 months).
     */
    public function chartsData(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json($this->service->chartsData($user));
    }
}
