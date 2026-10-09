<?php

return [

    'yes' => 'Ya',
    'no' => 'Tidak',
    'month' => 'Bulan',
    'guide_tab' => 'Petunjuk',
    'lists_tab' => 'Daftar',

    'action' => [
        'template' => 'Unduh template',
        'import' => 'Impor Excel',
        'download' => 'Unduh',
    ],

    'template' => [
        'heading' => 'Template :title',
        'month_hint' => 'Tanggal di template dibatasi ke bulan ini, supaya salah ketik bulan atau tahun langsung ketahuan.',
    ],

    'import' => [
        'heading' => 'Impor :title dari Excel',
        'description' => 'Pakai berkas dari tombol Unduh template. Kalau ada satu baris yang salah, tidak ada yang disimpan, dan daftar kesalahannya ditampilkan per baris.',
        'file' => 'Berkas Excel',
    ],

    'guide' => [
        'title' => 'Template impor — :title',
        'subtitle' => 'Isi sheet ":sheet", lalu unggah lewat tombol Impor Excel di menu :title.',
        'rules' => 'Cara mengisi',
        'examples' => 'Contoh pengisian (hanya contoh, jangan disalin ke sheet data)',
    ],

    'rule' => [
        'header' => 'Baris judul jangan diubah. Urutan kolom boleh diubah, dan kolom tambahan diabaikan.',
        'required' => 'Kolom bertanda * wajib diisi. Petunjuk tiap kolom muncul saat selnya dipilih.',
        'date' => 'Tanggal ditulis hari dulu: 5/8/2026 berarti 5 Agustus.',
        'money' => 'Angka ditulis polos: 35000, bukan Rp 35.000 atau 35rb.',
        'list' => 'Kolom berpanah dipilih dari daftar.',
        'update' => 'Baris yang :keys-nya sudah ada di aplikasi diperbarui, bukan ditambah. Sel yang dikosongkan tidak menghapus isi lama.',
        'dedupe' => 'Baris yang isinya persis sama dengan yang sudah tercatat dilewati, jadi berkas yang sama aman diimpor dua kali.',
        'all_or_nothing' => 'Kalau ada satu baris yang salah, seluruh berkas ditolak dan kesalahannya ditampilkan per baris. Betulkan, lalu unggah lagi.',
        'rows' => 'Aturan isian disiapkan untuk :rows baris.',
    ],

    'prompt' => [
        'required' => 'Wajib diisi.',
        'date' => 'Tanggal, hari dulu: 5/8/2026.',
        'money' => 'Angka saja, tanpa Rp dan titik: 35000.',
        'number' => 'Bilangan bulat.',
        'open_list' => 'Pilih dari daftar. Isian baru tetap boleh.',
        'closed_list' => 'Pilih dari daftar.',
        'creates' => 'Pilih dari daftar. Nama baru dibuat otomatis saat impor.',
        'text' => 'Paling banyak :max huruf.',
        'free' => 'Boleh dikosongkan.',
    ],

    'error' => [
        'required' => 'wajib diisi',
        'too_long' => 'terlalu panjang, paling banyak :max huruf',
        'number' => 'bukan angka (":value")',
        'between' => 'harus antara :min dan :max',
        'min' => 'tidak boleh kurang dari :min',
        'date' => 'bukan tanggal (":value"), tulis hari dulu: 5/8/2026',
        'date_rule' => 'Isi dengan tanggal, hari dulu: 5/8/2026.',
        'boolean' => 'isi Ya atau Tidak, bukan ":value"',
        'choice' => '":value" tidak ada di daftar',
        'open_list' => 'Belum ada di daftar. Tetap pakai isian ini?',
        'closed_list' => 'Pilih salah satu dari daftar.',
        'no_header' => 'Baris judul tidak ditemukan. Kolom yang dicari: :columns. Pakai berkas dari tombol Unduh template.',
        'row' => 'Baris :row · :column: :message',
        'duplicate' => 'Baris :row: sama dengan baris :first. Hapus salah satunya.',
        'save' => 'Baris :row tidak bisa disimpan: :message',
        'failed' => 'Impor gagal: :message',
    ],

    'pos' => [
        'action' => 'Impor penjualan kasir',
        'heading' => 'Impor penjualan dari laporan kasir',
        'description' => 'Pakai berkas "Report Item Details" (CSV atau Excel). Net Sales dijumlahkan per tanggal dan cara bayar (Cash, BNI, GrabFood, GoFood, GoPay). Tanggal yang sudah ada barisnya dilewati, tidak pernah ditimpa. Kalau ada yang salah, tidak ada yang disimpan.',
        'file' => 'Berkas CSV atau Excel',
        'unreadable' => 'Berkas tidak bisa dibaca. Pakai berkas CSV atau Excel hasil ekspor dari kasir.',
        'missing_column' => 'Kolom ":column" tidak ada. Pakai berkas "Report Item Details" apa adanya.',
        'bad_date' => 'Baris :row: tanggal ":value" tidak valid. Pakai hari-bulan-tahun, mis. 30-09-2026.',
        'bad_amount' => 'Baris :row: Net Sales ":value" bukan angka.',
        'unknown_method' => 'Cara bayar ":method" (baris :rows) tidak dikenal. Yang dikenal: :valid. Ubah namanya di berkas, atau minta ditambahkan.',
        'skipped_note' => 'Dilewati karena sudah terisi: :dates',
    ],

    'telegram' => [
        'action' => 'Tarik dari Telegram',
        'heading' => 'Tarik penjualan harian dari Telegram',
        'description' => 'Tempel pesan penjualan harian, atau unggah hasil "Export chat history" Telegram Desktop (JSON). Satu pesan = satu hari, diawali "Penjualan <tanggal>". Tanggal yang sudah ada barisnya dilewati, tidak pernah ditimpa. Kalau ada yang salah, tidak ada yang disimpan.',
        'text' => 'Tempel pesan',
        'file' => 'Atau unggah berkas ekspor Telegram',
        'file_help' => 'Berkas result.json dari Telegram Desktop → ⋮ → Export chat history → format JSON.',
        'empty' => 'Tempel pesan atau unggah berkas dulu.',
        'unreadable' => 'Berkas JSON dari Telegram tidak bisa dibaca.',
        'nothing_found' => 'Tidak ada pesan penjualan. Pesan harus diawali "Penjualan 26 Sep 2026", lalu satu baris per channel, mis. "Cash: 1.545.390".',
        'unknown_label' => ':date: nama ":label" tidak dikenal. Yang dikenal: :valid.',
        'bad_amount' => ':date: angka ":value" di baris :label tidak wajar. Tulis seperti 1.545.390.',
        'no_amounts' => ':date: tidak ada angka satu pun. Tulis satu baris per channel, mis. "Cash: 1.545.390".',
        'total_mismatch' => ':date: Total :total tidak sama dengan jumlah channel :sum. Periksa angkanya.',
        'repeated_note' => 'Dikirim lebih dari sekali, pesan terakhir dipakai: :dates',
    ],

    'count' => [
        'created' => ':count baru',
        'updated' => ':count diperbarui',
        'skipped' => ':count sudah ada',
        'imported' => ':count baris masuk',
        'replaced' => ':count diganti',
        'manual' => ':count dilewati (sudah diketik manual)',
    ],

    'result' => [
        'done' => 'Impor selesai',
        'unchanged' => 'Tidak ada yang berubah',
        'empty' => 'Berkasnya tidak berisi baris data.',
        'failed' => 'Impor dibatalkan: :count kesalahan',
        'more' => '…dan :count kesalahan lain.',
        'nothing_saved' => 'Tidak ada yang disimpan. Betulkan berkasnya, lalu unggah lagi.',
        'range' => 'Tanggal terbaca: :range',
    ],

];
