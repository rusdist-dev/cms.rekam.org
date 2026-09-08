<?php

/*
 * Overrides Breeze/Laravel's built-in English defaults — no lang/id/auth.php
 * existed before, so these fell through to English, violating context.md §9
 * ("Semua teks UI berbahasa Indonesia").
 */
return [
    'failed' => 'Email atau kata sandi tidak cocok dengan data kami.',
    'password' => 'Kata sandi yang dimasukkan salah.',
    'throttle' => 'Terlalu banyak percobaan masuk. Coba lagi dalam :seconds detik.',
];
