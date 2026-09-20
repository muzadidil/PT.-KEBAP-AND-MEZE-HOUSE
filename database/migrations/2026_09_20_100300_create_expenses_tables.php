<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        | Satu tabel untuk semua pengeluaran. Laporan Expenses Cash, Online
        | Transfer, Salary, Tax, dan Monthly Expenses by Owner semuanya
        | tampilan tersaring dari tabel ini, bukan tabel sendiri, supaya satu
        | pengeluaran tidak pernah tercatat di dua tempat.
        */
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('spent_on');
            $table->string('category', 20);
            $table->string('method', 12);
            $table->string('description', 160);
            $table->unsignedBigInteger('amount');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            // Diisi kalau pengeluarannya ditalangi pemilik dengan uang
            // pribadi. Inilah yang dibagi 60/40 di laporan bulanan.
            $table->foreignId('paid_by_owner_id')->nullable()->constrained('owners')->nullOnDelete();
            // Belum dibayar berarti utang, dan uangnya belum berkurang. Ini
            // yang membuat Neraca tetap seimbang; lihat App\Support\Ledger.
            $table->boolean('is_paid')->default(true);
            $table->date('due_on')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['spent_on', 'category']);
            $table->index(['spent_on', 'method']);
        });

        /*
        | Setoran dan penarikan modal pemilik. Dipisah dari expenses karena
        | keduanya bukan beban: tidak mengurangi laba, hanya memindahkan uang
        | antara pemilik dan usaha.
        */
        Schema::create('capital_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->date('entry_on');
            $table->string('direction', 4);
            $table->string('method', 12);
            $table->unsignedBigInteger('amount');
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['entry_on', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_entries');
        Schema::dropIfExists('expenses');
    }
};
