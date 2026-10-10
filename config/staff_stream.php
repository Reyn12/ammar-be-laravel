<?php

return [
    // Lama satu koneksi SSE (detik) sebelum ditutup; klien otomatis menyambung lagi.
    'duration' => (float) env('STAFF_STREAM_DURATION', 25),

    // Jeda pengecekan event baru di database (milidetik).
    'interval_ms' => (int) env('STAFF_STREAM_INTERVAL_MS', 1000),

    // Kirim komentar ": ping" tiap sekian detik supaya koneksi tidak dianggap mati proxy.
    'heartbeat' => (int) env('STAFF_STREAM_HEARTBEAT', 15),
];
