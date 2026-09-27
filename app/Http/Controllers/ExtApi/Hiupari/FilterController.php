<?php

namespace App\Http\Controllers\ExtApi\Hiupari;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Services\Hiupari\HiupariLengthFrequencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The species list for HIUPARI's one filter.
 *
 * No chain here, unlike IKAN and BSC: species is the only dimension this
 * surface filters on, so there is nothing above it to narrow by.
 */
class FilterController extends ExternalApiController
{
    protected string $datasource = 'hiupari';

    public function spesies(Request $request): JsonResponse
    {
        return $this->dataResponse(
            $request,
            'opsi:spesies',
            fn () => app(HiupariLengthFrequencyService::class)->species(),
        );
    }
}
