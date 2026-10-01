<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagi hasil investor lokal (pemilik lokasi) di Laporan Pajak, dalam persen.
 * Kosong = pakai persen bawaan. Hanya berlaku untuk pelaporan; data asli
 * tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_filings', function (Blueprint $table) {
            $table->decimal('investor_share', 5, 2)->nullable()->after('revenue_override');
        });
    }

    public function down(): void
    {
        Schema::table('tax_filings', function (Blueprint $table) {
            $table->dropColumn('investor_share');
        });
    }
};
