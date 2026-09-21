<?php

namespace Database\Seeders;

use App\Models\PaymentMethodOption;
use App\Models\PurchaseItem;
use Illuminate\Database\Seeder;

/**
 * Isi awal untuk pembukuan bulanan: cara bayar dan beberapa barang belanja.
 *
 * Hanya data induk, tidak ada satu pun angka transaksi. Pemasukan, belanja,
 * transfer, dan gaji semuanya datang dari berkas Excel klien atau diketik
 * sendiri — baris contoh di sini hanya akan tercampur dengan catatan
 * sungguhan lalu ikut terhitung di laporan.
 *
 * Gunanya sekadar supaya kotak saran di formulir belanja tidak kosong di
 * hari pertama. Semuanya updateOrCreate, jadi aman dijalankan ulang, dan
 * apa pun yang sudah diubah pemilik tidak dikembalikan ke nilai awal.
 */
class ZeytinSeeder extends Seeder
{
    public function run(): void
    {
        // Ejaan yang dipakai di berkas Excel klien, supaya baris hasil impor
        // dan baris ketikan memakai istilah yang sama.
        foreach (['Transfer', 'Cash', 'COD'] as $method) {
            PaymentMethodOption::updateOrCreate(['name' => $method], ['active' => true]);
        }

        // Barang yang paling sering muncul di sheet Expense bulan Agustus.
        $items = [
            ['Aqua Galon', 'galon', 20_000],
            ['Es Batu', 'balok', 12_000],
            ['Still Water', 'dus', 26_000],
            ['Gas Elpiji', 'tabung', 25_000],
        ];

        foreach ($items as [$name, $unit, $price]) {
            PurchaseItem::updateOrCreate(
                ['name' => $name],
                ['unit' => $unit, 'price' => $price, 'active' => true],
            );
        }
    }
}
