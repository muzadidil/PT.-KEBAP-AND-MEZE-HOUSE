<?php

namespace Tests\Feature;

use App\Enums\CapitalDirection;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\SaleSource;
use App\Models\CapitalEntry;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\Sale;
use App\Support\Ledger;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Neraca.
 *
 * Yang diuji bukan angka satu skenario, melainkan bahwa
 * Aset = Kewajiban + Ekuitas berlaku untuk kombinasi transaksi apa pun.
 * Kalau ada satu kombinasi saja yang membuatnya tidak seimbang, laporan
 * neraca tidak bisa dipercaya sama sekali.
 *
 * Dua aturan yang menjaganya, keduanya diuji tersendiri di bawah:
 *  - Pengeluaran yang belum dibayar tidak mengurangi uang, tapi jadi utang.
 *  - Pengeluaran yang ditalangi pemilik tidak mengurangi uang usaha, tapi
 *    menambah modal pemilik itu.
 */
class BalanceSheetTest extends TestCase
{
    protected function sell(string $date, string $channel, int $amount): Sale
    {
        return Sale::create([
            'sold_on' => $date,
            'channel' => $channel,
            'source' => SaleSource::Quick,
            'subtotal' => $amount,
            'total' => $amount,
            'paid' => $amount,
        ]);
    }

    protected function spend(string $date, int $amount, array $attributes = []): Expense
    {
        return Expense::create([
            'spent_on' => $date,
            'category' => ExpenseCategory::Operational,
            'method' => PaymentMethod::Cash,
            'description' => 'Pengeluaran',
            'amount' => $amount,
            ...$attributes,
        ]);
    }

    protected function capital(Owner $owner, string $date, int $amount, CapitalDirection $direction, PaymentMethod $method = PaymentMethod::Cash): CapitalEntry
    {
        return CapitalEntry::create([
            'owner_id' => $owner->id,
            'entry_on' => $date,
            'direction' => $direction,
            'method' => $method,
            'amount' => $amount,
        ]);
    }

    protected function today(): Carbon
    {
        return Carbon::parse('2026-09-30')->endOfDay();
    }

    public function test_neraca_kosong_seimbang_di_angka_nol(): void
    {
        $sheet = Ledger::balanceSheet($this->today());

        $this->assertSame(0, $sheet['assets']);
        $this->assertSame(0, $sheet['liabilities']);
        $this->assertSame(0, $sheet['equity']);
        $this->assertSame(0, $sheet['difference']);
    }

    public function test_penjualan_tunai_masuk_kas_dan_nontunai_masuk_bank(): void
    {
        $this->sell('2026-09-10', 'cash', 1_000_000);
        $this->sell('2026-09-10', 'cashless', 300_000);
        $this->sell('2026-09-10', 'grab', 200_000);

        $sheet = Ledger::balanceSheet($this->today());

        $this->assertSame(1_000_000, $sheet['cash_on_hand']);
        $this->assertSame(500_000, $sheet['bank']);
        $this->assertSame(1_500_000, $sheet['assets']);
        $this->assertSame(1_500_000, $sheet['retained_earnings']);
        $this->assertSame(0, $sheet['difference']);
    }

    public function test_tagihan_yang_belum_dibayar_jadi_utang_dan_tidak_mengurangi_kas(): void
    {
        $this->sell('2026-09-10', 'cash', 1_000_000);
        $this->spend('2026-09-11', 400_000, [
            'category' => ExpenseCategory::Supplier,
            'is_paid' => false,
        ]);

        $sheet = Ledger::balanceSheet($this->today());

        // Uangnya belum keluar.
        $this->assertSame(1_000_000, $sheet['cash_on_hand']);
        // Tapi kewajibannya sudah timbul, dan labanya sudah berkurang.
        $this->assertSame(400_000, $sheet['supplier_payable']);
        $this->assertSame(600_000, $sheet['retained_earnings']);
        $this->assertSame(0, $sheet['difference']);
    }

    public function test_utang_pajak_muncul_terpisah_dari_utang_pemasok(): void
    {
        $this->sell('2026-09-10', 'cash', 2_000_000);
        $this->spend('2026-09-11', 150_000, ['category' => ExpenseCategory::Tax, 'is_paid' => false]);
        $this->spend('2026-09-11', 250_000, ['category' => ExpenseCategory::Supplier, 'is_paid' => false]);
        $this->spend('2026-09-11', 50_000, ['category' => ExpenseCategory::Other, 'is_paid' => false]);

        $sheet = Ledger::balanceSheet($this->today());

        $this->assertSame(150_000, $sheet['tax_payable']);
        $this->assertSame(250_000, $sheet['supplier_payable']);
        $this->assertSame(50_000, $sheet['other_payable']);
        $this->assertSame(450_000, $sheet['liabilities']);
        $this->assertSame(0, $sheet['difference']);
    }

