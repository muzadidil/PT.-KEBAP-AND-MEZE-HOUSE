<?php

namespace Tests\Feature\Payroll;

use App\Filament\Admin\Resources\Payslips\Pages\CreatePayslip;
use App\Filament\Admin\Resources\Payslips\Pages\EditPayslip;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\Payslip;
use App\Support\Payroll\PayslipPdf;
use App\Support\Terbilang;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Database\QueryException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Slip gaji, meniru aplikasi Slip Gaji (repo slip_gaji_cv_alfarisy) — dengan
 * aturan yang di sana hanya dijaga formulir, di sini dijaga model.
 */
class PayslipTest extends TestCase
{
    protected function employee(array $attributes = []): Employee
    {
        return Employee::create([
            'name' => 'Budi Santoso',
            'nik' => 'ZT-'.fake()->unique()->numerify('###'),
            'position' => 'Cook',
            'section' => 'Kitchen Staff',
            'basic_salary' => 3_000_000,
            ...$attributes,
        ]);
    }

    protected function slip(Employee $employee, string $period = '2026-09-01', array $attributes = []): Payslip
    {
        return Payslip::create([
            'employee_id' => $employee->id,
            'period' => $period,
            'basic_salary' => $employee->basic_salary,
            ...$attributes,
        ]);
    }

    public function test_nomor_slip_mengikuti_bulan_dan_berurut(): void
    {
        $this->assertSame('SG/2026/IX/001', $this->slip($this->employee())->number);
        $this->assertSame('SG/2026/IX/002', $this->slip($this->employee())->number);

        // Bulan lain mulai dari 001 lagi.
        $this->assertSame('SG/2026/X/001', $this->slip($this->employee(), '2026-10-15')->number);
    }

    /**
     * Aplikasi aslinya menghitung jumlah slip di bulan itu, jadi menghapus
     * satu slip membuat slip berikutnya mendapat nomor yang sudah dipakai.
     */
    public function test_menghapus_slip_tidak_membuat_nomor_kembar(): void
    {
        $this->slip($this->employee());
        $second = $this->slip($this->employee());
        $this->slip($this->employee());

        $second->delete();

        $this->assertSame('SG/2026/IX/004', $this->slip($this->employee())->number);
    }

    public function test_slip_yang_dipindah_bulan_diberi_nomor_bulan_tujuannya(): void
    {
        $slip = $this->slip($this->employee());

        $slip->update(['period' => '2026-10-01']);

        $this->assertSame('SG/2026/X/001', $slip->fresh()->number);
    }

    public function test_total_dan_gaji_bersih_dihitung_dari_rinciannya(): void
    {
        $slip = $this->slip($this->employee(), attributes: [
            'earnings' => [
                ['label' => 'Tunjangan Makan', 'amount' => 500_000],
                ['label' => 'Lembur', 'amount' => '250.000'],
            ],
            'deductions' => [
                ['label' => 'Kasbon', 'amount' => 300_000],
            ],
        ]);

        $this->assertSame(3_750_000, $slip->total_earnings);
        $this->assertSame(300_000, $slip->total_deductions);
        $this->assertSame(3_450_000, $slip->net_pay);
    }

    public function test_item_fix_selalu_bernominal_bawaannya(): void
    {
        PayComponent::create(['type' => PayComponent::DEDUCTION, 'name' => 'BPJS Kesehatan', 'default_amount' => 100_000, 'fixed' => true]);
        PayComponent::create(['type' => PayComponent::EARNING, 'name' => 'Transport', 'default_amount' => 200_000, 'fixed' => false]);

        $slip = $this->slip($this->employee(), attributes: [
            'earnings' => [['label' => 'Transport', 'amount' => 350_000]],
            // Ejaan dan huruf besar-kecil yang diketik tangan tetap dikenali.
            'deductions' => [['label' => ' bpjs kesehatan ', 'amount' => 5]],
        ]);

        $this->assertSame(350_000, $slip->earnings[0]['amount'], 'Item tidak fix boleh ditimpa.');
        $this->assertSame(100_000, $slip->deductions[0]['amount'], 'Item fix dikunci ke nominal bawaan.');
    }

