<?php

return [

    'nav' => 'Sisa Cash Kasir',
    'saved' => 'Tersimpan',
    'formula' => 'Sisa cash = saldo awal + penjualan cash − pengeluaran tunai. Penjualan cash dari Pemasukan Harian. Pengeluaran tunai: Belanja Tunai, ditambah menu Pengeluaran yang dibayar Cash (termasuk gaji tunai). Yang ditalangi pemilik, belum dibayar, atau lewat transfer tidak dihitung, begitu juga petty cash. Hindari mencatat belanja yang sama di dua menu: akan terkurang dua kali.',
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
        'cash_out' => 'Pengeluaran tunai',
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