    public function test_talangan_pemilik_tidak_mengurangi_kas_usaha_tapi_menambah_modalnya(): void
    {
        [$aslan] = $this->owners();

        $this->sell('2026-09-10', 'cash', 1_000_000);
        $this->spend('2026-09-11', 300_000, ['paid_by_owner_id' => $aslan->id]);

        $sheet = Ledger::balanceSheet($this->today());

        // Uangnya dari kantong Aslan, bukan dari laci kasir.
        $this->assertSame(1_000_000, $sheet['cash_on_hand']);
        $this->assertSame(300_000, collect($sheet['owners'])->firstWhere('name', 'Aslan')['capital']);
        // Tetap beban, jadi laba berkurang.
        $this->assertSame(700_000, $sheet['retained_earnings']);
        $this->assertSame(0, $sheet['difference']);
    }

    public function test_setoran_dan_penarikan_modal_menggerakkan_uang_tanpa_menyentuh_laba(): void
    {
        [$aslan] = $this->owners();

        $this->capital($aslan, '2026-09-01', 5_000_000, CapitalDirection::In);
        $this->capital($aslan, '2026-09-20', 1_000_000, CapitalDirection::Out);

        $sheet = Ledger::balanceSheet($this->today());

        $this->assertSame(4_000_000, $sheet['cash_on_hand']);
        $this->assertSame(4_000_000, $sheet['owner_capital']);
        // Modal bukan pendapatan dan prive bukan beban.
        $this->assertSame(0, $sheet['retained_earnings']);
        $this->assertSame(0, $sheet['difference']);
    }

    public function test_setoran_lewat_transfer_masuk_bank_bukan_kas(): void
    {
        [$aslan] = $this->owners();

        $this->capital($aslan, '2026-09-01', 5_000_000, CapitalDirection::In, PaymentMethod::Transfer);

        $sheet = Ledger::balanceSheet($this->today());

        $this->assertSame(0, $sheet['cash_on_hand']);
        $this->assertSame(5_000_000, $sheet['bank']);
        $this->assertSame(0, $sheet['difference']);
    }

    public function test_transaksi_setelah_tanggal_neraca_tidak_ikut_terhitung(): void
    {
        $this->sell('2026-09-30', 'cash', 100_000);
        $this->sell('2026-10-01', 'cash', 999_000);

        $sheet = Ledger::balanceSheet($this->today());

        $this->assertSame(100_000, $sheet['cash_on_hand']);
        $this->assertSame(0, $sheet['difference']);
    }

    /**
     * Pembuktian utamanya: banyak kombinasi acak sekaligus, bukan satu
     * skenario yang dipilih supaya cocok.
     */
    public function test_neraca_selalu_seimbang_untuk_kombinasi_transaksi_apa_pun(): void
    {
        [$aslan, $leo] = $this->owners();

        mt_srand(20260920);

        foreach (range(1, 40) as $round) {
            Sale::query()->delete();
            Expense::query()->delete();
            CapitalEntry::query()->delete();

            foreach (range(1, mt_rand(1, 6)) as $ignored) {
                $this->sell(
                    '2026-09-'.str_pad((string) mt_rand(1, 30), 2, '0', STR_PAD_LEFT),
                    ['cash', 'cashless', 'grab'][mt_rand(0, 2)],
                    mt_rand(1, 5_000_000),
                );
            }

            foreach (range(1, mt_rand(1, 6)) as $ignored) {
                $owner = [null, $aslan->id, $leo->id][mt_rand(0, 2)];

                $this->spend(
                    '2026-09-'.str_pad((string) mt_rand(1, 30), 2, '0', STR_PAD_LEFT),
                    mt_rand(1, 3_000_000),
                    [
                        'category' => ExpenseCategory::cases()[mt_rand(0, count(ExpenseCategory::cases()) - 1)],
                        'method' => PaymentMethod::cases()[mt_rand(0, 2)],
                        'paid_by_owner_id' => $owner,
                        'is_paid' => (bool) mt_rand(0, 1),
                    ],
                );
            }

            foreach (range(1, mt_rand(0, 3)) as $ignored) {
                $this->capital(
                    mt_rand(0, 1) ? $aslan : $leo,
                    '2026-09-'.str_pad((string) mt_rand(1, 30), 2, '0', STR_PAD_LEFT),
                    mt_rand(1, 2_000_000),
                    mt_rand(0, 1) ? CapitalDirection::In : CapitalDirection::Out,
                    PaymentMethod::cases()[mt_rand(0, 2)],
                );
            }

            $sheet = Ledger::balanceSheet($this->today());

            $this->assertSame(
                0,
                $sheet['difference'],
                "Neraca tidak seimbang di putaran {$round}: aset {$sheet['assets']}, "
                ."kewajiban {$sheet['liabilities']}, ekuitas {$sheet['equity']}",
            );

            $this->assertSame(
                $sheet['assets'],
                $sheet['liabilities'] + $sheet['equity'],
                "Aset tidak sama dengan kewajiban + ekuitas di putaran {$round}",
            );
        }
    }
}