    public function test_baris_kosong_tidak_ikut_tersimpan(): void
    {
        $slip = $this->slip($this->employee(), attributes: [
            'earnings' => [['label' => '', 'amount' => 0], ['label' => 'Bonus', 'amount' => 100_000]],
        ]);

        $this->assertCount(1, $slip->earnings);
    }

    /** Slip adalah dokumen yang sudah diserahkan; ia tidak ikut berubah. */
    public function test_data_karyawan_disalin_ke_slip(): void
    {
        $employee = $this->employee(['name' => 'Budi Santoso', 'position' => 'Cook']);
        $slip = $this->slip($employee);

        $employee->update(['name' => 'Budi S.', 'position' => 'Head Chef']);
        $slip->update(['issued_on' => '2026-09-30']);

        $slip->refresh();
        $this->assertSame('Budi Santoso', $slip->employee_name);
        $this->assertSame('Cook', $slip->employee_position);
    }

    public function test_satu_karyawan_hanya_satu_slip_per_bulan(): void
    {
        $employee = $this->employee();
        $this->slip($employee, '2026-09-01');

        $this->expectException(QueryException::class);

        $this->slip($employee, '2026-09-20');
    }

    #[DataProvider('spelledAmounts')]
    public function test_terbilang(int $amount, string $expected): void
    {
        $this->assertSame($expected, Terbilang::rupiah($amount));
    }

    public static function spelledAmounts(): array
    {
        return [
            [0, 'Nol rupiah'],
            [11, 'Sebelas rupiah'],
            [115, 'Seratus lima belas rupiah'],
            [1_000, 'Seribu rupiah'],
            [21_000, 'Dua puluh satu ribu rupiah'],
            [1_250_000, 'Satu juta dua ratus lima puluh ribu rupiah'],
            [3_450_000, 'Tiga juta empat ratus lima puluh ribu rupiah'],
            [2_000_111_500, 'Dua miliar seratus sebelas ribu lima ratus rupiah'],
        ];
    }

    public function test_pdf_slip_berisi_angka_yang_sama_dan_berbahasa_indonesia(): void
    {
        app()->setLocale('en');

        $slip = $this->slip($this->employee(), attributes: [
            'earnings' => [['label' => 'Tunjangan Makan', 'amount' => 500_000]],
        ]);

        $html = view('payslips.paper', ['paper' => PayslipPdf::paper($slip)])->render();

        $this->assertStringContainsString('SLIP GAJI KARYAWAN', $html);
        $this->assertStringContainsString('Periode: September 2026', $html);
        $this->assertStringContainsString('SG/2026/IX/001', $html);
        $this->assertStringContainsString('Rp 3.500.000', $html);
        $this->assertStringContainsString('Tiga juta lima ratus ribu rupiah', $html);

        $pdf = new PayslipPdf($slip);
        $this->assertStringStartsWith('%PDF-', $pdf->render());
        $this->assertSame('SlipGaji_Budi_Santoso_September_2026.pdf', $pdf->filename());
    }

