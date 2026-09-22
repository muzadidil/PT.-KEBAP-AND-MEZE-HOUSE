<?php

namespace Tests\Feature\DailyReport;

use App\Filament\Admin\Pages\DailyReport;
use App\Models\DailyNote;
use App\Support\DailyReport\DailyReportText;
use App\Support\DailyReport\NoteBoard;
use App\Support\DailyReport\NoteTemplate;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Daily Report — catatan harian per bagian dengan sub-catatan bertingkat,
 * dan teks WhatsApp yang dihasilkannya.
 */
class DailyReportPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Carbon::setTestNow('2026-09-22 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function note(string $text, array $attributes = []): DailyNote
    {
        return DailyNote::create(['date' => '2026-09-22', 'text' => $text, ...$attributes]);
    }

    /* -------------------------------------------------------------- pohon */

    public function test_catatan_bertingkat_tanpa_batas(): void
    {
        $master = $this->note('Sales');
        $sub = $this->note('Total Sales', ['parent_id' => $master->id, 'nominal' => 4_606_140]);
        $this->note('Rincian shift pagi', ['parent_id' => $sub->id]);

        $board = NoteBoard::load(Carbon::parse('2026-09-22'));

        $this->assertCount(1, $board->topLevel());
        $this->assertCount(1, $board->children($master));
        $this->assertCount(1, $board->children($sub));
    }

    /* ------------------------------------------------------------- halaman */

    public function test_menambah_bagian_dan_sub_catatan_dengan_nominal(): void
    {
        $page = Livewire::actingAs($this->admin())
            ->test(DailyReport::class)
            ->set('draft.text', 'Sales')
            ->call('addNote')
            ->assertHasNoErrors();

        $master = DailyNote::sole();
        $this->assertSame('Sales', $master->text);
        $this->assertNull($master->nominal);

        $page->set("subDraft.{$master->id}.text", 'Total Sales')
            ->set("subDraft.{$master->id}.nominal", 'Rp 4.606.140')
            ->call('addSub', $master->id);

        $sub = DailyNote::where('parent_id', $master->id)->sole();
        $this->assertSame('Total Sales', $sub->text);
        $this->assertSame(4_606_140, $sub->nominal);

        $page->assertSee('Sales')->assertSee('Total Sales');
    }

    public function test_catatan_wajib_diisi_nominal_boleh_kosong(): void
    {
        Livewire::actingAs($this->admin())
            ->test(DailyReport::class)
            ->set('draft.text', '')
            ->call('addNote')
            ->assertHasErrors(['draft.text']);

        $this->assertSame(0, DailyNote::count());
    }

    public function test_ubah_dan_hapus_catatan_beserta_sub_catatannya(): void
    {
        $master = $this->note('Operation');
        $this->note('Overall', ['parent_id' => $master->id]);

        $page = Livewire::actingAs($this->admin())->test(DailyReport::class);

        $page->call('startEdit', $master->id)
            ->set('edit.text', 'Operasional')
            ->set('edit.nominal', '')
            ->call('saveEdit');

        $this->assertSame('Operasional', $master->fresh()->text);

        $page->call('delete', $master->id);
        $this->assertSame(0, DailyNote::count());
    }

    public function test_pindah_tanggal_memuat_catatan_tanggal_itu(): void
    {
        $this->note('Catatan kemarin', ['date' => '2026-09-21']);
        $this->note('Catatan hari ini', ['date' => '2026-09-22']);

        $page = Livewire::actingAs($this->admin())->test(DailyReport::class);

        $page->assertSee('Catatan hari ini')->assertDontSee('Catatan kemarin');

        $page->call('shiftDay', -1);
        $page->assertSee('Catatan kemarin')->assertDontSee('Catatan hari ini');

        $page->call('goToday');
        $page->assertSee('Catatan hari ini')->assertDontSee('Catatan kemarin');
    }

    /* --------------------------------------------------------------- bagian */

    public function test_isi_bagian_standar_hanya_sekali_per_tanggal(): void
    {
        $page = Livewire::actingAs($this->admin())->test(DailyReport::class)->call('fillTemplate');

        $this->assertSame(7, DailyNote::whereNull('parent_id')->count());
        $this->assertGreaterThan(0, DailyNote::whereNotNull('parent_id')->count());

        $page->assertSee(__('daily_report.template.sales'))->assertSee(__('daily_report.template.plan'));

        // Sudah ada isinya: klik lagi tidak menggandakan.
        $page->call('fillTemplate');
        $this->assertSame(7, DailyNote::whereNull('parent_id')->count());
    }

    /* ------------------------------------------------------------- bagikan */

    public function test_format_whatsapp_bagian_tebal_bernomor_dan_nominal_di_ujung_poin(): void
    {
        $sales = $this->note('Sales');
        $this->note('Total Sales', ['parent_id' => $sales->id, 'nominal' => 4_606_140]);
        $this->note('Total Guest', ['parent_id' => $sales->id]);

        $staff = $this->note('Staff');
        $shift = $this->note('Attendance', ['parent_id' => $staff->id]);
        $this->note('Alen off', ['parent_id' => $shift->id]);

        $this->app->setLocale('en');

        $expected = implode("\n", [
            '*DAILY REPORT – ZEYTIN*',
            '📅 *Date:* 22 Sep 2026',
            '',
            '*1. SALES*',
            '• Total Sales Rp 4.606.140',
            '• Total Guest',
            '',
            '*2. STAFF*',
            '• Attendance',
            '   ◦ Alen off',
        ]);

        $this->assertSame($expected, DailyReportText::make(Carbon::parse('2026-09-22'))->whatsapp());
    }

    public function test_format_whatsapp_manual_di_dalam_teks_tidak_disentuh(): void
    {
        $this->note('Notes', ['nominal' => null])
            ->children()
            ->create(['date' => '2026-09-22', 'text' => '~sudah batal~ pindah ke besok']);

        $this->assertStringContainsString('~sudah batal~ pindah ke besok', DailyReportText::make(Carbon::parse('2026-09-22'))->whatsapp());
    }

    public function test_laporan_kosong_tetap_punya_judul_dan_tanggal(): void
    {
        $this->app->setLocale('en');

        $text = DailyReportText::make(Carbon::parse('2026-09-22'))->whatsapp();

        $this->assertStringStartsWith("*DAILY REPORT – ZEYTIN*\n📅 *Date:* 22 Sep 2026", $text);
        $this->assertStringContainsString(__('daily_report.empty_day'), $text);
    }

    public function test_tautan_kirim_whatsapp_membawa_pesannya(): void
    {
        $this->note('Sales');

        $url = DailyReportText::make(Carbon::parse('2026-09-22'))->whatsappUrl();

        $this->assertStringStartsWith('https://wa.me/?text=', $url);
        $this->assertStringContainsString(rawurlencode('SALES'), $url);
    }

    public function test_template_menghasilkan_pohon_yang_bisa_dibaca_papan(): void
    {
        NoteTemplate::apply(Carbon::parse('2026-09-22'));

        $board = NoteBoard::load(Carbon::parse('2026-09-22'));
        $sales = $board->topLevel()->firstWhere('text', __('daily_report.template.sales'));

        $this->assertNotNull($sales);
        $this->assertCount(2, $board->children($sales));
    }
}
