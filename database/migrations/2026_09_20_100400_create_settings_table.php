<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        | Pengaturan yang boleh diubah pemilik lewat halaman admin, bukan
        | lewat .env. Bentuknya kunci-nilai supaya menambah satu pengaturan
        | tidak perlu migrasi baru; nilainya disimpan sebagai JSON sehingga
        | daftar slide pun muat tanpa tabel tersendiri.
        */
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
