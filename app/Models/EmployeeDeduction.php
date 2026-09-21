<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Potongan gaji karyawan yang lahir dari satu pengeluaran — gelas pecah,
 * barang hilang.
 *
 * Dicatat Admin bersama pengeluarannya, lalu otomatis masuk slip gaji
 * karyawan itu. Begitu slip menyimpannya, `payslip_id` terisi dan potongan
 * yang sama tidak ditawarkan lagi ke slip mana pun.
 */
class EmployeeDeduction extends Model
{
    protected $fillable = [
        'employee_id',
        'date',
        'amount',
        'reason',
        'payslip_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Tanggal dan alasannya ikut baris pengeluaran asalnya, jadi tidak
        // perlu diketik dua kali di formulir yang sama.
        static::saving(function (self $deduction) {
            if ($source = $deduction->source) {
                $deduction->date = $source->date;
                $deduction->reason = $source->note ?: $source->item;
            }

            $deduction->date ??= Carbon::today();
            $deduction->user_id ??= Auth::id();
        });

        // Yang sudah dipotong slip tidak bisa dihapus dari halaman lain:
        // slipnya sudah diserahkan dengan potongan itu di dalamnya. Untuk
        // membatalkannya, ubah atau hapus slipnya.
        // Diperiksa di basis data, bukan pada salinan di memori yang bisa
        // dimuat sebelum slipnya dibuat.
        static::deleting(fn (self $deduction) => static::query()->whereKey($deduction->getKey())->whereNotNull('payslip_id')->exists()
            ? false
            : null);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    /**
     * Potongan yang menunggu dipotong di slip satu karyawan: sampai akhir
     * bulan slipnya, termasuk sisa bulan-bulan sebelumnya yang belum sempat
     * masuk slip. Potongan milik slip ini sendiri ikut, supaya membuka ulang
     * slip lama tetap menampilkannya.
     */
    public function scopePendingFor(Builder $query, int $employeeId, Carbon $period, ?int $payslipId = null): Builder
    {
        return $query
            ->where('employee_id', $employeeId)
            ->whereDate('date', '<=', $period->copy()->endOfMonth())
            ->where(fn (Builder $q) => $q->whereNull('payslip_id')->when($payslipId, fn ($q) => $q->orWhere('payslip_id', $payslipId)))
            ->orderBy('date')
            ->orderBy('id');
    }

    /** Keterangan di slip: "Gelas pecah (12/09)". */
    public function label(): string
    {
        return $this->reason.' ('.$this->date->format('d/m').')';
    }
}
