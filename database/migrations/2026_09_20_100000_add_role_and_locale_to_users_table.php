<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('cashier')->after('email');
            // Pilihan bahasa menempel di pengguna, bukan sesi, supaya kasir
            // yang berbahasa Indonesia tidak perlu menggantinya tiap masuk.
            $table->string('locale', 5)->default('en')->after('role');
            $table->boolean('active')->default(true)->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'locale', 'active']);
        });
    }
};
