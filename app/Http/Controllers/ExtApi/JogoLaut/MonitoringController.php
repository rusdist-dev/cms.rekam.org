<?php

namespace App\Http\Controllers\ExtApi\JogoLaut;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Http\Requests\ExtApi\JogoLautMonitoringRequest;
use App\Services\ExternalCacheService;
use App\Services\JogoLaut\JogoLautMonitoringService;
use Illuminate\Http\JsonResponse;

/**
 * The JOGO LAUT monitoring page as one response: every chart, stat and status
 * the old server-rendered page drew, as chart-neutral data.
 *
 * Not dataResponse(): the cache is keyed by the *normalised* parameters, so
 * `include=do,co2` and `include=co2,do` share an entry, and a payload with a
 * failed section is served but never cached.
 */
class MonitoringController extends ExternalApiController
{
    protected string $datasource = 'jogolaut';

    public function __invoke(JogoLautMonitoringRequest $request): JsonResponse
    {
        $params = $request->params();

        $payload = app(ExternalCacheService::class)->rememberUnless(
            $this->datasource,
            'monitoring',
            ['include' => implode(',', $params['include'])] + $params,
            fn () => app(JogoLautMonitoringService::class)->build($params),
            fn (array $payload) => JogoLautMonitoringService::hasErrors($payload),
        );

        return response()->json(['data' => $payload]);
    }
}
