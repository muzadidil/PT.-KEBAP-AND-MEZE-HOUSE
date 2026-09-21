<?php

namespace App\Models\Concerns;

use App\Models\EmployeeDeduction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Baris pengeluaran yang bisa membawa satu potongan gaji karyawan.
 *
 * Dipakai Belanja Tunai dan Transfer Pemasok. Tanggal dan alasan potongan
 * mengikuti barisnya; lihat EmployeeDeduction::booted().
 */
trait HasEmployeeDeduction
{
    public static function bootHasEmployeeDeduction(): void
    {
        // Tanggal atau catatan pengeluarannya diubah: potongannya ikut.
        static::saved(function (Model $row) {
            if ($row->wasChanged(['date', 'note', 'item'])) {
                $row->deduction?->save();
            }
        });

        // Pengeluaran dihapus: potongan yang belum masuk slip ikut hilang.
        // Yang sudah masuk slip tetap ada, karena slipnya sudah memotongnya.
        // Dibaca ulang dari basis data, bukan relasi yang mungkin sudah dimuat
        // sebelum slipnya dibuat: salinan lama di memori belum tahu potongan
        // itu sudah dipotong.
        static::deleting(function (Model $row) {
            $row->deduction()->whereNull('payslip_id')->first()?->delete();
        });
    }

    public function deduction(): MorphOne
    {
        return $this->morphOne(EmployeeDeduction::class, 'source');
    }
}
