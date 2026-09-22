<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\ForBackoffice;
use App\Models\DailyNote;
use App\Support\DailyReport\DailyReportText;
use App\Support\DailyReport\NoteBoard;
use App\Support\DailyReport\NoteTemplate;
use App\Support\Money;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Daily Report — catatan harian per bagian, meniru contoh laporan tim yang
 * dibagikan ke grup WhatsApp: Sales, Operation, Staff, dan seterusnya, tiap
 * bagian boleh punya sub-catatan bertingkat tanpa batas.
 *
 * Bukan Progres Rapat: tidak ada status selesai/belum, karena isinya
 * catatan, bukan tugas yang dikerjakan. Ditaruh persis di bawahnya di menu.
 * Dipakai Super Admin dan Admin, sama seperti Progres Rapat — lihat
 * App\Filament\Admin\Concerns\ForBackoffice.
 */
class DailyReport extends Page
{
    use ForBackoffice;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.admin.pages.daily-report';

    #[Url]
    public string $date = '';

    /** @var array<string, string> isian bagian baru */
    public array $draft = ['text' => '', 'nominal' => ''];

    /** @var array<int, array<string, string>> isian sub-catatan baru, per induknya */
    public array $subDraft = [];

    public ?int $editingId = null;

    /** @var array<string, string> */
    public array $edit = [];

    public static function getNavigationLabel(): string
    {
        return __('daily_report.nav');
    }

    public function getTitle(): string
    {
        return __('daily_report.nav');
    }

    public function getSubheading(): ?string
    {
        return __('daily_report.subtitle');
    }

    public function mount(): void
    {
        $this->date = $this->date !== '' ? $this->date : Carbon::today()->toDateString();
    }

    protected function carbon(): Carbon
    {
        return Carbon::parse($this->date)->startOfDay();
    }

    /* ------------------------------------------------------------- data */

    #[Computed]
    public function board(): NoteBoard
    {
        return NoteBoard::load($this->carbon());
    }

    #[Computed]
    public function report(): DailyReportText
    {
        return DailyReportText::make($this->carbon());
    }

    /* --------------------------------------------------------- navigasi */

    public function setDate(string $date): void
    {
        $this->date = $date;
        $this->cancelEdit();
    }

    public function goToday(): void
    {
        $this->setDate(Carbon::today()->toDateString());
    }

    public function shiftDay(int $days): void
    {
        $this->setDate($this->carbon()->addDays($days)->toDateString());
    }

    /* ------------------------------------------------------------ isian */

    /** Mengisi bagian standar; tidak melakukan apa-apa kalau sudah ada isinya. */
    public function fillTemplate(): void
    {
        NoteTemplate::apply($this->carbon());
    }

    public function addNote(): void
    {
        $data = $this->validate($this->rules('draft'))['draft'];

        DailyNote::create([
            'date' => $this->date,
            'text' => trim($data['text']),
            'nominal' => $this->parseNominal($data['nominal'] ?? null),
        ]);

        $this->draft = ['text' => '', 'nominal' => ''];
    }

    public function addSub(int $parentId): void
    {
        $data = $this->subDraft[$parentId] ?? [];
        $text = trim((string) ($data['text'] ?? ''));

        if ($text === '') {
            return;
        }

        DailyNote::create([
            'date' => $this->date,
            'parent_id' => $parentId,
            'text' => mb_substr($text, 0, 500),
            'nominal' => $this->parseNominal($data['nominal'] ?? null),
        ]);

        unset($this->subDraft[$parentId]);
    }

    public function startEdit(int $id): void
    {
        $note = DailyNote::findOrFail($id);

        $this->editingId = $note->id;
        $this->edit = [
            'text' => $note->text,
            'nominal' => $note->nominal !== null ? (string) $note->nominal : '',
        ];
    }

    public function saveEdit(): void
    {
        $data = $this->validate($this->rules('edit'))['edit'];

        DailyNote::whereKey($this->editingId)->update([
            'text' => trim($data['text']),
            'nominal' => $this->parseNominal($data['nominal'] ?? null),
        ]);

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->edit = [];
    }

    public function delete(int $id): void
    {
        DailyNote::whereKey($id)->delete();
    }

    /* ---------------------------------------------------------- pembantu */

    /** @return array<string, mixed> */
    protected function rules(string $key): array
    {
        return [
            "{$key}.text" => ['required', 'string', 'max:500'],
            "{$key}.nominal" => ['nullable', 'string', 'max:20'],
        ];
    }

    /** Kosong berarti bukan catatan soal uang; "45.000" dan "Rp 45.000" sama-sama diterima. */
    protected function parseNominal(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return Money::parse($value);
    }
}
