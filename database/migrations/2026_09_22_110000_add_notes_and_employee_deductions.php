<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan pada pengeluaran, dan potongan gaji karyawan yang lahir darinya.
 *
 * Contoh yang memicunya: gelas pecah, belanja gelas pengganti dicatat, dan
 * karyawan yang memecahkannya dipotong gajinya. Potongan disimpan di tabel
 * sendiri, terhubung ke baris belanja/transfer asalnya, dan ke slip gaji yang
 * akhirnya memotongnya — sehingga satu potongan tidak bisa terpotong dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('note', 200)->nullable()->after('total');
        });

        Schema::table('supplier_transfers', function (Blueprint $table) {
            $table->string('note', 200)->nullable()->after('status');
        });

        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('amount');
            $table->string('reason', 200);

            // Baris pengeluaran asalnya. Satu baris, satu potongan.
            $table->nullableMorphs('source');

            // Slip yang memotongnya; kosong berarti belum dipotong. Slip yang
            // dihapus mengembalikan potongannya ke antrean, bukan menghapusnya.
            $table->foreignId('payslip_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->index(['employee_id', 'payslip_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_deductions');

        Schema::table('supplier_transfers', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
