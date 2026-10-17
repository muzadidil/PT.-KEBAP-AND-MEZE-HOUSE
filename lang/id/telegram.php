<?php

return [
    'nav' => 'Antrean Telegram',
    'date_taken' => 'Tanggal ini sudah ada datanya',

    'col' => [
        'date' => 'Tanggal',
        'total' => 'Total penjualan',
        'status' => 'Status',
        'sender' => 'Pengirim',
    ],

    'status' => [
        'pending' => 'Menunggu',
        'recorded' => 'Tercatat',
        'ignored' => 'Ditolak',
    ],

    'filter' => [
        'period' => 'Tanggal laporan',
        'from' => 'Dari tanggal',
        'to' => 'Sampai tanggal',
    ],

    'action' => [
        'record' => 'Konfirmasi',
        'record_heading' => 'Konfirmasi penjualan harian',
        'record_description' => 'Periksa tanggal dan angkanya. Boleh diubah sebelum dicatat. Setelah dikonfirmasi, datanya masuk ke Pemasukan Harian.',
        'record_submit' => 'Catat ke Pemasukan Harian',
        'recorded' => 'Tercatat di Pemasukan Harian',
        'taken' => 'Tanggal itu sudah ada datanya',
        'taken_body' => 'Pemasukan Harian tidak pernah ditimpa. Ubah tanggalnya ke hari yang benar, atau tolak laporan ini.',
        'ignore' => 'Tolak',
        'ignore_heading' => 'Tolak laporan ini?',
        'ignore_description' => 'Laporan tidak dicatat. Pengirim diberi tahu di grup supaya bisa mengirim ulang.',
    ],

    'reply' => [
        'recorded' => 'Tercatat: :date · Total :total.',
        'ignored' => 'Laporan :date ditolak admin. Silakan kirim ulang yang benar.',
    ],

    'pull' => [
        'action' => 'Tarik dari bot Telegram',
        'heading' => 'Tarik laporan dari bot Telegram',
        'description' => 'Membaca pesan baru di grup Telegram sekarang juga. Laporan yang benar masuk ke antrean ini untuk Anda konfirmasi. Belum ada yang dicatat ke Pemasukan Harian.',
        'submit' => 'Tarik sekarang',
        'no_token' => 'TELEGRAM_BOT_TOKEN belum diisi di .env hosting.',
        'no_chat_ids' => 'Id grup belum diisi',
        'chat_ids_found' => 'Isi TELEGRAM_CHAT_IDS di .env dengan salah satu id ini, lalu tarik lagi:',
        'no_messages' => 'Belum ada pesan terbaca. Jadikan bot admin grup, kirim satu pesan baru ke grup, lalu tarik lagi.',
        'done' => 'Telegram dibaca',
        'summary' => ':checked pesan dicek: :queued masuk antrean, :failed salah format, :ignored diabaikan (bukan laporan).',
        'failed' => 'Gagal membaca Telegram',
        'busy' => 'Sedang dibaca oleh proses lain. Coba lagi sebentar.',
    ],
];
