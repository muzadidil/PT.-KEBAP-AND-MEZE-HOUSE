<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan Pajak: koreksi angka untuk pelaporan, satu baris per bulan.
 *
 * Tabel ini TIDAK menyentuh data asli. Omzet dan angka lain yang ditimpa di
 * sini hanya berlaku di Laporan Pajak; Buku Besar, Neraca, dan laporan
 * penjualan tetap membaca sumbernya. Kolom kosong berarti "pakai angka
 * dari sistem". Setiap perubahan dicatat di tax_filing_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_filings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            $table->unsignedBigInteger('revenue_override')->nullable();
            $table->decimal('final_rate', 5, 2)->nullable();
            $table->decimal('ppn_rate', 5, 2)->nullable();
            $table->boolean('ppn_inclusive')->nullable();
            $table->unsignedBigInteger('ppn_input')->default(0);
            $table->unsignedBigInteger('paid_final')->default(0);
            $table->unsignedBigInteger('paid_ppn')->default(0);
            $table->boolean('is_reported')->default(false);
            $table->text('note')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('tax_filing_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('field', 40);
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_filing_logs');
        Schema::dropIfExists('tax_filings');
    }
};