    public function test_pdf_slip_dibuka_di_tab_baru_hanya_untuk_super_admin(): void
    {
        $slip = $this->slip($this->employee());
        $url = route('filament.admin.pdf.payslip', $slip);

        $response = $this->actingAs($this->superAdmin())->get($url)->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));

        // Gaji per orang tidak untuk Admin.
        $this->flushSession();
        $this->actingAs($this->admin())->get($url)->assertForbidden();
    }

    public function test_formulir_mengisi_gaji_pokok_dan_menyimpan_slip(): void
    {
        Filament::setCurrentPanel('admin');
        Repeater::fake();

        $employee = $this->employee(['basic_salary' => 4_000_000]);

        Livewire::actingAs($this->superAdmin())
            ->test(CreatePayslip::class)
            ->fillForm(['employee_id' => $employee->id])
            ->assertSchemaStateSet(['basic_salary' => 4_000_000])
            ->fillForm([
                'period' => '2026-09-01',
                'earnings' => [['label' => 'Lembur', 'amount' => 150_000]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $slip = Payslip::sole();
        $this->assertSame(4_150_000, $slip->net_pay);
        $this->assertSame('Budi Santoso', $slip->employee_name);
    }

    public function test_formulir_menolak_slip_kedua_di_bulan_yang_sama(): void
    {
        Filament::setCurrentPanel('admin');

        $employee = $this->employee();
        $existing = $this->slip($employee, '2026-09-01');

        Livewire::actingAs($this->superAdmin())
            ->test(CreatePayslip::class)
            ->fillForm(['employee_id' => $employee->id, 'period' => '2026-09-15'])
            ->call('create')
            ->assertHasFormErrors(['period'])
            ->assertSee($existing->number);

        $this->assertSame(1, Payslip::count());
    }

    public function test_slip_bisa_diubah_tanpa_dianggap_kembar_dengan_dirinya(): void
    {
        Filament::setCurrentPanel('admin');

        $slip = $this->slip($this->employee());

        Livewire::actingAs($this->superAdmin())
            ->test(EditPayslip::class, ['record' => $slip->getRouteKey()])
            ->fillForm(['basic_salary' => 3_500_000])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3_500_000, $slip->fresh()->net_pay);
    }

    /* ------------------------------------------------------------ catatan */

    public function test_catatan_kosong_disimpan_sebagai_null(): void
    {
        $slip = $this->slip($this->employee(), attributes: ['note' => "   \n  "]);

        $this->assertNull($slip->note);
    }

    public function test_catatan_ikut_tersimpan_dan_tercetak_di_slip(): void
    {
        $slip = $this->slip($this->employee(), attributes: [
            'note' => 'Terima kasih atas kerja kerasnya bulan ini, terus semangat!',
        ]);

        $this->assertSame('Terima kasih atas kerja kerasnya bulan ini, terus semangat!', $slip->note);

        $html = view('payslips.paper', ['paper' => PayslipPdf::paper($slip)])->render();

        $this->assertStringContainsString('Catatan:', $html);
        $this->assertStringContainsString('Terima kasih atas kerja kerasnya bulan ini, terus semangat!', $html);
    }

    /** Slip tanpa catatan tidak menampilkan kotak catatan sama sekali. */
    public function test_slip_tanpa_catatan_tidak_menampilkan_kotak_catatan(): void
    {
        $slip = $this->slip($this->employee());

        $html = view('payslips.paper', ['paper' => PayslipPdf::paper($slip)])->render();

        // Label "Catatan:" hanya ditulis di dalam kotak; ketiadaannya
        // membuktikan kotaknya tidak dirender sama sekali.
        $this->assertStringNotContainsString('Catatan:', $html);
    }

    public function test_formulir_menyimpan_catatan_untuk_karyawan(): void
    {
        Filament::setCurrentPanel('admin');
        Repeater::fake();

        $employee = $this->employee();

        Livewire::actingAs($this->superAdmin())
            ->test(CreatePayslip::class)
            ->fillForm([
                'employee_id' => $employee->id,
                'period' => '2026-09-01',
                'note' => 'Pertahankan performa yang baik ini.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Pertahankan performa yang baik ini.', Payslip::sole()->note);
    }

    public function test_catatan_bisa_dihapus_lewat_formulir_ubah(): void
    {
        Filament::setCurrentPanel('admin');

        $slip = $this->slip($this->employee(), attributes: ['note' => 'Catatan lama']);

        Livewire::actingAs($this->superAdmin())
            ->test(EditPayslip::class, ['record' => $slip->getRouteKey()])
            ->fillForm(['note' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($slip->fresh()->note);
    }
}
