<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_membaca_angka_dari_berbagai_bentuk_ketikan(): void
    {
        $this->assertSame(45000, Money::parse('45.000'));
        $this->assertSame(45000, Money::parse('45000'));
        $this->assertSame(45000, Money::parse('Rp 45.000'));
        $this->assertSame(45000, Money::parse(45000));
        $this->assertSame(0, Money::parse(''));
        $this->assertSame(0, Money::parse(null));
    }

    public function test_pembagian_tidak_pernah_kehilangan_atau_menciptakan_rupiah(): void
    {
        // 100 tidak habis dibagi 60/40 dalam rupiah bulat pada banyak angka;
        // yang penting jumlah hasilnya selalu persis sama dengan asalnya.
        foreach (range(0, 500) as $amount) {
            $shares = Money::split($amount, ['aslan' => 60, 'leo' => 40]);

            $this->assertSame(
                $amount,
                array_sum($shares),
                "Pembagian {$amount} tidak berjumlah kembali ke asalnya",
            );
        }
    }

    public function test_sisa_pembagian_jatuh_ke_porsi_terbesar(): void
    {
        // 1 rupiah dibagi 60/40: yang 60% yang menanggung rupiah itu.
        $this->assertSame(['aslan' => 1, 'leo' => 0], Money::split(1, ['aslan' => 60, 'leo' => 40]));
    }

    public function test_pembagian_biasa_tanpa_sisa(): void
    {
        $this->assertSame(
            ['aslan' => 600_000, 'leo' => 400_000],
            Money::split(1_000_000, ['aslan' => 60, 'leo' => 40]),
        );
    }

    public function test_porsi_kosong_tidak_membuat_pembagian_gagal(): void
    {
        $this->assertSame(['a' => 0, 'b' => 0], Money::split(1000, ['a' => 0, 'b' => 0]));
        $this->assertSame(['a' => 0, 'b' => 0], Money::split(0, ['a' => 60, 'b' => 40]));
    }

    public function test_format_rupiah(): void
    {
        $this->assertSame('Rp 45.000', Money::format(45000));
        $this->assertSame('Rp 0', Money::format(0));
        $this->assertSame('Rp 0', Money::format(null));
        $this->assertSame('-Rp 12.500', Money::format(-12500));
    }
}
