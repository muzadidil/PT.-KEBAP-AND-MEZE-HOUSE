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
        'heading' => 'Ambil email bank',
        'description' => 'Pilih periodenya (kedua tanggal ikut). Email yang sudah masuk antrean tidak pernah masuk dua kali.',
        'submit' => 'Ambil',
        'from' => 'Dari tanggal',
        'to' => 'Sampai tanggal',
        'too_long' => 'Paling lama 3 bulan sekali di sini. Untuk periode lebih panjang, jalankan php artisan bank:fetch-email --from=YYYY-MM-DD --to=YYYY-MM-DD di server.',
        'action' => 'Ambil dari email',
        'done' => 'Email dicek',
        'failed' => 'Kotak email tidak bisa dibaca',
        'summary' => ':checked email dicek: :added baru, :duplicate sudah ada di antrean',
        'other_format' => ':count email transaksi berformat lain belum bisa dibaca (kabari pengembang)',
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
