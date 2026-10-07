<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gaji pemilik (Aslan, Leo) per bulan. Terpisah dari Gaji karyawan; satu
 * baris per pemilik per bulan. Bulannya disimpan sebagai tanggal 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('note', 200)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['owner_id', 'month']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_salaries');
    }
};
