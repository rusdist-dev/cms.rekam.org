<?php

use App\Http\Controllers\ExtApi\Coast\DesaController;
use App\Http\Controllers\ExtApi\Coast\KawasanKonservasiController;
use App\Http\Controllers\ExtApi\Coast\StatistikController;
use App\Http\Controllers\ExtApi\Ikan\FilterController as IkanFilterController;
use App\Http\Controllers\ExtApi\Ikan\LengthFrequencyController;
use App\Http\Controllers\ExtApi\Ikan\CatchChartController;
use App\Http\Controllers\ExtApi\Ikan\TripChartController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| External Datasource API — /api/v1/ext/{datasource}/*
|--------------------------------------------------------------------------
|
| Endpoints backed by a database that is neither the central CMS database nor a
| tenant content database — see config/datasources.php. Authentication is the
| same `X-Api-Key` header as routes/api.php (ResolvePublicTenant), so a compro
| site needs no second credential; what differs is where the data comes from.
|
| Kept in its own file rather than bolted onto routes/api.php because these
| endpoints have a different failure mode: a third-party database can be
| missing from an environment, slow, or down, and none of that should be able
| to take the tenant content API with it. Hence the separate throttle, the
| separate cache TTL, and the `datasource:` gate on every group.
|
| Each datasource gets one group. The shape, copied for the next one:
|
|   Route::middleware(['datasource:simrs', $cacheHeaders])
|       ->prefix('simrs')
|       ->name('simrs.')
|       ->group(function () {
|           Route::get('pasien', [SimrsPasienController::class, 'index'])->name('pasien.index');
|       });
|
| docs/api-external.md has the full walkthrough.
|
*/

Route::middleware(['resolve.public.tenant', 'throttle:ext-api'])
    ->prefix('v1/ext')
    ->name('ext.')
    ->group(function () {
        // Same public-cache posture as routes/api.php: these are reads, and the
        // upstream system is the expensive part of every one of them.
        $cacheHeaders = 'cache.headers:public;max_age='.config('datasources.cache_ttl').';etag';

        // COAST — coastal/blue-carbon survey data behind the public map.
        Route::middleware(['datasource:coast', $cacheHeaders])
            ->prefix('coast')
            ->name('coast.')
            ->group(function () {
                Route::get('desa', [DesaController::class, 'index'])->name('desa.index');
                // {kode} is a wilayah code (33.01.22.1005), so it contains dots
                // — the default segment pattern already allows them, but the
                // constraint documents the shape and rejects anything else.
                Route::get('desa/{kode}', [DesaController::class, 'show'])
                    ->where('kode', '[0-9.]+')
                    ->name('desa.show');

                Route::get('kawasan-konservasi', [KawasanKonservasiController::class, 'index'])
                    ->name('kawasan-konservasi.index');

                // Village totals with no village: the headline panel.
                Route::get('statistik', [StatistikController::class, 'index'])
                    ->name('statistik.index');
            });

        // IKAN — fisheries trip data (trips, vessels, gear, catch).
        Route::middleware(['datasource:ikan', $cacheHeaders])
            ->prefix('ikan')
            ->name('ikan.')
            ->group(function () {
                // Chained filter dropdowns. Each level accepts every level
                // above it as an optional query parameter — see FilterController.
                Route::prefix('opsi')->name('opsi.')->group(function () {
                    Route::get('wppnri', [IkanFilterController::class, 'wppnri'])->name('wppnri');
                    Route::get('provinsi', [IkanFilterController::class, 'provinsi'])->name('provinsi');
                    Route::get('kabupaten', [IkanFilterController::class, 'kabupaten'])->name('kabupaten');
                    Route::get('lokasi-pendaratan', [IkanFilterController::class, 'lokasiPendaratan'])->name('lokasi-pendaratan');
                    Route::get('jenis-data', [IkanFilterController::class, 'jenisData'])->name('jenis-data');
                    Route::get('alat-tangkap', [IkanFilterController::class, 'alatTangkap'])->name('alat-tangkap');
                    Route::get('family', [IkanFilterController::class, 'family'])->name('family');
                    Route::get('spesies', [IkanFilterController::class, 'spesies'])->name('spesies');
                });

                // Both chart series in one response: they must describe the
                // same filtered set of trips.
                Route::get('grafik/trip', TripChartController::class)->name('grafik.trip');

                // What the landed weight is made of, per species.
                Route::get('grafik/tangkapan', CatchChartController::class)->name('grafik.tangkapan');

                // Length-frequency histogram, with Lc computed and Lm supplied.
                Route::get('grafik/frekuensi-panjang', LengthFrequencyController::class)->name('grafik.frekuensi-panjang');
            });

        // -- datasource groups go here; each one applies $cacheHeaders --
    });
