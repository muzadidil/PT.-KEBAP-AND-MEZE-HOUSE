<?php

return [

    'nav' => 'Saldo Bank',
    'saved' => 'Tersimpan',
    'formula' => 'Saldo bank = saldo awal + penjualan non-tunai (BNI, Grab Food, Go Food, Go Pay) − pembayaran dari rekening (Transfer Pemasok, dan Pengeluaran yang dibayar transfer). Ini PERKIRAAN, bukan mutasi bank: biaya bank, komisi Grab/Go, dan jeda pencairan belum tercatat. Cocokkan dengan mutasi, dan atur ulang saldo awal bila perlu. Gaji yang dibayar lewat bank dicatat di Pengeluaran (bukan di Gaji), supaya tidak terhitung dua kali.',
    'before_opening' => 'Sebelum tanggal mulai, tidak dihitung',

    'card' => [
        'balance' => 'Perkiraan saldo bank',
        'as_of' => 'Per :date',
        'start' => 'Saldo di awal rentang',
        'opening' => 'Saldo awal :amount pada :date',
        'no_opening' => 'Tanggal mulai belum diatur: semua catatan dihitung, dari 0.',
    ],

    'col' => [
        'opening' => 'Saldo awal',
        'money_in' => 'Penjualan non-tunai',
        'money_out' => 'Pembayaran keluar',
        'balance' => 'Saldo',
    ],

    'opening' => [
        'action' => 'Saldo awal',
        'heading' => 'Saldo awal bank',
        'description' => 'Saldo rekening perusahaan pada tanggal mulai, sesuai mutasi bank. Hitungan dimulai dari tanggal itu.',
        'amount' => 'Saldo awal',
        'date' => 'Tanggal mulai',
        'date_hint' => 'Kosongkan untuk menghitung semua hari yang tercatat dari 0.',
    ],

];
