<?php

/*
| Bot Telegram untuk penjualan harian. Semua nilainya dari .env, termasuk
| token: jangan ditulis di sini dan jangan dikirim ke chat.
|
|   TELEGRAM_BOT_TOKEN=<token dari @BotFather>
|   TELEGRAM_CHAT_IDS=-1001234567890        (id grup; pisahkan koma kalau lebih dari satu)
|   TELEGRAM_USER_IDS=                        (opsional: hanya pengirim ini yang dipercaya)
*/
return [
    'token' => env('TELEGRAM_BOT_TOKEN'),

    // Hanya pesan dari grup/chat ini yang dibaca. Kosong = tidak ada yang dibaca.
    'chat_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('TELEGRAM_CHAT_IDS', ''))))),

    // Opsional. Kalau diisi, pesan dari pengirim lain di grup diabaikan.
    'user_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('TELEGRAM_USER_IDS', ''))))),

    // Bot membalas di grup (berhasil / gagal + alasannya).
    'reply' => (bool) env('TELEGRAM_REPLY', true),

    // Zona waktu untuk menentukan "hari ini" pada pesan tanpa judul tanggal.
    'timezone' => env('TELEGRAM_TIMEZONE', 'Asia/Makassar'),

    'timeout' => 20,
];
