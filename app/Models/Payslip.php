<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Slip gaji satu karyawan untuk satu bulan.
 *
 * Aturan yang di aplikasi Slip Gaji aslinya hanya dijaga formulir, di sini
 * dijaga model, jadi berlaku dari mana pun slip disimpan:
 *
 *   - nomor slip SG/{tahun}/{bulan romawi}/{urut}, tidak pernah kembar
 *   - nama, NIK, dan jabatan disalin dari data karyawan saat slip dibuat
 *   - item bertanda "fix" selalu bernominal bawaannya
 *   - total pendapatan, potongan, dan gaji bersih dihitung ulang tiap simpan
 */
class Payslip extends Model
{
    protected $fillable = [
        'number',
        'employee_id',
        'period',
        'issued_on',
        'employee_name',
        'employee_nik',
        'employee_position',
        'basic_salary',
        'earnings',
        'deductions',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'issued_on' => 'date',
            'basic_salary' => 'integer',
            'earnings' => 'array',
            'deductions' => 'array',
            'total_earnings' => 'integer',
            'total_deductions' => 'integer',
            'net_pay' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Payslip $slip) {
            $slip->prepare();

            // Nomor memuat bulannya, jadi slip yang dipindah bulan diberi
            // nomor baru di bulan tujuannya.
            if ($slip->exists && $slip->isDirty('period')) {
                $slip->number = null;
            }

            $slip->number ??= static::nextNumber($slip->period);
        });

        static::saved(fn (Payslip $slip) => $slip->linkDeductions());
    }

    /**
     * Menandai potongan karyawan (gelas pecah, dan sebagainya) yang dipotong
     * slip ini, dan melepas yang barisnya sudah dihapus dari slip — potongan
     * yang dilepas kembali menunggu slip berikutnya, tidak hilang.
     */
    public function linkDeductions(): void
    {
        $ids = collect($this->deductions)->pluck('deduction_id')->filter()->map(fn ($id) => (int) $id)->all();

        EmployeeDeduction::query()
            ->where('payslip_id', $this->id)
            ->whereNotIn('id', $ids ?: [0])
            ->update(['payslip_id' => null]);

        if ($ids) {
            EmployeeDeduction::query()
                ->whereIn('id', $ids)
                ->where('employee_id', $this->employee_id)
                ->whereNull('payslip_id')
                ->update(['payslip_id' => $this->id]);
        }
    }

    /**
     * Seluruh aturan slip, tanpa menyimpan apa pun.
     *
     * Dipanggil saat menyimpan, dan juga oleh pratinjau di formulir — jadi
     * angka di pratinjau tidak mungkin berbeda dari yang tersimpan.
     */
    public function prepare(): static
    {
        $this->period = Carbon::parse($this->period ?? Carbon::today())->startOfMonth();
        $this->issued_on ??= Carbon::today();

        // Disalin saat slip dibuat atau karyawannya diganti. Mengubah data
        // karyawan belakangan tidak mengubah slip yang sudah ada.
        if ($this->employee_id && ($this->isDirty('employee_id') || blank($this->employee_name))) {
            $employee = Employee::find($this->employee_id);

            $this->employee_name = $employee?->name ?? $this->employee_name;
            $this->employee_nik = $employee?->nik;
            $this->employee_position = $employee?->position;
        }

        $this->earnings = static::normalize(PayComponent::EARNING, $this->earnings);
        $this->deductions = static::normalize(PayComponent::DEDUCTION, $this->deductions);

        $this->total_earnings = (int) $this->basic_salary + array_sum(array_column($this->earnings, 'amount'));
        $this->total_deductions = array_sum(array_column($this->deductions, 'amount'));
        $this->net_pay = $this->total_earnings - $this->total_deductions;

        return $this;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Baris tunjangan/potongan yang bersih: tanpa baris kosong, nominal
     * berupa angka bulat, dan item "fix" dikunci ke nominal bawaannya.
     *
     * @return array<int, array{label: string, amount: int, deduction_id?: int}>
     */
    public static function normalize(string $type, mixed $lines): array
    {
        $clean = [];

        foreach (array_values((array) $lines) as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            $amount = max(0, (int) preg_replace('/[^0-9]/', '', (string) ($line['amount'] ?? 0)));

            if ($label === '' && $amount === 0) {
                continue;
            }

            $component = PayComponent::match($type, $label);

            if ($component?->fixed) {
                $amount = $component->default_amount;
            }

            $row = ['label' => $label, 'amount' => $amount];

            // Baris yang datang dari potongan karyawan membawa nomornya,
            // supaya potongannya bisa ditandai sudah dipotong slip ini.
            if (filled($line['deduction_id'] ?? null)) {
                $row['deduction_id'] = (int) $line['deduction_id'];
            }

            $clean[] = $row;
        }

        return $clean;
    }

    /**
     * Nomor berikutnya untuk satu bulan: SG/2026/IX/001, SG/2026/IX/002, …
     *
     * Diambil dari nomor terbesar yang ada, bukan dari jumlah slip. Aplikasi
     * aslinya menghitung jumlah slip, sehingga menghapus satu slip membuat
     * slip berikutnya mendapat nomor yang sudah dipakai.
     */
    public static function nextNumber(Carbon $period): string
    {
        $prefix = 'SG/'.$period->year.'/'.static::roman($period->month).'/';

        $last = static::query()
            ->where('number', 'like', $prefix.'%')
            ->pluck('number')
            ->map(fn (string $number) => (int) Str::afterLast($number, '/'))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }

    public static function roman(int $month): string
    {
        return ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month - 1];
    }
}
