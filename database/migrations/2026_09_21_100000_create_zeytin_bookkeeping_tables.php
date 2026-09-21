<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pembukuan bulanan, sepadan dengan sheet-sheet di berkas Excel klien.
 *
 * Sengaja terpisah dari `sales` dan `expenses` yang sudah ada, bukan
 * dilebur ke dalamnya. Alasannya bukan kerapian, tapi asal-usul: `sales`
 * berisi transaksi per struk dari mesin kasir, sedangkan sheet Income berisi
 * satu baris rekap per hari per channel yang ditulis tangan. Meleburnya
 * berarti satu hari penjualan bisa terhitung dua kali — sekali dari struk,
 * sekali dari rekap — dan tidak ada cara membedakannya setelah tercampur.
 *
 * Tiap baris hasil impor membawa `import_key` yang diturunkan dari isinya,
 * dan `source` yang menandai siapa yang menulisnya. Keduanya yang membuat
 * mengimpor ulang berkas yang sama aman; lihat App\Support\Zeytin\Workbook.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | Sheet Income — satu baris per tanggal. Tanggalnya unik, jadi
        | menyimpan tanggal yang sama mengganti barisnya, bukan menumpuk.
        | Nilai channel disimpan apa adanya; Total Sales tidak ikut disimpan
        | karena ia turunan, dan turunan yang disimpan bisa menyimpang dari
        | sumbernya. Lihat App\Support\Zeytin\DailyLedger.
        */
        Schema::create('daily_incomes', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();

            foreach (config('zeytin.channels') as $channel) {
                $table->unsignedBigInteger($channel['key'])->default(0);
            }

            $table->string('note', 200)->nullable();
            $table->string('source', 8)->default('manual');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        /*
        | Sheet Expense — belanja harian, dibayar tunai dari titipan pemasok.
        | `total` ikut disimpan walau turunan, karena rumusnya milik berkas
        | Excel (`=((qty*price)+tax-disc)`) dan bisa saja berbeda dari yang
        | dihitung ulang kalau kliennya mengubah rumus di kemudian hari.
        */
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('vendor', 160)->nullable();
            $table->string('item', 200);
            $table->integer('qty')->default(1);
            $table->string('unit', 40)->nullable();
            $table->bigInteger('price')->default(0);
            $table->bigInteger('disc')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('source', 8)->default('manual');
            $table->string('import_key', 120)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['date', 'source']);
        });

        /*
        | Sheet Supplier Transfer Payment — pembayaran ke pemasok lewat
        | rekening. Di berkas aslinya angka ini tidak pernah ikut hitungan
        | laba; di sini ikut. Lihat docs/DISKUSI.md di repo Zeytin.
        |
        | `status` menandai siapa yang menalangi ("PT KEBAP PAID",
        | "ASLAN PAID"). Disimpan sebagai teks bebas, bukan enum: di berkas
        | aslinya lebih dari separuh barisnya kosong, dan enum akan menolak
        | ejaan yang belum pernah terlihat justru saat impor berjalan.
        */
        Schema::create('supplier_transfers', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('vendor', 160)->nullable();
            $table->string('item', 200);
            $table->string('unit', 40)->nullable();
            $table->integer('qty')->default(1);
            $table->bigInteger('price')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('method', 60)->nullable();
            $table->string('status', 60)->nullable();
            $table->string('source', 8)->default('manual');
            $table->string('import_key', 120)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['date', 'source']);
        });

        /*
        | Sheet Payroll — gaji per orang per bulan. Bulannya disimpan sebagai
        | tanggal 1, supaya bisa diurutkan dan disaring seperti tanggal biasa
        | tanpa mengurai teks "September 2026".
        */
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->date('month');
            $table->string('section', 40)->nullable();
            $table->string('name', 120);
            $table->bigInteger('basic')->default(0);
            $table->bigInteger('bpjs')->default(0);
            $table->bigInteger('grand_total')->default(0);
            $table->string('source', 8)->default('manual');
            $table->string('import_key', 120)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['month', 'source']);
        });

        /*
        | Sheet Outstanding INV — tagihan yang sudah diterima tapi belum
        | tentu dibayar. Yang belum beres mengurangi saldo global.
        |
        | `status` teks bebas dengan alasan yang sama seperti di atas; yang
        | menentukan "sudah beres" adalah DailyLedger::isSettled(), yang
        | mengenali "paid", "lunas", dan "settled" apa pun ejaannya.
        */
        Schema::create('outstanding_bills', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->string('vendor', 160)->nullable();
            $table->string('item', 200);
            $table->string('unit', 40)->nullable();
            $table->integer('qty')->default(1);
            $table->bigInteger('price')->default(0);
            $table->bigInteger('disc')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('status', 60)->nullable();
            $table->string('source', 8)->default('manual');
            $table->string('import_key', 120)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['date', 'source']);
        });

        /*
        | Barang yang biasa dibelanjakan, beserta harga terakhirnya.
        |
        | Bukan tabel `products` yang sudah ada: `products` berisi menu yang
        | DIJUAL ke tamu, ini berisi bahan yang DIBELI dari pemasok. Nama
        | yang sama ("Ayam") di dua tabel itu dua barang yang berbeda, dan
        | menyatukannya berarti harga jual dan harga beli saling menimpa.
        |
        | Harga di sini cuma tawaran awal, bukan aturan: begitu dipilih di
        | formulir belanja, angkanya masih bisa ditimpa. Harga pemasok
        | berubah terus, dan formulir yang memaksakan harga lama akan membuat
        | catatannya salah dengan rapi.
        */
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('unit', 40)->nullable();
            $table->bigInteger('price')->default(0);
            $table->string('vendor', 160)->nullable();
            $table->string('note', 200)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('name');
        });

        /*
        | Cara pembayaran ke pemasok. Di berkas Excel isinya diketik ulang
        | tiap baris ("Transfer", "COD", "Cash"), jadi ejaannya gampang
        | berbeda-beda dan pengelompokan laporannya ikut berantakan. Di sini
        | didaftar sekali lalu dipakai sebagai saran.
        |
        | Terpisah dari enum App\Enums\PaymentMethod yang dipakai kasir:
        | enum itu menentukan ke mana uang bergerak di neraca dan hanya boleh
        | berisi tiga nilai yang dikenal Ledger. Yang ini sekadar label cara
        | bayar pemasok, dan pemilik boleh menambahnya sendiri.
        */
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('note', 200)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        /*
        | Sheet Supplier Database menyimpan rekening dan cara bayar tiap
        | pemasok — keterangan yang belum ada di tabel `suppliers`.
        |
        | Ditambahkan sebagai kolom baru yang boleh kosong, bukan tabel
        | pemasok kedua: dua daftar pemasok yang harus dijaga tetap sama
        | adalah janji yang selalu diingkari. Baris yang sudah ada tidak
        | berubah sedikit pun — kolom barunya null, dan `source` mereka
        | 'manual', jadi pengimpor tidak akan pernah menyentuhnya.
        */
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('bank', 60)->nullable()->after('supplies');
            $table->string('bank_account', 60)->nullable()->after('bank');
            $table->string('account_name', 120)->nullable()->after('bank_account');
            $table->string('payment_method', 60)->nullable()->after('account_name');
            $table->bigInteger('last_price')->default(0)->after('payment_method');
            $table->string('source', 8)->default('manual')->after('last_price');
            $table->string('import_key', 120)->nullable()->unique()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['import_key']);
            $table->dropColumn([
                'bank', 'bank_account', 'account_name',
                'payment_method', 'last_price', 'source', 'import_key',
            ]);
        });

        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('outstanding_bills');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('supplier_transfers');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('daily_incomes');
    }
};
