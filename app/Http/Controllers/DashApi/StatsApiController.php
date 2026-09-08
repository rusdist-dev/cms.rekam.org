<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;

/**
 * Replaces PrototypeApiController::stats() with real numbers (plan.md Fase 7).
 * Same route name and `section` whitelist, so the dashboard's Blade/Alpine
 * layer needed no changes.
 */
class StatsApiController extends Controller
{
    public function __construct(private readonly StatsService $stats) {}

    public function show(string $section): JsonResponse
    {
        $data = match ($section) {
            'summary' => $this->stats->summary(),
            'publishing_trend' => $this->stats->publishingTrend(),
            'status_breakdown' => $this->stats->statusBreakdown(),
            'recent_activity' => $this->stats->recentActivity(),
            'popular_news' => $this->stats->popularNews(),
        };

        return response()->json(['data' => $data]);
    }
}
