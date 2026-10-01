<?php

/*
| Label untuk GET /api/v1/ext/jogolaut/monitoring (?locale=id). Hanya teks:
| satuan dikirim di field `unit` tersendiri, jadi tidak diulang di sini.
| Pasangannya lang/en/jogolaut.php — kuncinya harus sama persis.
*/

return [

    'series' => [
        'co2_tanah' => 'CO₂ Tanah',
        'co2_udara' => 'CO₂ Udara',
        'pasut_ma' => 'Pasut (MA)',
        'respirasi' => 'Respirasi CO₂',
        'carbon_flux' => 'Fluks Karbon',
        'do' => 'Oksigen Terlarut',
        'suhu_air' => 'Suhu Air',
        'ph' => 'pH Air',
        'conductivity' => 'Konduktivitas',
        'level_air' => 'Level Air',
        'kec_angin' => 'Kecepatan Angin',
        'arah_angin' => 'Arah Angin',
        'suhu_udara' => 'Suhu Udara',
        'kelembaban' => 'Kelembaban Udara',
        'curah_hujan' => 'Curah Hujan',
        'mean' => 'Rata-rata CO₂ Tanah',
        'std' => 'Simpangan Baku CO₂ Tanah',
        'ph_tanah' => 'pH Tanah',
        'ph_air' => 'pH Air',
        'waktu' => 'Waktu',
        'temp_air' => 'Suhu Udara (CO₂)',
        'humidity' => 'Kelembaban (CO₂)',
        'soil_moisture' => 'Kelembaban Tanah',
        'soil_temp' => 'Suhu Tanah',
        'soil_ph' => 'pH Tanah',
    ],

    'kpi' => [
        'suhu_udara' => 'Suhu Udara',
        'kelembaban_udara' => 'Kelembaban Udara',
        'curah_hujan' => 'Curah Hujan',
        'jarak_air' => 'Jarak Sensor ke Muka Air',
        'pasut' => 'Pasang Surut',
        'co2_lapangan' => 'CO₂ Lapangan',
        'suhu_co2' => 'Suhu Udara (CO₂)',
        'kelembaban_co2' => 'Kelembaban (CO₂)',
        'kelembaban_tanah' => 'Kelembaban Tanah',
        'suhu_tanah' => 'Suhu Tanah',
        'ph_tanah' => 'pH Tanah',
        'conductivity' => 'Konduktivitas',
        'suhu_ctd' => 'Suhu Air (CTD)',
        'level_air' => 'Level Air',
        'do_air' => 'DO Air',
        'suhu_do' => 'Suhu Air (DO)',
        'ph_air' => 'pH Air',
        'suhu_ph' => 'Suhu Air (pH)',
    ],

    'gauge' => [
        'heat_index' => 'Indeks Panas',
        'do' => 'Oksigen Terlarut',
        'conductivity' => 'Konduktivitas',
        'water_temp' => 'Suhu Air',
        'ph_air' => 'pH Air',
    ],

    'level' => [
        'heat_index' => [
            'safe' => 'Aman',
            'caution' => 'Waspada',
            'warning' => 'Siaga',
            'danger' => 'Bahaya',
            'extreme' => 'Bahaya Ekstrem',
        ],
        'do' => [
            'hypoxic' => 'Hipoksik',
            'low' => 'Rendah',
            'normal' => 'Normal',
            'optimal' => 'Optimal',
        ],
        'conductivity' => [
            'low' => 'Rendah',
            'normal' => 'Normal',
            'high' => 'Tinggi',
            'saline' => 'Salin',
        ],
        'water_temp' => [
            'cool' => 'Sejuk',
            'normal' => 'Normal',
            'warm' => 'Hangat',
            'hot' => 'Panas',
        ],
        'ph_air' => [
            'acidic' => 'Asam',
            'optimal' => 'Optimal',
            'basic' => 'Basa',
        ],
    ],

    'heat_index_desc' => [
        'safe' => 'Kondisi nyaman. Aktivitas luar ruangan aman dilakukan.',
        'caution' => 'Kelelahan mungkin terjadi pada paparan lama. Perbanyak minum air.',
        'warning' => 'Risiko kram panas dan kelelahan panas. Hindari aktivitas berat di luar.',
        'danger' => 'Serangan panas sangat mungkin terjadi. Batasi aktivitas luar ruangan.',
        'extreme' => 'Risiko heatstroke sangat tinggi. Hindari sepenuhnya beraktivitas di luar.',
    ],

    'status' => [
        'photosynthesis' => [
            'label' => 'Fotosintesis Dominan',
            'description' => 'Siang hari, sistem aktif memproduksi O₂. Mangrove dan mikroalga aktif berfotosintesis, menyerap CO₂ dan melepas oksigen ke lingkungan.',
        ],
        'respiration' => [
            'label' => 'Respirasi Dominan',
            'description' => 'Malam hari atau kondisi anaerob. Dekomposisi bahan organik tinggi, melepas CO₂ dan mengonsumsi oksigen.',
        ],
        'tidal' => [
            'label' => 'Dikendalikan Pasang Surut',
            'description' => 'Sistem dikontrol oleh pasang surut. Pergerakan air lebih dominan daripada proses biologis.',
        ],
        'mixed' => [
            'label' => 'Campuran / Seimbang',
            'description' => 'Tidak ada proses yang dominan. Sistem dalam kondisi transisi atau seimbang.',
        ],
    ],

    'direction' => [
        'N' => 'Utara', 'NNE' => 'Utara-Timur Laut', 'NE' => 'Timur Laut', 'ENE' => 'Timur-Timur Laut',
        'E' => 'Timur', 'ESE' => 'Timur-Tenggara', 'SE' => 'Tenggara', 'SSE' => 'Selatan-Tenggara',
        'S' => 'Selatan', 'SSW' => 'Selatan-Barat Daya', 'SW' => 'Barat Daya', 'WSW' => 'Barat-Barat Daya',
        'W' => 'Barat', 'WNW' => 'Barat-Barat Laut', 'NW' => 'Barat Laut', 'NNW' => 'Utara-Barat Laut',
    ],

    'beaufort' => [
        'calm' => 'Tenang',
        'light_breeze' => 'Sepoi-sepoi lemah',
        'gentle_breeze' => 'Sepoi-sepoi sedang',
        'moderate_breeze' => 'Angin sedang',
        'fresh_breeze' => 'Angin segar',
        'strong_breeze' => 'Angin kencang',
    ],

];
