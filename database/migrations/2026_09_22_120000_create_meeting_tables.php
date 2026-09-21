<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progres rapat — meniru aplikasi TaskFlow (repo TODO_muzadidil): proyek,
 * tugas dengan sub-tugas bertingkat tanpa batas, dan progres yang dihitung
 * dari sub-tugas yang selesai.
 *
 * Sub-tugas disimpan di tabel yang sama dengan tugasnya, lewat `parent_id`.
 * Hanya tugas utama yang punya proyek, kategori, dan prioritas; sub-tugas
 * cukup teks, tenggat, dan link — sama seperti di TaskFlow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('meeting_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('meeting_projects')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('meeting_tasks')->cascadeOnDelete();
            $table->string('text', 500);
            $table->string('category', 20)->default('kerja');
            $table->string('priority', 10)->default('medium');
            $table->date('deadline')->nullable();
            $table->string('link', 500)->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            // Tugas selesai diarsipkan otomatis sehari kemudian, dan bisa
            // dikembalikan dari arsip.
            $table->boolean('archived')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['parent_id', 'archived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_tasks');
        Schema::dropIfExists('meeting_projects');
    }
};
