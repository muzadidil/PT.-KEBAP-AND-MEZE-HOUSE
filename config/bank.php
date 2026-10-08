<?php

/*
| Pembaca email notifikasi bank (Transaksi Bank). Semua nilainya dari .env,
| termasuk kata sandi: jangan ditulis di sini dan jangan dikirim ke chat.
|
|   BANK_IMAP_USER=zeytin.canggu@gmail.com
|   BANK_IMAP_PASSWORD=<kata sandi khusus aplikasi Gmail>
*/
return [
    'imap' => [
        'host' => env('BANK_IMAP_HOST', 'imap.gmail.com'),
        'port' => (int) env('BANK_IMAP_PORT', 993),
        'user' => env('BANK_IMAP_USER'),
        'password' => env('BANK_IMAP_PASSWORD'),
        'timeout' => 25,
    ],

    // Hanya email dari domain ini yang dibaca.
    'sender_domain' => env('BANK_SENDER_DOMAIN', 'bni.co.id'),

    // Email palsu mudah dibuat: wajib lolos pemeriksaan DKIM/SPF milik
    // penyedia email (Gmail mencatatnya di header Authentication-Results).
    'require_authentication' => (bool) env('BANK_REQUIRE_AUTH', true),

    // Berapa hari ke belakang yang dicari tiap kali berjalan.
    'lookback_days' => (int) env('BANK_LOOKBACK_DAYS', 7),
];
