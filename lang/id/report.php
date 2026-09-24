<?php

return [

    'from' => 'Dari',
    'to' => 'Sampai',
    'month' => 'Bulan',
    'year' => 'Tahun',
    'apply' => 'Terapkan',
    'period' => 'Periode',
    'date' => 'Tanggal',
    'week' => 'Minggu',
    'days' => 'Hari',
    'transactions' => 'Transaksi',
    'total' => 'Total',
    'average_per_recorded_day' => 'Rata-rata per hari tercatat',
    'average_hint' => 'Dari :days hari yang ada catatan penjualannya.',
    'no_data' => 'Belum ada yang tercatat di periode ini.',
    'print' => 'Cetak',
    'source_bookkeeping' => 'Sumber: Pemasukan Harian di Pembukuan Bulanan — sama dengan Buku Besar Bulanan.',
    'open_ledger' => 'Buka Buku Besar untuk rentang ini',
    'people' => 'Orang',

    // Asal angka tiap laporan, ditulis di atas tabelnya.
    'source' => [
        'cash_expenses' => 'Sumber: Belanja Tunai di Pembukuan Bulanan — totalnya sama dengan Belanja tunai di Buku Besar untuk rentang yang sama.',
        'online_transfers' => 'Sumber: Transfer Pemasok di Pembukuan Bulanan — totalnya sama dengan Transfer pemasok di Buku Besar untuk rentang yang sama.',
        'salary' => 'Sumber: Gaji di Penggajian — sama dengan Gaji di Buku Besar. Hanya total per bulan; rincian per orang hanya untuk Super Admin.',
        'tax' => 'Sumber: menu Pengeluaran, kategori Pajak. Belum termasuk di Buku Besar Bulanan.',
    ],
    'export_csv' => 'Unduh CSV',
    'export_excel' => 'Unduh Excel',
    'pdf' => 'PDF',
    'all_dates' => 'Seluruh tanggal',
    'yes' => 'Ya',
    'no' => 'Belum',
    'grand_total' => 'Total keseluruhan',
    'as_of' => 'Per tanggal',

    'preset' => [
        'today' => 'Hari ini',
        'last_year' => 'Tahun lalu',
        'this_month' => 'Bulan ini',
        'last_month' => 'Bulan lalu',
        'this_year' => 'Tahun ini',
        'last_7' => '7 hari terakhir',
        'last_30' => '30 hari terakhir',
    ],

    'summary' => [
        'sales' => 'Penjualan',
        'expenses' => 'Pengeluaran',
        'profit' => 'Laba',
    ],

    'balance' => [
        'assets' => 'Aset',
        'cash_on_hand' => 'Kas di laci',
        'bank' => 'Bank & hasil nontunai',
        'total_assets' => 'Total aset',

        'liabilities' => 'Kewajiban',
        'supplier_payable' => 'Utang pemasok',
        'tax_payable' => 'Utang pajak',
        'other_payable' => 'Tagihan lain belum dibayar',
        'total_liabilities' => 'Total kewajiban',

        'equity' => 'Ekuitas',
        'owner_capital' => 'Modal pemilik',
        'retained_earnings' => 'Laba terkumpul',
        'total_equity' => 'Total ekuitas',

        'liabilities_and_equity' => 'Kewajiban + ekuitas',
        'balanced' => 'Seimbang.',
        'not_balanced' => 'Selisih :amount. Ini semestinya tidak pernah terjadi — mohon laporkan.',

        'invested' => 'Disetor',
        'withdrawn' => 'Ditarik',
        'advanced' => 'Ditalangi sendiri',
        'capital' => 'Modal',

        'note' => 'Semua angka di sini dihitung ulang dari penjualan, pengeluaran, dan catatan modal setiap kali halaman ini dibuka. Tidak ada saldo yang disimpan terpisah.',
    ],

    'owner_split' => [
        'title' => 'Pengeluaran Pemilik',
        'intro' => 'Pengeluaran yang ditalangi pemilik dengan uang pribadi selama periode ini. Tiap pemilik menanggung porsi yang disepakati; yang membayar lebih dari porsinya berhak menerima selisihnya.',
        'total_advanced' => 'Total ditalangi',
        'paid' => 'Yang dibayar',
        'share' => 'Porsinya',
        'balance' => 'Selisih',
        'receives' => 'menerima',
        'owes' => 'menyetor',
        'settled' => 'impas',
        'share_warning' => 'Porsi pemilik berjumlah :total%, bukan 100%. Betulkan di Data Induk → Pemilik sebelum memakai hasil pembagian ini.',
        'no_owners' => 'Belum ada pemilik aktif. Tambahkan di Data Induk → Pemilik.',
    ],

];
