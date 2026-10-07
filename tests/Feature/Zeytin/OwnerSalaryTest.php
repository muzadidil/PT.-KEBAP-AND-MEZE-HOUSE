<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Resources\Zeytin\OwnerSalaries\OwnerSalaryResource;
use App\Filament\Admin\Resources\Zeytin\OwnerSalaries\Pages\ManageOwnerSalaries;
use App\Models\OwnerSalary;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

/** Gaji pemilik (Super Admin): pemilik, periode (tahun dan bulan), nominal. */
class OwnerSalaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_super_admin_mencatat_gaji_pemilik_per_bulan(): void
    {
        [$aslan, $leo] = $this->owners();

        Livewire::actingAs($this->superAdmin())
            ->test(ManageOwnerSalaries::class)
            ->callAction(CreateAction::class, [
                'owner_id' => $aslan->id,
                'month' => '2026-09-17',
                'amount' => 15_000_000,
            ])
            ->assertHasNoActionErrors();

        $salary = OwnerSalary::first();
        $this->assertSame('2026-09-01', $salary->month->toDateString());
        $this->assertSame(15_000_000, $salary->amount);
        $this->assertSame('Aslan', $salary->owner->name);
        $this->assertNotNull($salary->user_id);
    }

    public function test_satu_pemilik_hanya_satu_gaji_per_bulan(): void
    {
        [$aslan, $leo] = $this->owners();
        OwnerSalary::create(['owner_id' => $aslan->id, 'month' => '2026-09-01', 'amount' => 1]);

        $page = Livewire::actingAs($this->superAdmin())->test(ManageOwnerSalaries::class);

        $page->callAction(CreateAction::class, ['owner_id' => $aslan->id, 'month' => '2026-09-20', 'amount' => 2])
            ->assertHasActionErrors(['month']);
        $this->assertSame(1, OwnerSalary::count());

        // Pemilik lain di bulan yang sama boleh.
        Livewire::actingAs($this->superAdmin())->test(ManageOwnerSalaries::class)
            ->callAction(CreateAction::class, ['owner_id' => $leo->id, 'month' => '2026-09-20', 'amount' => 2])
            ->assertHasNoActionErrors();
        $this->assertSame(2, OwnerSalary::count());
    }

    public function test_menu_hanya_untuk_super_admin(): void
    {
        $this->actingAs($this->superAdmin());
        $this->assertTrue(OwnerSalaryResource::canAccess());

        $this->flushSession();
        $this->actingAs($this->admin());
        $this->assertFalse(OwnerSalaryResource::canAccess());
    }
}
