<?php

return [

    'nav' => [
        'group' => 'Penggajian',
        'employees' => 'Karyawan',
        'components' => 'Komponen Gaji',
        'payslips' => 'Slip Gaji',
    ],

    'field' => [
        'name' => 'Nama lengkap',
        'nik' => 'NIK / ID karyawan',
        'position' => 'Jabatan',
        'section' => 'Bagian',
        'basic_salary' => 'Gaji pokok',
        'type' => 'Jenis',
        'component' => 'Nama item',
        'default_amount' => 'Nominal bawaan',
        'fixed' => 'Nominal fix',
        'employee' => 'Karyawan',
        'period' => 'Periode',
        'issued_on' => 'Tanggal slip',
        'number' => 'No. slip',
        'earnings' => 'Pendapatan',
        'deductions' => 'Potongan',
        'label' => 'Keterangan',
        'amount' => 'Jumlah',
        'total_earnings' => 'Total pendapatan',
        'total_deductions' => 'Total potongan',
        'net_pay' => 'Gaji bersih',
        'note' => 'Catatan untuk karyawan',
    ],

    'type' => [
        'earning' => 'Pendapatan',
        'deduction' => 'Potongan',
    ],

    'help' => [
        'nik' => 'Opsional.',
        'active' => 'Karyawan yang keluar dinonaktifkan, bukan dihapus, supaya slip lamanya tetap utuh.',
        'default_amount' => 'Kosongkan jika tidak tetap. Terisi otomatis saat item ini dipilih di slip.',
        'fixed' => 'Nominalnya dikunci ke nominal bawaan dan tidak bisa diubah di slip.',
        'lines' => 'Pilih dari daftar Komponen Gaji, atau ketik keterangan sendiri.',
        'snapshot' => 'Nama, NIK, dan jabatan disalin ke slip saat disimpan. Mengubah data karyawan belakangan tidak mengubah slip ini.',
        'note' => 'Opsional. Pesan pribadi dari perusahaan — apresiasi, masukan, atau nasihat — ikut tercetak di slip. Kosongkan jika tidak perlu.',
        'note_placeholder' => 'Mis. "Terima kasih atas kerja kerasnya bulan ini, terus semangat!"',
    ],

    'action' => [
        'add_earning' => 'Tambah tunjangan',
        'add_deduction' => 'Tambah potongan',
        'pdf' => 'Lihat PDF',
        'letterhead' => 'Kop & penandatangan',
    ],

    'letterhead' => [
        'company' => 'Nama perusahaan',
        'address' => 'Alamat',
        'city' => 'Kota (untuk tanda tangan)',
        'signer_name' => 'Nama penandatangan',
        'signer_title' => 'Jabatan penandatangan',
        'logo' => 'Logo diambil dari Data Induk → Tampilan.',
        'saved' => 'Kop slip disimpan',
    ],

    'preview' => 'Pratinjau slip',
    'duplicate' => 'Slip untuk karyawan ini pada bulan itu sudah ada (:number). Ubah slip tersebut, bukan membuat yang baru.',

    // Isi slip. Selalu dicetak dalam bahasa Indonesia, apa pun bahasa
    // aplikasinya: slip diserahkan ke karyawan, bukan dibaca pemilik.
    'slip' => [
        'company' => 'NAMA PERUSAHAAN',
        'title' => 'SLIP GAJI KARYAWAN',
        'period' => 'Periode: :period',
        'number' => 'No: :number',
        'name' => 'Nama',
        'nik' => 'NIK',
        'position' => 'Jabatan',
        'earnings' => 'PENDAPATAN',
        'deductions' => 'POTONGAN',
        'basic_salary' => 'Gaji Pokok',
        'total_earnings' => 'Jumlah Pendapatan',
        'total_deductions' => 'Jumlah Potongan',
        'net_pay' => 'GAJI BERSIH (Take Home Pay)',
        'spelled' => 'Terbilang: :words',
        'note_label' => 'Catatan:',
        'recipient' => 'Penerima,',
        'signer' => 'Pimpinan',
        'blank' => '(............................)',
    ],

];
