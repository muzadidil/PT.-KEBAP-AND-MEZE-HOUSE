<?php

return [

    'nav' => 'Sisa Cash Kasir',
    'saved' => 'Tersimpan',
    'formula' => 'Sisa cash = saldo awal + penjualan cash − belanja tunai. Dibaca dari Pemasukan Harian dan Belanja Tunai, sama dengan Buku Besar Bulanan. Petty cash, transfer pemasok, dan gaji tidak dihitung.',
    'before_opening' => 'Sebelum tanggal mulai, tidak dihitung',

    'card' => [
        'balance' => 'Sisa cash di kasir',
        'as_of' => 'Per :date',
        'start' => 'Saldo di awal rentang',
        'opening' => 'Saldo awal :amount pada :date',
        'no_opening' => 'Tanggal mulai belum diatur: semua catatan dihitung, dari 0.',
    ],

    'col' => [
        'opening' => 'Saldo awal',
        'cash_in' => 'Penjualan cash',
        'cash_out' => 'Belanja tunai',
        'balance' => 'Sisa cash',
    ],

    'opening' => [
        'action' => 'Saldo awal',
        'heading' => 'Saldo awal cash',
        'description' => 'Uang cash yang sudah ada di kasir pada tanggal mulai. Hitungan dimulai dari tanggal itu.',
        'amount' => 'Saldo awal',
        'date' => 'Tanggal mulai',
        'date_hint' => 'Kosongkan untuk menghitung semua hari yang tercatat dari 0.',
    ],

];
