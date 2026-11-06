<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kapan dan lewat apa gaji dibayarkan. Gaji disimpan per bulan tanpa tanggal
 * bayar, jadi Saldo Bank dan Sisa Cash tidak tahu kapan uangnya keluar.
 * Kolom ini diisi lewat tombol "Catat pembayaran gaji"; yang kosong berarti
 * belum dibayar dan tidak mengurangi saldo apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->date('paid_on')->nullable()->after('grand_total');
            $table->string('paid_via', 10)->nullable()->after('paid_on');

            $table->index(['paid_via', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex(['paid_via', 'paid_on']);
            $table->dropColumn(['paid_on', 'paid_via']);
        });
    }
};
