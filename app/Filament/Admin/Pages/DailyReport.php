<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\ForBackoffice;
use App\Models\DailyNote;
use App\Models\DailyReportOption;
use App\Support\DailyReport\DailyReportText;
use App\Support\DailyReport\NoteBoard;
use App\Support\DailyReport\NoteTemplate;
use App\Support\Money;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Daily Report — catatan harian per bagian, meniru contoh laporan tim yang
 * dibagikan ke grup WhatsApp: Operation, Staff Issue, Google Reviews, dan
 * seterusnya. Tiap bagian boleh punya sub-catatan bertingkat tanpa batas.
 *
 * Isian tiap catatan mengikuti jenisnya — teks dengan nominal, satu pilihan
 * kondisi, angka, rating bintang, atau status; lihat DailyNote. Pilihan
 * kondisi dan status diambil dari master yang dikelola di halaman ini juga.
 *
 * Bukan Progres Rapat: tidak ada centang selesai/belum, karena isinya
 * catatan, bukan tugas. Ditaruh persis di bawahnya di menu. Dipakai Super
 * Admin dan Admin — lihat App\Filament\Admin\Concerns\ForBackoffice.
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
    public array $draft = [];

    /** @var array<int, array<string, string>> isian sub-catatan baru, per induknya */
    public array $subDraft = [];

    public ?int $editingId = null;

    /** @var array<string, string> */
    public array $edit = [];

    /** @var array<string, array<string, string>> isian pilihan master baru, per grup */
    public array $newOption = [];

    public ?int $editingOptionId = null;

    /** @var array<string, string> */
    public array $optionEdit = [];

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
        $this->resetDraft();
        $this->resetNewOptions();
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

    /** @return Collection<string, Collection<int, DailyReportOption>> master pilihan per grup */
    #[Computed]
    public function options(): Collection
    {
        return collect(DailyReportOption::GROUPS)->mapWithKeys(fn (string $group) => [
            $group => DailyReportOption::query()->inGroup($group)->get(),
        ]);
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
        $data = $this->validate([
            'draft.text' => ['required', 'string', 'max:500'],
            'draft.kind' => ['required', Rule::in(DailyNote::SECTION_KINDS)],
            'draft.icon' => ['nullable', 'string', 'max:16'],
            'draft.nominal' => ['nullable', 'string', 'max:20'],
        ])['draft'];

        DailyNote::create([
            'date' => $this->date,
            'text' => trim($data['text']),
            'kind' => $data['kind'],
            'icon' => trim((string) ($data['icon'] ?? '')) ?: null,
            'nominal' => $data['kind'] === 'text' ? $this->parseNominal($data['nominal'] ?? null) : null,
        ]);

        $this->resetDraft();
    }

    public function addSub(int $parentId): void
    {
        $parent = DailyNote::findOrFail($parentId);
        $data = $this->subDraft[$parentId] ?? [];
        $text = trim((string) ($data['text'] ?? ''));

        if ($text === '') {
            return;
        }

        $kind = $parent->childKind();

        DailyNote::create([
            'date' => $this->date,
            'parent_id' => $parent->id,
            'text' => mb_substr($text, 0, 500),
            'kind' => $kind,
            'nominal' => $kind === 'text' ? $this->parseNominal($data['nominal'] ?? null) : null,
            'value' => $kind === 'number' ? $this->parseValue('number', $data['value'] ?? null) : null,
            'option_id' => $kind === 'status' ? $this->validOption('status', $data['option_id'] ?? null) : null,
        ]);

        unset($this->subDraft[$parentId]);
    }

    /** Pilihan kondisi bagian, atau status poin — langsung tersimpan begitu dipilih. */
    public function setOption(int $id, mixed $optionId): void
    {
        $note = DailyNote::findOrFail($id);

        if ($group = $note->optionGroup()) {
            $note->update(['option_id' => $this->validOption($group, $optionId)]);
        }
    }

    /** Angka atau rating — langsung tersimpan begitu isiannya ditinggalkan. */
    public function setValue(int $id, mixed $value): void
    {
        $note = DailyNote::findOrFail($id);

        if (in_array($note->kind, ['number', 'rating'], true)) {
            $note->update(['value' => $this->parseValue($note->kind, $value)]);
        }
    }

    public function startEdit(int $id): void
    {
        $note = DailyNote::findOrFail($id);

        $this->editingId = $note->id;
        $this->edit = [
            'text' => $note->text,
            'icon' => (string) ($note->icon ?? ''),
            'nominal' => $note->nominal !== null ? (string) $note->nominal : '',
        ];
    }

    public function saveEdit(): void
    {
        $note = DailyNote::findOrFail($this->editingId);

        $data = $this->validate([
            'edit.text' => ['required', 'string', 'max:500'],
            'edit.icon' => ['nullable', 'string', 'max:16'],
            'edit.nominal' => ['nullable', 'string', 'max:20'],
        ])['edit'];

        $note->update([
            'text' => trim($data['text']),
            // Ikon hanya untuk judul bagian; nominal hanya untuk catatan biasa.
            'icon' => $note->parent_id ? $note->icon : (trim((string) ($data['icon'] ?? '')) ?: null),
            'nominal' => $note->kind === 'text' ? $this->parseNominal($data['nominal'] ?? null) : $note->nominal,
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

    /* ----------------------------------------------------- master pilihan */

    public function addOption(string $group): void
    {
        abort_unless(in_array($group, DailyReportOption::GROUPS, true), 404);

        $data = $this->validate([
            "newOption.{$group}.label" => ['required', 'string', 'max:60'],
            "newOption.{$group}.icon" => ['nullable', 'string', 'max:16'],
        ])['newOption'][$group];

        DailyReportOption::create([
            'group' => $group,
            'label' => trim($data['label']),
            'icon' => trim((string) ($data['icon'] ?? '')) ?: null,
            'sort_order' => (int) DailyReportOption::where('group', $group)->max('sort_order') + 1,
        ]);

        $this->resetNewOptions();
    }

    public function startOptionEdit(int $id): void
    {
        $option = DailyReportOption::findOrFail($id);

        $this->editingOptionId = $option->id;
        $this->optionEdit = ['label' => $option->label, 'icon' => (string) ($option->icon ?? '')];
    }

    public function saveOption(): void
    {
        $data = $this->validate([
            'optionEdit.label' => ['required', 'string', 'max:60'],
            'optionEdit.icon' => ['nullable', 'string', 'max:16'],
        ])['optionEdit'];

        DailyReportOption::whereKey($this->editingOptionId)->update([
            'label' => trim($data['label']),
            'icon' => trim((string) ($data['icon'] ?? '')) ?: null,
        ]);

        $this->cancelOptionEdit();
    }

    public function cancelOptionEdit(): void
    {
        $this->editingOptionId = null;
        $this->optionEdit = [];
    }

    /** Catatan yang memakai pilihan ini tidak ikut terhapus — hanya jadi belum dipilih. */
    public function deleteOption(int $id): void
    {
        DailyReportOption::whereKey($id)->delete();
    }

    /* ---------------------------------------------------------- pembantu */

    protected function resetDraft(): void
    {
        $this->draft = ['text' => '', 'kind' => 'text', 'icon' => '', 'nominal' => ''];
    }

    protected function resetNewOptions(): void
    {
        $this->newOption = collect(DailyReportOption::GROUPS)
            ->mapWithKeys(fn (string $group) => [$group => ['label' => '', 'icon' => '']])
            ->all();
    }

    /** Kosong berarti bukan catatan soal uang; "45.000" dan "Rp 45.000" sama-sama diterima. */
    protected function parseNominal(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return Money::parse($value);
    }

    /**
     * Rating menerima "4,9" maupun "4.9" dan dibatasi 0–5; angka biasa
     * bilangan bulat, "1.200" dibaca seribu dua ratus.
     */
    protected function parseValue(string $kind, mixed $value): ?float
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        if ($kind === 'rating') {
            $rating = (float) str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $raw));

            return round(min(max($rating, 0), DailyNote::MAX_RATING), 1);
        }

        return (float) Money::parse($raw);
    }

    /** Id pilihan yang benar-benar ada di grup itu, atau null. */
    protected function validOption(string $group, mixed $optionId): ?int
    {
        if (! is_numeric($optionId)) {
            return null;
        }

        return DailyReportOption::query()->where('group', $group)->whereKey((int) $optionId)->value('id');
    }
}
