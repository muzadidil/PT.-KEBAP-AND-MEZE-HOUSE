<?php

return [

    'source' => 'Sumber: omzet dari Pemasukan Harian di Pembukuan Bulanan. Perubahan di sini hanya berlaku untuk laporan ini — data asli, Buku Besar Bulanan, dan Neraca tidak pernah berubah.',
    'saved' => 'Tersimpan',
    'adjusted' => 'Diedit',
    'adjusted_hint' => 'Tarif bisa diatur. Restoran bisa jadi terutang pajak restoran daerah (PBJT), bukan PPN — pastikan ke konsultan pajak. "Diedit" menandai bulan yang omzetnya ditimpa untuk pelaporan.',
    'reported' => 'Sudah dilapor',
    'not_reported' => 'Belum dilapor',

    'month' => [
        'year_view' => 'Setahun',
        'daily_title' => 'Penjualan per hari',
        'inclusive' => 'harga sudah termasuk pajak',
        'exclusive' => 'pajak ditambahkan di atas',
    ],

    'card' => [
        'system' => 'Angka sistem: :amount',
        'paid' => 'Disetor: :amount',
        'final' => 'PPh Final',
        'ppn' => 'PPN / pajak restoran terutang',
    ],

    'col' => [
        'system_revenue' => 'Omzet sistem',
        'investor_share' => 'Bagi hasil investor lokal',
        'tax_base' => 'Dasar pajak',
        'revenue' => 'Omzet dilaporkan',
        'final_due' => 'PPh Final terutang',
        'paid_final' => 'PPh Final disetor',
        'ppn_output' => 'PPN keluaran',
        'ppn_input' => 'PPN masukan',
        'ppn_due' => 'PPN terutang',
        'paid_ppn' => 'PPN disetor',
        'outstanding' => 'Sisa bayar',
        'status' => 'Status',
    ],

    'field' => [
        'investor_share' => 'Bagi hasil investor lokal (%)',
        'revenue_override' => 'Omzet untuk pelaporan',
        'final_rate' => 'Tarif PPh Final',
        'ppn_rate' => 'Tarif PPN / pajak restoran',
        'ppn_inclusive' => 'Harga sudah termasuk pajak',
        'ppn_input' => 'PPN masukan (bisa dikreditkan)',
        'paid_final' => 'PPh Final disetor',
        'paid_ppn' => 'PPN disetor',
        'is_reported' => 'Sudah dilaporkan ke kantor pajak',
        'note' => 'Catatan',
    ],

    'rates' => [
        'action' => 'Tarif bawaan',
        'heading' => 'Tarif pajak bawaan',
        'description' => 'Dipakai untuk semua bulan, kecuali bulan yang mengisi tarifnya sendiri.',
        'investor_hint' => 'Bagian omzet untuk investor lokal pemilik lokasi. Tampil sebagai baris sendiri; pajak dihitung dari sisanya. Isi 0 kalau tidak ada.',
        'ppn_hint' => 'PPN 11%. Pajak restoran (PBJT) biasanya 10% — isi sesuai yang berlaku untuk Anda.',
        'inclusive_hint' => 'Aktif: pajak dipisah dari harga jual. Mati: pajak ditambahkan di atas harga.',
    ],

    'edit' => [
        'button' => 'Ubah',
        'heading' => 'Ubah :month',
        'description' => 'Khusus pelaporan. Kolom kosong memakai angka sistem atau tarif bawaan. Data asli tidak berubah.',
        'revenue_hint' => 'Kosongkan untuk memakai omzet sistem.',
        'rate_hint' => 'Kosongkan untuk memakai tarif bawaan.',
        'save' => 'Simpan',
        'use_default' => 'Pakai bawaan',
        'inclusive_yes' => 'Ya, sudah termasuk',
        'inclusive_no' => 'Tidak, ditambahkan di atas',
    ],

    'history' => [
        'title' => 'Riwayat perubahan',
        'empty' => 'Belum ada perubahan untuk tahun ini.',
        'when' => 'Kapan',
        'who' => 'Siapa',
        'field' => 'Kolom',
        'from' => 'Dari',
        'to' => 'Menjadi',
    ],

];
