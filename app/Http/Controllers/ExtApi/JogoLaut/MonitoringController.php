<?php

namespace App\Http\Controllers\ExtApi\JogoLaut;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Http\Requests\ExtApi\JogoLautMonitoringRequest;
use App\Services\JogoLaut\JogoLautMonitoringService;
use Illuminate\Http\JsonResponse;

/**
 * The JOGO LAUT monitoring page as one response: every chart, stat and status
 * the old server-rendered page drew, as chart-neutral data.
 *
 * Not dataResponse(), and no response cache at all: caching happens a level
 * down, per upstream read and per section (JogoLautSnapshot), so that varying
 * `include` or `window` can never turn into extra queries upstream.
 */
class MonitoringController extends ExternalApiController
{
    protected string $datasource = 'jogolaut';

    public function __invoke(JogoLautMonitoringRequest $request, JogoLautMonitoringService $monitoring): JsonResponse
    {
        return response()->json(['data' => $monitoring->build($request->params())]);
    }
}
