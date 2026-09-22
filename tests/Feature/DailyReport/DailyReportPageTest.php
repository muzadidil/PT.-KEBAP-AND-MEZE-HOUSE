<?php

namespace Tests\Feature\DailyReport;

use App\Filament\Admin\Pages\DailyReport;
use App\Models\DailyNote;
use App\Models\DailyReportOption;
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

        $this->assertSame(6, DailyNote::whereNull('parent_id')->count());
        $this->assertGreaterThan(0, DailyNote::whereNotNull('parent_id')->count());

        $page->assertSee(__('daily_report.template.operation'))->assertSee(__('daily_report.template.plan'));

        // Sudah ada isinya: klik lagi tidak menggandakan.
        $page->call('fillTemplate');
        $this->assertSame(6, DailyNote::whereNull('parent_id')->count());
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
        $reviews = $board->topLevel()->firstWhere('text', __('daily_report.template.reviews'));

        $this->assertNotNull($reviews);
        $this->assertCount(6, $board->children($reviews));
    }

    /* -------------------------------------------------------- jenis isian */

    protected function option(string $group, string $label): DailyReportOption
    {
        return DailyReportOption::where('group', $group)->where('label', $label)->sole();
    }

    /** Contoh laporan tim persis: pilihan, angka, rating bintang, status. */
    public function test_format_whatsapp_mengikuti_jenis_isian(): void
    {
        NoteTemplate::apply(Carbon::parse('2026-09-22'));

        $operation = DailyNote::where('text', 'Operation')->sole();
        $operation->update(['option_id' => $this->option('condition', 'Good')->id]);

        DailyNote::where('text', 'Rating')->update(['value' => 4.9]);
        DailyNote::where('text', 'Total Reviews')->update(['value' => 56]);

        $task = DailyNote::where('text', 'Task/Work Update')->sole();
        $this->note('Salon agreement', ['parent_id' => $task->id, 'kind' => 'status', 'option_id' => $this->option('status', 'Pending')->id]);
        $this->note('Add CCTV Zeytin', ['parent_id' => $task->id, 'kind' => 'status']);

        $this->app->setLocale('en');

        $expected = implode("\n", [
            '*DAILY REPORT – ZEYTIN*',
            '📅 *Date:* 22 Sep 2026',
            '',
            '*1. ⚙️ OPERATION*',
            '• 🟢 Good',
            '',
            '*2. 👥 STAFF ISSUE*',
            '',
            '*3. ⭐ GOOGLE REVIEWS*',
            '• Rating: ⭐ 4.9',
            '• Total Reviews: 56',
            '• New Reviews: -',
            '• Replied: -',
            '• Negative Reviews: -',
            '• Follow Up: -',
            '',
            '*4. 📋 TASK/WORK UPDATE*',
            '• Salon agreement ⏳ Pending',
            '• Add CCTV Zeytin',
            '',
            '*5. 📌 IMPORTANT NOTES*',
            '',
            '*6. 🗓️ PLAN/FOLLOW UP*',
        ]);

        $this->assertSame($expected, DailyReportText::make(Carbon::parse('2026-09-22'))->whatsapp());
    }

    public function test_memilih_kondisi_dan_status_dari_master(): void
    {
        NoteTemplate::apply(Carbon::parse('2026-09-22'));

        $operation = DailyNote::where('text', 'Operation')->sole();
        $task = DailyNote::where('text', 'Task/Work Update')->sole();
        $problem = $this->option('condition', 'Problem');
        $finish = $this->option('status', 'Finish');

        $page = Livewire::actingAs($this->admin())->test(DailyReport::class)
            ->call('setOption', $operation->id, (string) $problem->id);

        $this->assertSame($problem->id, $operation->fresh()->option_id);

        // Status bukan pilihan kondisi: tidak boleh dipasang di bagian Operation.
        $page->call('setOption', $operation->id, (string) $finish->id);
        $this->assertNull($operation->fresh()->option_id);

        // Poin baru di bawah bagian Status ikut jenis status, dengan statusnya.
        $page->set("subDraft.{$task->id}.text", 'Sign board cube survey')
            ->set("subDraft.{$task->id}.option_id", (string) $finish->id)
            ->call('addSub', $task->id);

        $sub = DailyNote::where('parent_id', $task->id)->sole();
        $this->assertSame('status', $sub->kind);
        $this->assertSame($finish->id, $sub->option_id);
    }

    public function test_angka_dan_rating_bukan_nominal(): void
    {
        NoteTemplate::apply(Carbon::parse('2026-09-22'));

        $rating = DailyNote::where('text', 'Rating')->sole();
        $total = DailyNote::where('text', 'Total Reviews')->sole();
        $reviews = DailyNote::where('text', 'Google Reviews')->sole();

        $page = Livewire::actingAs($this->admin())->test(DailyReport::class)
            ->call('setValue', $rating->id, '4,9')
            ->call('setValue', $total->id, '1.200');

        $this->assertSame(4.9, $rating->fresh()->value);
        $this->assertSame(1200.0, $total->fresh()->value);

        // Rating dibatasi 0–5; klik bintang ke-1 memberi nilai 1.
        $page->call('setValue', $rating->id, '9');
        $this->assertSame(5.0, $rating->fresh()->value);

        $page->call('setValue', $rating->id, 1);
        $this->assertSame('1', $rating->fresh()->formattedValue());

        // Poin baru di bawah bagian angka juga angka, bukan nominal.
        $page->set("subDraft.{$reviews->id}.text", 'Photos')
            ->set("subDraft.{$reviews->id}.value", '12')
            ->call('addSub', $reviews->id);

        $photos = DailyNote::where('text', 'Photos')->sole();
        $this->assertSame('number', $photos->kind);
        $this->assertSame(12.0, $photos->value);
        $this->assertNull($photos->nominal);
    }

    public function test_bagian_baru_bisa_dipilih_jenis_dan_ikonnya(): void
    {
        Livewire::actingAs($this->admin())->test(DailyReport::class)
            ->set('draft.text', 'Kitchen')
            ->set('draft.kind', 'choice')
            ->set('draft.icon', '🍳')
            ->call('addNote')
            ->assertHasNoErrors();

        $section = DailyNote::sole();
        $this->assertSame('choice', $section->kind);
        $this->assertSame('🍳', $section->icon);
    }

    public function test_master_pilihan_ditambah_diubah_dan_dihapus(): void
    {
        $page = Livewire::actingAs($this->admin())->test(DailyReport::class)
            ->set('newOption.status.label', 'Cancelled')
            ->set('newOption.status.icon', '❌')
            ->call('addOption', 'status')
            ->assertHasNoErrors();

        $cancelled = $this->option('status', 'Cancelled');
        $this->assertSame('❌ Cancelled', $cancelled->display());

        $page->call('startOptionEdit', $cancelled->id)
            ->set('optionEdit.label', 'Batal')
            ->call('saveOption');

        $this->assertSame('Batal', $cancelled->fresh()->label);

        // Catatan yang memakainya tetap ada, hanya jadi belum dipilih.
        $note = $this->note('Salon agreement', ['kind' => 'status', 'option_id' => $cancelled->id]);

        $page->call('deleteOption', $cancelled->id);

        $this->assertNull($note->fresh()->option_id);
        $this->assertFalse(DailyReportOption::whereKey($cancelled->id)->exists());
    }
}
