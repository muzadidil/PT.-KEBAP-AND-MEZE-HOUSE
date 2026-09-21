<?php

namespace Tests\Feature\Payroll;

use App\Filament\Admin\Resources\Payslips\Pages\CreatePayslip;
use App\Filament\Admin\Resources\Zeytin\Purchases\Pages\ManagePurchases;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\Payslip;
use App\Models\Purchase;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Catatan pada pengeluaran, dan potongan gaji yang lahir darinya.
 *
 * Contoh dari klien: gelas pecah, belanja gelas pengganti dicatat, dan
 * karyawan yang memecahkannya dipotong gajinya. Potongannya otomatis masuk
 * slip gaji karyawan itu, dan tidak pernah terpotong dua kali.
 */
class EmployeeDeductionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    protected function employee(string $name = 'Budi'): Employee
    {
        return Employee::create(['name' => $name, 'position' => 'Waiter', 'basic_salary' => 3_000_000]);
    }

    /** Belanja gelas pengganti, dengan potongan untuk karyawan yang memecahkannya. */
    protected function brokenGlass(Employee $employee, string $date = '2026-09-12', int $amount = 50_000): Purchase
    {
        $purchase = Purchase::create([
            'date' => $date,
            'item' => 'Gelas',
            'qty' => 2,
            'price' => 25_000,
            'note' => 'Gelas pecah',
        ]);

        $purchase->deduction()->create(['employee_id' => $employee->id, 'amount' => $amount]);

        return $purchase;
    }

    public function test_formulir_belanja_menyimpan_catatan_dan_potongan(): void
    {
        $employee = $this->employee();

        Livewire::actingAs($this->admin())
            ->test(ManagePurchases::class)
            ->callAction('create', data: [
                'date' => '2026-09-12',
                'item' => 'Gelas',
                'qty' => 2,
                'price' => 25_000,
                'note' => 'Gelas pecah',
                'deduction' => ['employee_id' => $employee->id, 'amount' => 50_000],
            ])
            ->assertHasNoActionErrors();

        $purchase = Purchase::sole();
        $this->assertSame('Gelas pecah', $purchase->note);

        $deduction = $purchase->deduction;
        $this->assertSame($employee->id, $deduction->employee_id);
        $this->assertSame(50_000, $deduction->amount);

        // Tanggal dan alasannya ikut baris belanjanya.
        $this->assertSame('2026-09-12', $deduction->date->toDateString());
        $this->assertSame('Gelas pecah', $deduction->reason);
    }

    public function test_belanja_tanpa_karyawan_tidak_membuat_potongan(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManagePurchases::class)
            ->callAction('create', data: ['date' => '2026-09-12', 'item' => 'Sabun', 'qty' => 1, 'price' => 10_000])
            ->assertHasNoActionErrors();

        $this->assertSame(0, EmployeeDeduction::count());
    }

    public function test_potongan_otomatis_masuk_slip_karyawannya(): void
    {
        Repeater::fake();

        $budi = $this->employee('Budi');
        $this->brokenGlass($budi);
        $this->brokenGlass($this->employee('Sari'));

        Livewire::actingAs($this->superAdmin())
            ->test(CreatePayslip::class)
            ->fillForm(['period' => '2026-09-01'])
            ->fillForm(['employee_id' => $budi->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $slip = Payslip::sole();

        // Hanya potongan Budi, bukan potongan Sari.
        $this->assertCount(1, $slip->deductions);
        $this->assertSame('Gelas pecah (12/09)', $slip->deductions[0]['label']);
        $this->assertSame(50_000, $slip->total_deductions);
        $this->assertSame(2_950_000, $slip->net_pay);

        $this->assertSame($slip->id, EmployeeDeduction::where('employee_id', $budi->id)->value('payslip_id'));
    }

    public function test_potongan_yang_sudah_dipotong_tidak_ditawarkan_lagi(): void
    {
        $budi = $this->employee();
        $this->brokenGlass($budi, '2026-09-12');

        $september = Payslip::create([
            'employee_id' => $budi->id,
            'period' => '2026-09-01',
            'basic_salary' => 3_000_000,
            'deductions' => EmployeeDeduction::query()->pendingFor($budi->id, now()->setDate(2026, 9, 1))->get()
                ->map(fn (EmployeeDeduction $d) => ['label' => $d->label(), 'amount' => $d->amount, 'deduction_id' => $d->id])
                ->all(),
        ]);

        $this->assertSame(50_000, $september->total_deductions);

        // Slip Oktober tidak menawarkan gelas pecah September lagi.
        $this->assertSame(0, EmployeeDeduction::query()->pendingFor($budi->id, now()->setDate(2026, 10, 1))->count());
    }

    /** Potongan bulan lalu yang belum sempat masuk slip ikut ke slip berikutnya. */
    public function test_potongan_bulan_lalu_yang_tertunda_ikut_slip_berikutnya(): void
    {
        $budi = $this->employee();
        $this->brokenGlass($budi, '2026-08-20');

        $this->assertSame(1, EmployeeDeduction::query()->pendingFor($budi->id, now()->setDate(2026, 9, 1))->count());

        // Potongan bulan depan belum ikut slip bulan ini.
        $this->brokenGlass($budi, '2026-10-02');
        $this->assertSame(1, EmployeeDeduction::query()->pendingFor($budi->id, now()->setDate(2026, 9, 1))->count());
    }

    public function test_menghapus_slip_mengembalikan_potongan_ke_antrean(): void
    {
        $budi = $this->employee();
        $deduction = $this->brokenGlass($budi)->deduction;

        $slip = Payslip::create([
            'employee_id' => $budi->id,
            'period' => '2026-09-01',
            'basic_salary' => 3_000_000,
            'deductions' => [['label' => $deduction->label(), 'amount' => 50_000, 'deduction_id' => $deduction->id]],
        ]);

        $this->assertSame($slip->id, $deduction->fresh()->payslip_id);

        $slip->delete();

        $this->assertNull($deduction->fresh()->payslip_id);
    }

    public function test_potongan_yang_sudah_dipotong_tidak_bisa_dihapus_dari_pengeluaran(): void
    {
        $budi = $this->employee();
        $purchase = $this->brokenGlass($budi);
        $deduction = $purchase->deduction;

        Payslip::create([
            'employee_id' => $budi->id,
            'period' => '2026-09-01',
            'basic_salary' => 3_000_000,
            'deductions' => [['label' => $deduction->label(), 'amount' => 50_000, 'deduction_id' => $deduction->id]],
        ]);

        // Menghapus belanjanya tidak menghapus potongan yang sudah di slip.
        $purchase->delete();

        $this->assertNotNull($deduction->fresh());
    }

    public function test_menghapus_pengeluaran_menghapus_potongan_yang_belum_dipotong(): void
    {
        $purchase = $this->brokenGlass($this->employee());

        $purchase->delete();

        $this->assertSame(0, EmployeeDeduction::count());
    }

    public function test_mengubah_tanggal_dan_catatan_pengeluaran_ikut_mengubah_potongannya(): void
    {
        $purchase = $this->brokenGlass($this->employee(), '2026-09-12');

        $purchase->update(['date' => '2026-09-14', 'note' => 'Piring pecah']);

        $deduction = $purchase->deduction()->first();
        $this->assertSame('2026-09-14', $deduction->date->toDateString());
        $this->assertSame('Piring pecah', $deduction->reason);
    }
}
