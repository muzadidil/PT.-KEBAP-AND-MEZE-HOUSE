<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penggajian: karyawan, komponen gaji, dan slip gaji bulanan.
 *
 * Bentuknya mengikuti aplikasi Slip Gaji (repo slip_gaji_cv_alfarisy):
 * karyawan dengan gaji pokok, master item pendapatan & potongan yang punya
 * nominal bawaan dan tanda "fix", lalu slip per karyawan per bulan.
 *
 * Seluruhnya hanya untuk Super Admin — gaji per orang tidak boleh terlihat
 * oleh Admin; lihat App\Filament\Admin\Concerns\ForSuperAdmin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('nik', 40)->nullable()->unique();
            $table->string('position', 80)->nullable();
            // Bagian, sama dengan pembagian di sheet Payroll klien:
            // Front Staff, Kitchen Staff, Owner.
            $table->string('section', 40)->nullable();
            $table->unsignedBigInteger('basic_salary')->default(0);
            // Karyawan yang keluar dinonaktifkan, bukan dihapus: slip lamanya
            // tetap menunjuk ke orang yang sama.
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        /*
        | Item pendapatan (tunjangan, lembur) dan potongan (BPJS, kasbon).
        | `fixed` berarti nominalnya dikunci ke nominal bawaan saat dipilih
        | di slip — ditegakkan oleh model, bukan cuma oleh formulir.
        */
        Schema::create('pay_components', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->string('name', 80);
            $table->unsignedBigInteger('default_amount')->default(0);
            $table->boolean('fixed')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'name']);
        });

        /*
        | Slip gaji. Nama, NIK, dan jabatan disalin ke slip saat dibuat:
        | slip adalah dokumen yang sudah diserahkan, jadi ia tidak boleh
        | berubah hanya karena data karyawannya diubah belakangan.
        |
        | Rincian tunjangan dan potongan disimpan sebagai JSON — daftar
        | {label, amount} persis seperti yang tercetak di slip.
        */
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period');
            $table->date('issued_on');

            $table->string('employee_name', 120);
            $table->string('employee_nik', 40)->nullable();
            $table->string('employee_position', 80)->nullable();

            $table->unsignedBigInteger('basic_salary')->default(0);
            $table->json('earnings')->nullable();
            $table->json('deductions')->nullable();
            $table->unsignedBigInteger('total_earnings')->default(0);
            $table->unsignedBigInteger('total_deductions')->default(0);
            $table->bigInteger('net_pay')->default(0);

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Satu karyawan, satu slip per bulan. Slip kembar berarti gaji
            // yang sama tercatat dua kali.
            $table->unique(['employee_id', 'period']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('pay_components');
        Schema::dropIfExists('employees');
    }
};
