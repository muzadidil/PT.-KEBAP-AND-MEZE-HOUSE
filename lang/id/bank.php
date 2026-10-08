<?php

return [

    'nav' => 'Transaksi Bank',

    'col' => [
        'when' => 'Tanggal dan jam',
        'to' => 'Dibayar ke',
        'amount' => 'Nominal',
        'direction' => 'Arah',
        'status' => 'Status',
        'reference' => 'Referensi BNI',
    ],

    'direction' => [
        'out' => 'Pembayaran keluar',
        'in' => 'Uang masuk',
    ],

    'status' => [
        'pending' => 'Menunggu',
        'recorded' => 'Tercatat',
        'ignored' => 'Diabaikan',
    ],

    'action' => [
        'record' => 'Catat sebagai Transfer Pemasok',
        'record_heading' => 'Catat sebagai Transfer Pemasok?',
        'record_description' => ':amount ke :to akan ditambahkan ke Transfer Pemasok.',
        'recorded' => 'Tercatat di Transfer Pemasok',
        'ignore' => 'Abaikan',
    ],

    'fetch' => [
        'action' => 'Ambil dari email',
        'done' => 'Email dicek',
        'failed' => 'Kotak email tidak bisa dibaca',
        'summary' => ':checked email dicek: :added baru, :duplicate sudah ada di antrean',
        'skipped' => ':count email bank lain dilewati (bukan notifikasi transaksi)',
        'rejected' => ':count diabaikan: bukan dari bank, atau gagal pemeriksaan pengirim',
    ],

    'paste' => [
        'action' => 'Tempel email BNI',
        'heading' => 'Tempel email notifikasi BNI',
        'description' => 'Tempel seluruh teks email. Beberapa email sekaligus boleh. Hanya transaksi berhasil yang dibaca, tidak ada yang dicatat sebelum Anda setujui, dan notifikasi yang sama tidak pernah masuk dua kali.',
        'field' => 'Teks email',
        'submit' => 'Baca email',
        'done' => 'Email dibaca',
        'added' => ':count masuk antrean',
        'duplicate' => ':count sudah ada di antrean, dilewati',
    ],

];
