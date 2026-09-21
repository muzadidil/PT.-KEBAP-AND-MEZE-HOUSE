<?php

return [

    'nav' => [
        'group' => 'Pembukuan Bulanan',
        'daily_income' => 'Pemasukan Harian',
        'purchases' => 'Belanja Tunai',
        'transfers' => 'Transfer Pemasok',
        'payroll' => 'Gaji',
        'outstanding' => 'Tagihan Belum Dibayar',
        'purchase_items' => 'Barang Belanja',
        'payment_methods' => 'Cara Bayar',
        'import' => 'Impor Excel',
        'ledger' => 'Buku Besar Bulanan',
    ],

    'card' => [
        'sales' => 'Total penjualan',
        'cashless' => 'Pemasukan nontunai',
        'cash_expense' => 'Belanja tunai',
        'transfers' => 'Transfer pemasok',
        'payroll' => 'Gaji',
        'total_expenses' => 'Total pengeluaran',
        'outstanding' => 'Tagihan belum dibayar',
        'supplier_cash' => 'Titipan belanja pemasok',
        'remaining_supplier_cash' => 'Sisa titipan belanja',
        'profit' => 'Laba bersih',
        'global_balance' => 'Saldo global',
    ],

    'hint' => [
        'petty_cash' => 'Tidak ikut total penjualan, persis seperti di berkas Excel aslinya.',
        'supplier_cash' => 'Saldo awal :opening ditambah uang tunai hari itu.',
        'remaining_supplier_cash' => 'Keadaan hari terakhir yang tercatat, bukan jumlah antar hari.',
        'outstanding' => 'Seluruh tagihan yang belum lunas, bukan hanya yang jatuh di periode ini.',
        'global_balance' => 'Penjualan − pengeluaran − tagihan belum dibayar.',
        'recorded_days' => ':recorded dari :days hari tercatat',
    ],

    'col' => [
        'total_sales' => 'Total penjualan',
        'cashless' => 'Nontunai',
        'expense' => 'Belanja',
        'supplier_cash' => 'Titipan belanja',
        'remaining_supplier_cash' => 'Sisa',
    ],

    'field' => [
        'vendor' => 'Pemasok',
        'item' => 'Barang',
        'qty' => 'Jumlah',
        'unit' => 'Satuan',
        'price' => 'Harga',
        'disc' => 'Potongan',
        'tax' => 'Pajak',
        'total' => 'Total',
        'status' => 'Status',
        'method' => 'Cara bayar',
        'basic' => 'Gaji pokok',
        'bpjs' => 'Potongan BPJS',
        'grand_total' => 'Total diterima',
        'section' => 'Bagian',
        'month' => 'Bulan',
        'due_date' => 'Jatuh tempo',
        'bank' => 'Bank',
        'bank_account' => 'Nomor rekening',
        'account_name' => 'Atas nama',
        'payment_method' => 'Cara bayar',
        'last_price' => 'Harga terakhir',
        'source' => 'Diisi oleh',
        'note' => 'Catatan',
        'date' => 'Tanggal',
        'name' => 'Nama',
    ],

    'source' => [
        'manual' => 'Diketik',
        'import' => 'Excel',
    ],

    'section' => [
        'front' => 'Front Staff',
        'kitchen' => 'Kitchen Staff',
        'owner' => 'Owner',
    ],

    'status' => [
        'need' => 'Need the payment',
        'waiting' => 'Waiting the payment',
        'paid' => 'PAID',
        'kebap_paid' => 'PT KEBAP PAID',
        'aslan_paid' => 'ASLAN PAID',
        'none' => '—',
    ],

    'import' => [
        'title' => 'Impor berkas Excel bulanan',
        'intro' => 'Pengimpor mencari tiap sheet dari namanya dan tiap kolom dari judulnya. Sheet yang bentuknya tidak ia kenali dilaporkan, bukan ditebak.',
        'safe' => 'Mengimpor berkas yang sama dua kali itu aman: tiap baris diberi nomor yang diturunkan dari isinya, jadi impor kedua menimpa baris yang sama, bukan menambah baris baru. Baris yang Anda ketik sendiri tidak pernah disentuh.',
        'pick' => 'Berkas Excel',
        'payroll_month' => 'Bulan untuk sheet Payroll',
        'payroll_month_hint' => 'Sheet Payroll tidak menyebutkan bulannya sendiri — judulnya berupa kalimat bebas. Karena itu ditanyakan, bukan ditebak.',
        'run' => 'Impor',
        'reading' => 'Membaca…',
        'done' => 'Impor selesai',
        'nothing' => 'Tidak ada yang terimpor.',
        'rows' => 'Baris',
        'range' => 'Tanggal terbaca',
        'replaced' => 'Diganti',
        'sheet' => 'Sheet',
        'ok' => 'OK',
        'error' => [
            'sheet_missing' => 'Sheet tidak ada di berkas ini.',
            'header_missing' => 'Tidak ada baris header dengan judul kolom yang dicari.',
            'month_missing' => 'Pilih dulu bulan untuk sheet Payroll.',
            'unreadable' => 'Sheet ini tidak bisa dibaca.',
            'empty' => 'Tidak ada baris data di bawah headernya.',
            'failed' => 'Impor gagal: :message',
        ],
    ],

    'template' => [
        'download' => 'Unduh template',
        'hint' => 'Templatenya dibangun dari judul kolom yang sama dicari pengimpor, jadi berkas yang diisi dari situ tidak mungkin ditolak karena bentuknya.',
        'tab' => 'Petunjuk',
        'title' => 'Zeytin — template impor',
        'sheet' => 'Nama sheet jangan diubah. Sheet bernama lain diabaikan.',
        'header' => 'Baris judul jangan dihapus. Kolom tambahan boleh saja — diabaikan, bukan ditolak.',
        'date' => 'Tanggal boleh ditulis hari dulu (31/08/2026) atau sebagai tanggal Excel. Di sheet belanja, cukup baris pertama tiap hari yang diberi tanggal.',
        'money' => 'Angka uang ditulis polos, tanpa "Rp" dan tanpa desimal.',
        'month' => 'Sheet Payroll tidak berkolom bulan — bulannya ditanyakan saat berkasnya diunggah.',
        'extra' => 'Jangan tinggalkan baris contoh di dalam berkas: apa pun di bawah baris judul ikut terimpor sebagai data sungguhan.',
        'sheet_list' => 'Sheet dan judul kolomnya',
    ],

    'export' => [
        'download' => 'Unduh Excel',
        'summary' => 'Ringkasan',
        'daily' => 'Harian',
        'monthly' => 'Bulanan',
        'yearly' => 'Tahunan',
        'period' => 'Periode',
        'recorded_days' => 'Hari tercatat',
    ],

    'ledger' => [
        'daily' => 'Harian',
        'monthly' => 'Bulanan',
        'yearly' => 'Tahunan',
        'channels' => 'Saldo per channel',
        'cash' => 'Tunai',
        'noncash' => 'Non-tunai',
        'excluded' => 'excl.',
    ],

    'help' => [
        'price' => 'Cuma tawaran awal — timpa saja begitu harga pemasoknya berubah.',
        'total' => 'Dihitung (jumlah × harga) + pajak − potongan, rumus dari berkas Excel aslinya.',
        'source' => 'Baris yang dibawa berkas Excel ditandai, jadi impor berikutnya boleh menggantinya. Baris yang Anda ubah di sini menjadi milik Anda, dan tidak akan ditimpa impor.',
        'month' => 'Disimpan sebagai tanggal 1 bulan itu.',
    ],

];
