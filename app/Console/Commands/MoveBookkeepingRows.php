<?php

namespace App\Console\Commands;

use App\Models\DailyIncome;
use App\Models\OutstandingBill;
use App\Models\Payroll;
use App\Models\Purchase;
use App\Models\SupplierTransfer;
use App\Support\Money;
use App\Support\Zeytin\RecordSource;
use App\Support\Zeytin\Workbook\Cells;
use App\Support\Zeytin\Workbook\SheetSpec;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Memindahkan baris pembukuan yang salah tanggal ke tanggal yang benar.
 *
 * Kejadiannya nyata di berkas Agustus 2026: satu sel belanja diketik
 * "18/8/2028", dan bulan gaji dipilih Agustus 2025. Angkanya benar, hanya
 * jatuh di periode yang salah — sehingga belanja dan gaji hilang dari
 * laporan Agustus 2026.
 *
 * Nomor impor (`import_key`) ikut dihitung ulang dengan rumus pengimpor
 * yang sama, jadi hasilnya persis seperti mengimpor berkas yang selnya
 * sudah dibetulkan: mengimpor ulang berkas yang benar menimpa baris yang
 * sama, bukan menggandakannya.
 *
 *   php artisan zeytin:pindah belanja 2028-08-18 2026-08-18
 *   php artisan zeytin:pindah gaji 2025-08 2026-08
 */
class MoveBookkeepingRows extends Command
{
    protected $signature = 'zeytin:pindah
        {jenis : pemasukan | belanja | transfer | tagihan | gaji}
        {dari : tanggal asal (2028-08-18), atau bulan untuk gaji (2025-08)}
        {ke : tanggal atau bulan tujuan}
        {--paksa : jalankan tanpa bertanya}';

    protected $description = 'Memindahkan baris pembukuan yang salah tanggal, beserta nomor impornya';

    /** @var array<string, class-string<Model>> */
    protected const TYPES = [
        'pemasukan' => DailyIncome::class,
        'belanja' => Purchase::class,
        'transfer' => SupplierTransfer::class,
        'tagihan' => OutstandingBill::class,
        'gaji' => Payroll::class,
    ];

    public function handle(): int
    {
        $model = static::TYPES[$this->argument('jenis')] ?? null;

        if (! $model) {
            $this->error('Jenis tidak dikenal. Pilih: '.implode(', ', array_keys(static::TYPES)).'.');

            return self::FAILURE;
        }

        $monthly = $model === Payroll::class;
        $field = $monthly ? 'month' : 'date';

        try {
            $from = $this->parse($this->argument('dari'), $monthly);
            $to = $this->parse($this->argument('ke'), $monthly);
        } catch (\Throwable) {
            $this->error($monthly ? 'Bulan ditulis 2026-08.' : 'Tanggal ditulis 2026-08-18.');

            return self::FAILURE;
        }

        $rows = $model::query()->whereDate($field, $from)->orderBy('id')->get();

        if ($rows->isEmpty()) {
            $this->warn('Tidak ada baris '.$this->argument('jenis').' di '.$from->toDateString().'. Tidak ada yang diubah.');

            return self::SUCCESS;
        }

        // Pemasukan harian satu baris per tanggal: memindahkan ke tanggal
        // yang sudah terisi berarti satu hari punya dua baris.
        if ($model === DailyIncome::class && $model::query()->whereDate('date', $to)->exists()) {
            $this->error('Tanggal '.$to->toDateString().' sudah punya pemasukan harian. Tidak ada yang diubah.');

            return self::FAILURE;
        }

        $amountField = $monthly ? 'grand_total' : ($model === DailyIncome::class ? null : 'total');

        $this->table(['#', 'Isi', 'Jumlah'], $rows->map(fn (Model $row) => [
            $row->getKey(),
            $row->name ?? $row->item ?? $row->{$field}->toDateString(),
            $amountField ? Money::format((int) $row->{$amountField}) : '—',
        ]));

        $this->line(sprintf(
            '%d baris %s: %s → %s',
            $rows->count(),
            $this->argument('jenis'),
            $from->toDateString(),
            $to->toDateString(),
        ));

        if (! $this->option('paksa') && ! $this->confirm('Pindahkan?', true)) {
            return self::SUCCESS;
        }

        $spec = collect(SheetSpec::all())->firstWhere('model', $model);

        DB::transaction(function () use ($rows, $field, $to, $spec) {
            $seen = [];

            foreach ($rows as $row) {
                $row->{$field} = $to->copy();

                if ($row->source === RecordSource::IMPORT && $spec?->keyFrom) {
                    $row->import_key = $this->freshKey($row, $spec->keyFrom, $seen);
                }

                $row->save();
            }
        });

        $this->info('Selesai. '.$rows->count().' baris dipindahkan.');

        return self::SUCCESS;
    }

    protected function parse(string $value, bool $monthly): Carbon
    {
        return $monthly
            ? Carbon::createFromFormat('!Y-m', substr($value, 0, 7))
            : Carbon::createFromFormat('!Y-m-d', $value);
    }

    /**
     * Nomor impor baru, dengan rumus pengimpor. Kalau nomornya sudah
     * dipakai baris lain di tanggal tujuan — dua belanja yang isinya sama
     * persis — nomor urut di belakangnya dinaikkan, sama seperti pengimpor
     * memperlakukan baris kembar di satu berkas.
     *
     * @param  array<int, string>  $keyFrom
     * @param  array<string, int>  $seen
     */
    protected function freshKey(Model $row, array $keyFrom, array &$seen): string
    {
        $values = [];

        foreach ($keyFrom as $column) {
            $values[$column] = $row->{$column};
        }

        do {
            $key = Cells::stableKey($keyFrom, $values, $seen);
        } while ($row::query()->where('import_key', $key)->whereKeyNot($row->getKey())->exists());

        return $key;
    }
}
