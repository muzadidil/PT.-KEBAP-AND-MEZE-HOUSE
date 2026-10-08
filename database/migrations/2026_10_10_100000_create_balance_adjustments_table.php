<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koreksi saldo: penyesuaian tercatat (dengan alasan) atas Sisa Cash Kasir
 * atau Saldo Bank, misalnya biaya transfer bank atau selisih uang di laci.
 * Tidak mengubah catatan lama; hanya menggeser saldo mulai tanggalnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('account', 4);          // cash | bank
            $table->string('direction', 5);        // plus | minus
            $table->unsignedBigInteger('amount');
            $table->string('reason', 200);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['account', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_adjustments');
    }
};
