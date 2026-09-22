<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis isian per catatan Daily Report, dan master pilihannya.
 *
 * Tidak semua bagian laporan tim berupa teks bebas:
 *
 *   choice  bagian yang isinya satu pilihan dari master, mis. Operation:
 *           Good / Need Attention / Problem
 *   number  angka biasa, bukan Rupiah — Total Reviews, New Reviews, …
 *   rating  angka 0–5 yang ditulis dengan bintang — Rating Google
 *   status  poin yang punya status dari master, mis. Task/Work Update:
 *           Pending / Process / Finish
 *   text    catatan biasa dengan nominal Rupiah opsional, seperti sebelumnya
 *
 * Master pilihannya (kondisi dan status) disimpan di tabel sendiri supaya
 * bisa ditambah, diganti nama, atau dihapus dari halaman Daily Report tanpa
 * mengubah kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_report_options', function (Blueprint $table) {
            $table->id();
            // condition = pilihan untuk bagian jenis "choice"; status = untuk poin jenis "status"
            $table->string('group', 20);
            $table->string('label', 60);
            $table->string('icon', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group', 'sort_order']);
        });

        $now = now();

        DB::table('daily_report_options')->insert([
            ['group' => 'condition', 'label' => 'Good', 'icon' => '🟢', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'condition', 'label' => 'Need Attention', 'icon' => '🟡', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'condition', 'label' => 'Problem', 'icon' => '🔴', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'status', 'label' => 'Pending', 'icon' => '⏳', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'status', 'label' => 'Process', 'icon' => '🔄', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'status', 'label' => 'Finish', 'icon' => '✅', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('daily_notes', function (Blueprint $table) {
            $table->string('kind', 10)->default('text')->after('text');
            $table->string('icon', 16)->nullable()->after('kind');
            // Angka biasa atau rating; bukan uang, jadi terpisah dari `nominal`.
            $table->decimal('value', 12, 2)->nullable()->after('nominal');
            $table->foreignId('option_id')->nullable()->after('value')
                ->constrained('daily_report_options')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('option_id');
            $table->dropColumn(['kind', 'icon', 'value']);
        });

        Schema::dropIfExists('daily_report_options');
    }
};
