<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->nullable()->unique();
            $table->date('sold_on');
            $table->string('channel', 12);
            $table->string('source', 8)->default('pos');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('paid')->default(0);
            $table->unsignedBigInteger('change')->default(0);
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Seluruh laporan penjualan menyaring tanggal lalu mengelompokkan
            // channel; indeks ini yang dipakai semuanya.
            $table->index(['sold_on', 'channel']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            // Menu boleh dihapus dari daftar tanpa merusak struk lama, karena
            // nama dan harga ikut disalin ke baris ini saat transaksi dibuat.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedSmallInteger('qty');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
