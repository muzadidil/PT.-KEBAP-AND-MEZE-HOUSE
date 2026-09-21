<?php

namespace App\Models;

use App\Models\Concerns\BookkeepingRecord;
use App\Support\Zeytin\Channels;
use App\Support\Zeytin\DailyLedger;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris rekap pemasukan per hari, sepadan dengan sheet Income.
 *
 * Tanggalnya unik: menyimpan tanggal yang sama mengganti barisnya, bukan
 * menumpuk. Itu yang membuat mengimpor ulang berkas yang sama tidak
 * menggandakan pemasukan sebulan.
 */
class DailyIncome extends Model
{
    use BookkeepingRecord;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            ...array_fill_keys(Channels::keys(), 'integer'),
        ];
    }

    /** @return array<string, int> */
    public function channelAmounts(): array
    {
        $amounts = [];

        foreach (Channels::keys() as $key) {
            $amounts[$key] = (int) $this->{$key};
        }

        return $amounts;
    }

    /**
     * Total Sales hari ini — turunan, jadi dihitung, tidak disimpan.
     * Petty cash tidak ikut; lihat DailyLedger.
     */
    public function totalSales(): int
    {
        return DailyLedger::dayFigures($this->channelAmounts())['total_sales'];
    }
}
