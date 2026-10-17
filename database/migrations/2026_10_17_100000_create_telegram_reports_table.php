<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antrean laporan penjualan dari grup Telegram. Pesan dibaca bot, masuk sini
 * berstatus "menunggu", dan baru dicatat ke Pemasukan Harian setelah Admin
 * mengonfirmasi. Satu pesan yang sama untuk tanggal yang sama tidak pernah
 * masuk dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_reports', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 40);
            $table->unsignedBigInteger('message_id');
            $table->string('sender', 120)->nullable();
            $table->dateTime('sent_at');
            $table->date('report_date');
            $table->json('amounts');
            $table->text('raw_text')->nullable();
            $table->string('status', 10)->default('pending');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['chat_id', 'message_id', 'report_date']);
            $table->index(['status', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_reports');
    }
};
