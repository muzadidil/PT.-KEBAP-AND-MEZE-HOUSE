<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Report — catatan harian yang dibagikan ke WhatsApp, meniru contoh
 * laporan tim: bagian utama (Sales, Operation, Staff, …) dengan sub-catatan
 * di bawahnya, bertingkat tanpa batas.
 *
 * Satu tabel untuk bagian utama maupun sub-catatannya, lewat `parent_id` —
 * sama seperti meeting_tasks di Progres Rapat. Bedanya di sini tidak ada
 * status selesai/belum: catatan bukan tugas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_notes', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('parent_id')->nullable()->constrained('daily_notes')->cascadeOnDelete();
            $table->string('text', 500);
            // Angka Rupiah opsional di sisi kanan catatan — mis. "Total Sales: Rp 4.606.140".
            $table->integer('nominal')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['date', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_notes');
    }
};
