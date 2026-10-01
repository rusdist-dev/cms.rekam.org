<?php

/*
|--------------------------------------------------------------------------
| JOGO LAUT — coastal monitoring station, Cilacap
|--------------------------------------------------------------------------
|
| Constants behind GET /api/v1/ext/jogolaut/monitoring. Ported from the
| perikanan.org dashboard (config/jogolaut.php there) so both read the same
| numbers while the old page is still live; change them in both places until
| it is retired.
|
*/

return [

    // Default and ceiling for `?days=`. The ceiling bounds the payload: one
    // reading every five minutes is ~8,600 points per series at 30 days.
    'data_interval_days' => 7,
    'max_interval_days' => 30,

    // Upstream stores UTC; every timestamp leaves this API in station time.
    'source_timezone' => '+00:00',
    'timezone' => '+07:00',

    // The tide gauge measures the distance from the sensor down to the water,
    // so the tide level is this reference minus `pasut.jarak_air` (cm).
    'ref_pasut' => 420,

    // Nominal sensor interval, used to turn a CCF lag in points into minutes.
    'sampling_minutes' => 5,

    // Soil respiration chamber geometry (m³ and m), for the flux constant.
    'chamber_volume' => 0.0005,
    'chamber_diameter' => 0.03,

    // Two series are matched only when their readings are this close.
    'align_threshold_seconds' => 3600,

    // Hourly flux smoothing, and the trend thresholds of the ecosystem status.
    'ma_window' => 3,
    'co2_trend_threshold' => 2,
    'do_trend_threshold' => 0.1,

];
