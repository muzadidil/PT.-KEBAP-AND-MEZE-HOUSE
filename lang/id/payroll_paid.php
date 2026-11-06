<?php

return [
    'col' => 'Dibayar',
    'unpaid' => 'Belum dicatat',
    'submit' => 'Catat pembayaran',
    'nothing' => 'Tidak ada gaji yang dicatat',
    'nothing_body' => 'Semua baris di sini sudah punya tanggal bayar, atau bulannya kosong.',
    'marked' => 'Pembayaran gaji dicatat',
    'marked_body' => ':count orang · :total · :date · lewat :via. Jumlahnya sekarang mengurangi saldo.',
    'unmarked' => ':count baris dikembalikan ke belum dibayar',

    'action' => 'Catat pembayaran gaji',
    'heading' => 'Catat pembayaran gaji satu bulan',
    'description' => 'Pilih bulan gaji, tanggal uangnya keluar, dan lewat apa. Semua baris bulan itu yang belum bertanggal bayar ikut dicatat, dan jumlahnya mengurangi Saldo Bank (lewat bank) atau Sisa Cash (tunai) pada tanggal bayar. Jangan catat gaji yang sama lagi di Pengeluaran, nanti terpotong dua kali.',

    'field' => [
        'month' => 'Gaji bulan',
        'paid_on' => 'Tanggal dibayar',
        'paid_on_help' => 'Tanggal uangnya keluar (lihat mutasi bank), bukan bulan gajinya.',
        'paid_via' => 'Dibayar lewat',
    ],

    'via' => [
        'bank' => 'Bank (transfer)',
        'cash' => 'Tunai',
    ],

    'bulk' => [
        'paid' => 'Catat dibayar',
        'paid_heading' => 'Catat pembayaran untuk baris terpilih',
        'paid_description' => 'Hanya baris yang belum bertanggal bayar yang dicatat. Cocok kalau tidak semua orang dibayar di tanggal atau cara yang sama.',
        'unpaid' => 'Batalkan catatan bayar',
        'unpaid_heading' => 'Batalkan catatan pembayaran?',
        'unpaid_description' => 'Gaji terpilih kembali berstatus belum dibayar dan tidak lagi mengurangi saldo.',
    ],

    'report' => [
        'col_paid' => 'Tanggal dibayar',
        'btn_record' => 'Catat tanggal bayar',
        'btn_change' => 'Ubah',
        'btn_undo' => 'Batalkan',
        'heading' => 'Tanggal pembayaran gaji :month',
        'description' => 'Isi tanggal uang gaji keluar (lihat mutasi bank) dan lewat apa. Mulai tanggal itu, total gaji bulan ini mengurangi Saldo Bank (lewat bank) atau Sisa Cash (tunai). Jangan catat gaji yang sama lagi di Pengeluaran.',
        'saved_body' => 'Gaji :month · :count orang · dibayar :date · lewat :via. Saldo sekarang berkurang pada tanggal itu.',
        'partial' => 'baru :paid dari :people orang',
        'undo_heading' => 'Batalkan catatan bayar gaji :month?',
        'undone' => 'Catatan bayar dibatalkan',
        'note' => 'Isi tanggal pembayaran tiap bulan supaya gajinya ikut mengurangi Saldo Bank. Tanpa tanggal, gaji tidak mengurangi saldo apa pun.',
    ],
];
