<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antrean notifikasi bank (BNI). Email dibaca, masuk sini berstatus
 * "menunggu", dan baru dicatat ke Transfer Pemasok setelah disetujui.
 * Nomor referensi unik: notifikasi yang sama tidak pernah tercatat dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 60)->unique();
            $table->dateTime('occurred_at');
            $table->string('type', 80)->nullable();
            $table->string('direction', 3)->default('out');
            $table->unsignedBigInteger('amount');
            $table->string('remitter', 160)->nullable();
            $table->string('beneficiary', 160)->nullable();
            $table->string('beneficiary_bank', 160)->nullable();
            $table->string('remark', 255)->nullable();
            $table->string('status', 10)->default('pending');
            $table->foreignId('supplier_transfer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 10)->default('paste');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
