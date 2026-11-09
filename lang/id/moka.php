<?php

/*
| Judul kolom di bawah ini dipakai dua arah: sebagai label tabel (bahasa
| yang sedang dipakai) dan sebagai pengenal judul kolom berkas Moka. Versi
| Inggrisnya karena itu HARUS persis judul di berkas Moka — "Item Name",
| "Gross Sales", dan seterusnya. Mengubahnya berarti pengimpor tidak lagi
| mengenali berkasnya.
*/

return [

    'nav' => [
        'item_sales' => 'Penjualan per Menu (Moka)',
    ],

    'page' => [
        'subheading' => 'Rekap per menu dari laporan Report Item Sales Moka. Terpisah dari Pemasukan Harian: angka di sini tidak ikut Total penjualan di laporan mana pun, jadi tidak terhitung dua kali.',
        'empty_heading' => 'Belum ada laporan Moka',
        'empty_description' => 'Unduh Report Item Sales dari Moka, lalu unggah lewat tombol Impor dari Moka.',
    ],

    'action' => [
        'import' => 'Impor dari Moka',
    ],

    'import' => [
        'heading' => 'Impor laporan dari Moka',
        'description' => 'Unggah berkas hasil unduhan Moka apa adanya (Excel atau CSV); jenis laporannya dikenali otomatis. Report Item Sales (rekap per menu) masuk ke Penjualan per Menu. Report Transaction dan Report Item Details (per struk) masuk ke Pemasukan Harian: Net Sales dijumlahkan per tanggal dan cara bayar, dan tanggal yang sudah terisi dilewati, tidak ditimpa. Kalau ada yang salah, tidak ada yang disimpan.',
        'file' => 'Berkas laporan Moka',
        'period_hint' => 'Berkas Moka tidak memuat periodenya. Kotak ini diisi otomatis dari nama berkas bila terbaca; isi sendiri kalau namanya tidak memuat tanggal, dan periksa dulu sebelum mengimpor.',
    ],

    'filter' => [
        'period' => 'Periode',
        'category' => 'Kategori',
    ],

    'col' => [
        'period' => 'Periode',
        'item_name' => 'Menu',
        'variant_name' => 'Varian',
        'category_name' => 'Kategori',
        'sku' => 'SKU',
        'qty_sold' => 'Terjual',
        'qty_refunded' => 'Dikembalikan',
        'gross_sales' => 'Penjualan kotor',
        'discount' => 'Diskon',
        'refund' => 'Refund',
        'net_sales' => 'Penjualan bersih',
    ],

    'error' => [
        'no_header' => 'Baris judul tidak ditemukan. Kolom yang dicari: :columns. Pakai berkas Report Item Sales dari Moka apa adanya.',
        'unknown_report' => 'Berkas ini tidak dikenali sebagai laporan Moka. Yang bisa diimpor: Report Item Sales (rekap per menu), serta Report Transaction dan Report Item Details (per struk). Pakai berkas hasil unduhan Moka apa adanya.',
        'period_missing' => 'Periode laporan belum diketahui: nama berkasnya tidak memuat tanggal. Isi kotak Dari dan Sampai.',
        'empty' => 'Berkasnya tidak berisi baris menu.',
        'period_order' => 'Tanggal "Sampai" tidak boleh sebelum tanggal "Dari".',
        'period_future' => 'Periode tidak boleh melewati hari ini.',
        'fraction' => 'bukan bilangan bulat (":value")',
        'net_mismatch' => 'Baris :row (:name): Net Sales :net tidak sama dengan Gross Sales dikurangi Discount dan Refund (:expected). Pastikan ini berkas Report Item Sales yang utuh.',
        'total_mismatch' => 'Baris Total di berkas (:file) tidak sama dengan jumlah baris menu (:sum). Berkasnya mungkin terpotong.',
        'overlap' => 'Periode :periods sudah ada dan tanggalnya bersinggungan dengan periode ini, jadi menu yang sama akan terhitung dua kali. Impor periode yang persis sama untuk menggantinya, atau hapus dulu periode lama di halaman ini.',
    ],

    'note' => [
        'compare' => 'Penjualan bersih Moka :moka. Pemasukan Harian di periode yang sama :books (:days hari tercatat), selisih :diff. Keduanya beda sumber, jadi tidak harus sama persis; selisih besar patut dicek.',
        'no_books' => 'Penjualan bersih Moka :moka. Belum ada Pemasukan Harian di periode ini, jadi belum ada yang dibandingkan.',
        'separate' => 'Angka Moka tidak ikut Total penjualan di laporan mana pun.',
    ],

];
