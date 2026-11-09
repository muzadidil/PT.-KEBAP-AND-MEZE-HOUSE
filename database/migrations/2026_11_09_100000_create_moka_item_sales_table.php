<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        | Rekap penjualan per menu dari laporan "Report Item Sales" Moka.
        |
        | Satu baris = satu menu dalam satu periode laporan, bukan satu struk:
        | Moka hanya memberi jumlah terjual dan rupiahnya, tanpa tanggal per
        | transaksi. Karena itu tabel ini sengaja terpisah dari `sales` dan
        | `daily_incomes` — penjualan yang sama sudah tercatat di Pemasukan
        | Harian, dan meleburnya membuat satu bulan terhitung dua kali.
        |
        | Nama menu dan kategori disimpan sebagai teks Moka apa adanya, tidak
        | dihubungkan ke tabel `products` / `categories`: ejaannya berbeda
        | ("Chiken shawarma", "Pumkin") dan menu Moka tidak selalu ada padanan.
        |
        | Tidak ada indeks unik: kunci aslinya (menu + varian + kategori)
        | memuat kolom yang boleh kosong, dan MySQL menganggap NULL tidak
        | pernah sama dengan NULL. Pengimpor menggantinya per periode.
        */
        Schema::create('moka_item_sales', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('item_name', 120);
            $table->string('variant_name', 120)->nullable();
            $table->string('category_name', 80)->nullable();
            $table->string('sku', 60)->nullable();
            $table->unsignedInteger('qty_sold')->default(0);
            $table->unsignedInteger('qty_refunded')->default(0);
            // Rupiah bulat. Diskon dan refund disimpan sebagai besarnya
            // potongan (positif); Moka menulisnya dengan tanda minus.
            $table->unsignedBigInteger('gross_sales')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('refund')->default(0);
            // Angka Net Sales dari Moka. Pengimpor menolak baris yang tidak
            // sama dengan gross − diskon − refund, jadi tidak bisa menyimpang.
            $table->bigInteger('net_sales')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moka_item_sales');
    }
};
