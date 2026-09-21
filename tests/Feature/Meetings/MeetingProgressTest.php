<?php

namespace Tests\Feature\Meetings;

use App\Filament\Admin\Pages\MeetingProgress;
use App\Models\MeetingProject;
use App\Models\MeetingTask;
use App\Support\Meetings\MeetingReport;
use App\Support\Meetings\TaskBoard;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Progres Rapat — meniru TaskFlow (repo TODO_muzadidil): progres dari
 * sub-tugas bertingkat, arsip otomatis, dan daftar yang dibagikan sebagai
 * teks atau pesan WhatsApp.
 */
class MeetingProgressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Carbon::setTestNow('2026-09-21 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function task(string $text, array $attributes = []): MeetingTask
    {
        return MeetingTask::create(['text' => $text, ...$attributes]);
    }

    /* -------------------------------------------------------------- progres */

    public function test_progres_dihitung_dari_seluruh_sub_tugas_di_level_mana_pun(): void
    {
        $task = $this->task('Renovasi dapur');
        $a = $this->task('Pesan kompor', ['parent_id' => $task->id, 'completed' => true]);
        $this->task('Cari tukang', ['parent_id' => $task->id]);
        $this->task('Bandingkan harga', ['parent_id' => $a->id, 'completed' => true]);

        $board = TaskBoard::load();

        // 2 dari 3 sub-tugas selesai, termasuk yang dua tingkat di bawah.
        $this->assertSame(['total' => 3, 'done' => 2], $board->count($task));
        $this->assertSame(67, $board->progress($task));

        // Tugas yang dicentang selesai langsung 100%, apa pun sub-tugasnya.
        $task->update(['completed' => true]);
        $this->assertSame(100, TaskBoard::load()->progress($task->fresh()));
    }

    public function test_progres_rata_rata_dari_tugas_utama(): void
    {
        $this->task('Selesai', ['completed' => true]);
        $this->task('Belum');

        $board = TaskBoard::load();

        $this->assertSame(50, $board->mean($board->topLevel()));
    }

    public function test_status_tenggat_sama_dengan_taskflow(): void
    {
        $this->assertSame('overdue', $this->task('A', ['deadline' => '2026-09-20'])->deadlineStatus());
        $this->assertSame('soon', $this->task('B', ['deadline' => '2026-09-21'])->deadlineStatus());
        $this->assertSame('soon', $this->task('C', ['deadline' => '2026-09-22'])->deadlineStatus());
        $this->assertSame('normal', $this->task('D', ['deadline' => '2026-09-23'])->deadlineStatus());

        // Yang sudah selesai tidak pernah terlambat.
        $this->assertFalse($this->task('E', ['deadline' => '2026-09-01', 'completed' => true])->isOverdue());
    }

    public function test_tugas_selesai_diarsipkan_otomatis_setelah_sehari(): void
    {
        $old = $this->task('Selesai kemarin', ['completed' => true]);
        $old->forceFill(['completed_at' => now()->subHours(25)])->save();

        $fresh = $this->task('Baru selesai', ['completed' => true]);

        Livewire::actingAs($this->admin())->test(MeetingProgress::class);

        $this->assertTrue($old->fresh()->archived);
        $this->assertFalse($fresh->fresh()->archived);
    }

    /* -------------------------------------------------------------- halaman */

    public function test_menambah_tugas_sub_tugas_dan_mencentang(): void
    {
        $page = Livewire::actingAs($this->admin())
            ->test(MeetingProgress::class)
            ->set('draft.text', 'Evaluasi menu baru')
            ->set('draft.priority', 'high')
            ->set('draft.deadline', '2026-09-30')
            ->call('addTask')
            ->assertHasNoErrors();

        $task = MeetingTask::sole();
        $this->assertSame('high', $task->priority);

        $page->set("subDraft.{$task->id}", 'Uji rasa')->call('addSubtask', $task->id);
        $sub = MeetingTask::where('parent_id', $task->id)->sole();

        $page->call('toggle', $sub->id);
        $this->assertTrue($sub->fresh()->completed);
        $this->assertNotNull($sub->fresh()->completed_at);

        $page->assertSee('Evaluasi menu baru')->assertSee('Uji rasa');
    }

    /** Pekerjaan baru di bawah tugas yang sudah selesai membukanya lagi. */
    public function test_menambah_sub_tugas_membuka_lagi_tugas_yang_selesai(): void
    {
        $task = $this->task('Pesan seragam', ['completed' => true]);

        Livewire::actingAs($this->admin())
            ->test(MeetingProgress::class)
            ->set("subDraft.{$task->id}", 'Ukur karyawan baru')
            ->call('addSubtask', $task->id);

        $this->assertFalse($task->fresh()->completed);
    }

    public function test_tugas_wajib_diisi_dan_link_harus_alamat(): void
    {
        Livewire::actingAs($this->admin())
            ->test(MeetingProgress::class)
            ->set('draft.text', '')
            ->call('addTask')
            ->assertHasErrors(['draft.text'])
            ->set('draft.text', 'Cek CCTV')
            ->set('draft.link', 'bukan alamat')
            ->call('addTask')
            ->assertHasErrors(['draft.link']);

        $this->assertSame(0, MeetingTask::count());
    }

    public function test_arsip_kembalikan_dan_hapus_beserta_sub_tugasnya(): void
    {
        $task = $this->task('Tutup buku Agustus');
        $this->task('Cocokkan kas', ['parent_id' => $task->id]);

        $page = Livewire::actingAs($this->admin())->test(MeetingProgress::class);

        $page->call('archive', $task->id);
        $this->assertTrue($task->fresh()->archived);

        $page->call('restore', $task->id);
        $this->assertFalse($task->fresh()->archived);

        $page->call('delete', $task->id);
        $this->assertSame(0, MeetingTask::count());
    }

    public function test_impor_satu_tugas_per_baris(): void
    {
        $project = MeetingProject::create(['name' => 'Operasional']);

        Livewire::actingAs($this->admin())
            ->test(MeetingProgress::class)
            ->set('importText', "Beli gas\n\n  Servis AC  \nCek stok minyak")
            ->set('draft.project_id', (string) $project->id)
            ->call('importTasks');

        $this->assertSame(['Beli gas', 'Servis AC', 'Cek stok minyak'], MeetingTask::orderBy('id')->pluck('text')->all());
        $this->assertSame(3, $project->tasks()->count());
    }

    public function test_proyek_ditambah_diganti_nama_dan_dihapus_beserta_tugasnya(): void
    {
        $page = Livewire::actingAs($this->admin())
            ->test(MeetingProgress::class)
            ->set('newProject', 'Menu Baru')
            ->call('addProject');

        $project = MeetingProject::sole();
        $page->assertSet('project', (string) $project->id);

        $page->call('startRename', $project->id)->set('renameValue', 'Menu Musim Hujan')->call('saveRename');
        $this->assertSame('Menu Musim Hujan', $project->fresh()->name);

        $this->task('Foto menu', ['project_id' => $project->id]);

        $page->call('deleteProject', $project->id)->assertSet('project', 'all');
        $this->assertSame(0, MeetingTask::count());
    }

    public function test_saringan_belum_selesai_selesai_terlambat_dan_cari(): void
    {
        $this->task('Belum, terlambat', ['deadline' => '2026-09-10']);
        $this->task('Sudah', ['completed' => true]);
        $this->task('Belum, santai', ['deadline' => '2026-10-10']);

        $page = Livewire::actingAs($this->admin())->test(MeetingProgress::class);

        $texts = fn () => $page->instance()->tasks->pluck('text')->sort()->values()->all();

        $page->call('setFilter', 'active');
        $this->assertSame(['Belum, santai', 'Belum, terlambat'], $texts());

        $page->call('setFilter', 'completed');
        $this->assertSame(['Sudah'], $texts());

        $page->call('setFilter', 'overdue');
        $this->assertSame(['Belum, terlambat'], $texts());

        $page->call('setFilter', 'all')->set('search', 'SANTAI');
        $this->assertSame(['Belum, santai'], $texts());
    }

    /* ------------------------------------------------------------- bagikan */

    public function test_format_whatsapp_sama_dengan_taskflow(): void
    {
        $project = MeetingProject::create(['name' => 'Dapur']);
        $task = $this->task('Renovasi dapur', ['project_id' => $project->id, 'priority' => 'high', 'deadline' => '2026-09-30']);
        $this->task('Pesan kompor', ['parent_id' => $task->id, 'completed' => true]);
        $this->task('Cari tukang', ['parent_id' => $task->id]);
        $this->task('Cat ulang', ['project_id' => $project->id, 'completed' => true, 'priority' => 'low']);

        $this->app->setLocale('id');

        $expected = implode("\n", [
            '*LAPORAN PROGRES RAPAT — DAPUR*',
            '_21 September 2026 • progres 75%_',
            '',
            '*BELUM SELESAI (1)*',
            '1. Renovasi dapur _(Kerja • Tinggi • tenggat 30 Sep 2026)_',
            '   _◦ ~Pesan kompor~_',
            '   _◦ Cari tukang_',
            '',
            '*SELESAI (1)*',
            '1. ~Cat ulang~ _(Kerja • Rendah)_',
        ]);

        $this->assertSame($expected, MeetingReport::make()->whatsapp((string) $project->id));
    }

    public function test_format_teks_biasa_dan_semua_proyek_dikelompokkan(): void
    {
        $project = MeetingProject::create(['name' => 'Dapur']);
        $this->task('Renovasi dapur', ['project_id' => $project->id]);
        $this->task('Rekrut kasir');

        $this->app->setLocale('id');

        $text = MeetingReport::make()->text('all');

        $this->assertStringStartsWith("Progres Rapat — Daftar Tugas: Semua Proyek\n21 September 2026\nProgres keseluruhan: 0%", $text);
        $this->assertStringContainsString("===== Dapur (0%) =====\nBELUM SELESAI (1)\n[ ] Renovasi dapur (Kerja, Prioritas Sedang)", $text);
        $this->assertStringContainsString('===== Tanpa Proyek (0%) =====', $text);
    }

    public function test_tautan_kirim_whatsapp_membawa_pesannya(): void
    {
        $this->task('Rekrut kasir');

        $url = MeetingReport::make()->whatsappUrl('all');

        $this->assertStringStartsWith('https://wa.me/?text=', $url);
        $this->assertStringContainsString(rawurlencode('Rekrut kasir'), $url);
    }

    public function test_tugas_arsip_tidak_ikut_dibagikan(): void
    {
        $this->task('Lama', ['archived' => true, 'completed' => true]);

        $this->assertStringNotContainsString('Lama', MeetingReport::make()->whatsapp('all'));
    }

    public function test_pdf_dibuka_di_tab_baru(): void
    {
        $task = $this->task('Renovasi dapur');
        $this->task('Cari tukang', ['parent_id' => $task->id]);

        $response = $this->actingAs($this->superAdmin())
            ->get(route('filament.admin.pdf.meeting', ['scope' => 'all']))
            ->assertOk();

        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
