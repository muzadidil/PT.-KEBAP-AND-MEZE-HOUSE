<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan pribadi untuk karyawan di slip gaji — apresiasi, masukan, atau
 * nasihat dari perusahaan. Opsional dan ikut tercetak di kertas slip; lihat
 * App\Support\Payroll\PayslipPdf dan resources/views/payslips/paper.blade.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->text('note')->nullable()->after('deductions');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
