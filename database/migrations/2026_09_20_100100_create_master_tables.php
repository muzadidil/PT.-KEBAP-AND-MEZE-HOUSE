<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        | Pemilik beserta porsi tanggungannya. Aslan 60% / Leo 40% disimpan
        | sebagai data, bukan angka mati di kode, supaya perubahan kesepakatan
        | cukup diubah lewat halaman Owners.
        */
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedTinyInteger('share_percent');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Nama menu disimpan dua bahasa. Yang Indonesia boleh kosong; kalau
        // kosong, yang Inggris yang dipakai (lihat Product::displayName).
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 80);
            $table->string('name_id', 80)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 30)->nullable()->unique();
            $table->string('name_en', 120);
            $table->string('name_id', 120)->nullable();
            // Rupiah bulat. Tidak ada sen di kasir ini, dan bilangan bulat
            // menutup seluruh kemungkinan galat pembulatan di laporan.
            $table->unsignedBigInteger('price');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'active']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('supplies', 160)->nullable();
            $table->text('address')->nullable();
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('owners');
    }
};
