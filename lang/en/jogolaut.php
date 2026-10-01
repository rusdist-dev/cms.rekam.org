<?php

/*
| Labels for GET /api/v1/ext/jogolaut/monitoring (?locale=en). Text only:
| units travel in their own `unit` field. Keys must match
| lang/id/jogolaut.php exactly.
*/

return [

    'series' => [
        'co2_tanah' => 'Soil CO₂',
        'co2_udara' => 'Air CO₂',
        'pasut_ma' => 'Tide (MA)',
        'respirasi' => 'CO₂ Respiration',
        'carbon_flux' => 'Carbon Flux',
        'do' => 'Dissolved Oxygen',
        'suhu_air' => 'Water Temperature',
        'ph' => 'Water pH',
        'conductivity' => 'Conductivity',
        'level_air' => 'Water Level',
        'kec_angin' => 'Wind Speed',
        'arah_angin' => 'Wind Direction',
        'suhu_udara' => 'Air Temperature',
        'kelembaban' => 'Air Humidity',
        'curah_hujan' => 'Rainfall',
        'mean' => 'Mean Soil CO₂',
        'std' => 'Soil CO₂ Standard Deviation',
        'ph_tanah' => 'Soil pH',
        'ph_air' => 'Water pH',
        'waktu' => 'Time',
        'temp_air' => 'Air Temperature (CO₂)',
        'humidity' => 'Humidity (CO₂)',
        'soil_moisture' => 'Soil Moisture',
        'soil_temp' => 'Soil Temperature',
        'soil_ph' => 'Soil pH',
    ],

    'kpi' => [
        'suhu_udara' => 'Air Temperature',
        'kelembaban_udara' => 'Air Humidity',
        'curah_hujan' => 'Rainfall',
        'jarak_air' => 'Sensor-to-Water Distance',
        'pasut' => 'Tide Level',
        'co2_lapangan' => 'Field CO₂',
        'suhu_co2' => 'Air Temperature (CO₂)',
        'kelembaban_co2' => 'Humidity (CO₂)',
        'kelembaban_tanah' => 'Soil Moisture',
        'suhu_tanah' => 'Soil Temperature',
        'ph_tanah' => 'Soil pH',
        'conductivity' => 'Conductivity',
        'suhu_ctd' => 'Water Temperature (CTD)',
        'level_air' => 'Water Level',
        'do_air' => 'Water DO',
        'suhu_do' => 'Water Temperature (DO)',
        'ph_air' => 'Water pH',
        'suhu_ph' => 'Water Temperature (pH)',
    ],

    'gauge' => [
        'heat_index' => 'Heat Index',
        'do' => 'Dissolved Oxygen',
        'conductivity' => 'Conductivity',
        'water_temp' => 'Water Temperature',
        'ph_air' => 'Water pH',
    ],

    'level' => [
        'heat_index' => [
            'safe' => 'Safe',
            'caution' => 'Caution',
            'warning' => 'Warning',
            'danger' => 'Danger',
            'extreme' => 'Extreme Danger',
        ],
        'do' => [
            'hypoxic' => 'Hypoxic',
            'low' => 'Low',
            'normal' => 'Normal',
            'optimal' => 'Optimal',
        ],
        'conductivity' => [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'saline' => 'Saline',
        ],
        'water_temp' => [
            'cool' => 'Cool',
            'normal' => 'Normal',
            'warm' => 'Warm',
            'hot' => 'Hot',
        ],
        'ph_air' => [
            'acidic' => 'Acidic',
            'optimal' => 'Optimal',
            'basic' => 'Basic',
        ],
    ],

    'heat_index_desc' => [
        'safe' => 'Comfortable conditions. Outdoor activities are safe.',
        'caution' => 'Fatigue possible with prolonged exposure. Drink plenty of water.',
        'warning' => 'Risk of heat cramps and exhaustion. Avoid strenuous outdoor activities.',
        'danger' => 'Heat stroke very likely. Limit outdoor activities.',
        'extreme' => 'Extremely high heatstroke risk. Avoid outdoor activities entirely.',
    ],

    'status' => [
        'photosynthesis' => [
            'label' => 'Photosynthesis Dominant',
            'description' => 'During the day, the system actively produces O₂. Mangroves and microalgae photosynthesize, absorbing CO₂ and releasing oxygen into the environment.',
        ],
        'respiration' => [
            'label' => 'Respiration Dominant',
            'description' => 'Night time or anaerobic conditions. High organic matter decomposition, releasing CO₂ and consuming oxygen.',
        ],
        'tidal' => [
            'label' => 'Tidal Driven',
            'description' => 'System controlled by tides. Water movement is more dominant than biological processes.',
        ],
        'mixed' => [
            'label' => 'Mixed / Balanced',
            'description' => 'No dominant process. System is in a transitional or balanced state.',
        ],
    ],

    'direction' => [
        'N' => 'North', 'NNE' => 'North-Northeast', 'NE' => 'Northeast', 'ENE' => 'East-Northeast',
        'E' => 'East', 'ESE' => 'East-Southeast', 'SE' => 'Southeast', 'SSE' => 'South-Southeast',
        'S' => 'South', 'SSW' => 'South-Southwest', 'SW' => 'Southwest', 'WSW' => 'West-Southwest',
        'W' => 'West', 'WNW' => 'West-Northwest', 'NW' => 'Northwest', 'NNW' => 'North-Northwest',
    ],

    'beaufort' => [
        'calm' => 'Calm',
        'light_breeze' => 'Light breeze',
        'gentle_breeze' => 'Gentle breeze',
        'moderate_breeze' => 'Moderate breeze',
        'fresh_breeze' => 'Fresh breeze',
        'strong_breeze' => 'Strong breeze',
    ],

];
