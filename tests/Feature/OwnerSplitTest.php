<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\Owner;
use App\Support\Ledger;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pembagian pengeluaran yang ditalangi pemilik.
 *
 * Yang diuji di sini bukan tampilannya, melainkan sifat yang membuat
 * pembagiannya adil: jumlah tanggungan selalu sama dengan total yang
 * ditalangi, dan selisih seluruh pemilik selalu berjumlah nol — kalau satu
 * pihak berhak menerima, pasti ada pihak lain yang harus menyetor sebesar
 * itu juga, tanpa rupiah yang hilang di pembulatan.
 */
class OwnerSplitTest extends TestCase
{
    protected function advance(Owner $owner, int $amount, string $date = '2026-09-10'): Expense
    {
        return Expense::create([
            'spent_on' => $date,
            'category' => ExpenseCategory::Operational,
            'method' => PaymentMethod::Cash,
            'description' => 'Belanja harian',
            'amount' => $amount,
            'paid_by_owner_id' => $owner->id,
        ]);
    }

    protected function september(): array
    {
        return [Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30')];
    }

    public function test_porsi_dihitung_sesuai_kesepakatan_60_40(): void
    {
        [$aslan, $leo] = $this->owners();
        $this->advance($aslan, 10_000_000);

        [$from, $to] = $this->september();
        $result = Ledger::ownerSettlement($from, $to);

        $this->assertSame(10_000_000, $result['total']);

        $rows = collect($result['rows'])->keyBy('name');

        $this->assertSame(6_000_000, $rows['Aslan']['share']);
        $this->assertSame(4_000_000, $rows['Leo']['share']);

        // Aslan membayar semuanya padahal porsinya 60%, jadi Leo berutang
        // 40% kepadanya.
        $this->assertSame(4_000_000, $rows['Aslan']['balance']);
        $this->assertSame(-4_000_000, $rows['Leo']['balance']);
    }

    public function test_selisih_seluruh_pemilik_selalu_berjumlah_nol(): void
    {
        [$aslan, $leo] = $this->owners();

        // Angka yang sengaja tidak bulat terhadap 60/40, termasuk yang
        // menyisakan rupiah ganjil saat dibagi.
        $this->advance($aslan, 1_234_567);
        $this->advance($leo, 765_433);
        $this->advance($aslan, 1);
        $this->advance($leo, 7);

        [$from, $to] = $this->september();
        $result = Ledger::ownerSettlement($from, $to);

        $this->assertSame(0, array_sum(array_column($result['rows'], 'balance')));
        $this->assertSame($result['total'], array_sum(array_column($result['rows'], 'share')));
        $this->assertSame($result['total'], array_sum(array_column($result['rows'], 'paid')));
    }

    public function test_pembagian_tetap_seimbang_untuk_kombinasi_jumlah_apa_pun(): void
    {
        [$aslan, $leo] = $this->owners();

        // Bukan sekadar satu skenario yang kebetulan cocok: dicoba pada
        // banyak nilai sekaligus, termasuk yang menyisakan rupiah ganjil.
        foreach ([1, 3, 7, 99, 101, 999, 1_000_001, 3_333_333] as $amount) {
            Expense::query()->delete();

            $this->advance($aslan, $amount);
            $this->advance($leo, $amount + 1);

            [$from, $to] = $this->september();
            $result = Ledger::ownerSettlement($from, $to);

            $this->assertSame(
                $result['total'],
                array_sum(array_column($result['rows'], 'share')),
                "Tanggungan tidak berjumlah kembali ke total pada nilai {$amount}",
            );

            $this->assertSame(
                0,
                array_sum(array_column($result['rows'], 'balance')),
                "Selisih tidak berjumlah nol pada nilai {$amount}",
            );
        }
    }

    public function test_pengeluaran_tanpa_penalang_tidak_ikut_dibagi(): void
    {
        [$aslan] = $this->owners();
        $this->advance($aslan, 1_000_000);

        // Dibayar usaha sendiri, bukan ditalangi siapa pun.
        Expense::create([
            'spent_on' => '2026-09-12',
            'category' => ExpenseCategory::Operational,
            'method' => PaymentMethod::Cash,
            'description' => 'Gas',
            'amount' => 500_000,
        ]);

        [$from, $to] = $this->september();

        $this->assertSame(1_000_000, Ledger::ownerSettlement($from, $to)['total']);
    }

    public function test_pengeluaran_di_luar_bulan_tidak_ikut_dibagi(): void
    {
        [$aslan] = $this->owners();
        $this->advance($aslan, 1_000_000, '2026-08-31');
        $this->advance($aslan, 2_000_000, '2026-09-01');
        $this->advance($aslan, 4_000_000, '2026-10-01');

        [$from, $to] = $this->september();

        $this->assertSame(2_000_000, Ledger::ownerSettlement($from, $to)['total']);
    }

    public function test_porsi_yang_diubah_langsung_berlaku(): void
    {
        [$aslan, $leo] = $this->owners();
        $this->advance($aslan, 1_000_000);

        // Kesepakatan berubah jadi 50/50; laporan harus langsung mengikuti
        // tanpa satu baris kode pun diubah.
        $aslan->update(['share_percent' => 50]);
        $leo->update(['share_percent' => 50]);

        [$from, $to] = $this->september();
        $rows = collect(Ledger::ownerSettlement($from, $to)['rows'])->keyBy('name');

        $this->assertSame(500_000, $rows['Aslan']['share']);
        $this->assertSame(500_000, $rows['Leo']['share']);
    }

    public function test_pemilik_nonaktif_tidak_ikut_menanggung(): void
    {
        [$aslan, $leo] = $this->owners();
        $this->advance($aslan, 1_000_000);

        $leo->update(['active' => false]);

        [$from, $to] = $this->september();
        $result = Ledger::ownerSettlement($from, $to);

        $this->assertCount(1, $result['rows']);
        $this->assertSame(1_000_000, $result['rows'][0]['share']);
        $this->assertSame(0, $result['rows'][0]['balance']);
    }

    public function test_pengeluaran_talangan_selalu_berstatus_lunas(): void
    {
        [$aslan] = $this->owners();

        // Dicoba dipaksa "belum dibayar"; model harus menolaknya, karena
        // uangnya sudah keluar dari kantong pemilik.
        $expense = Expense::create([
            'spent_on' => '2026-09-10',
            'category' => ExpenseCategory::Operational,
            'method' => PaymentMethod::Cash,
            'description' => 'Belanja',
            'amount' => 250_000,
            'paid_by_owner_id' => $aslan->id,
            'is_paid' => false,
        ]);

        $this->assertTrue($expense->fresh()->is_paid);
    }
}
