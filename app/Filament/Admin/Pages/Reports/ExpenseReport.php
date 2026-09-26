<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Support\Money;
use App\Support\Reports\ListExport;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Induk laporan pengeluaran berbentuk daftar: Pengeluaran Tunai, Transfer
 * Online, Pajak.
 *
 * Tiap laporan menyebut tabel sumbernya sendiri. Pengeluaran Tunai dan
 * Transfer Online membaca Belanja Tunai dan Transfer Pemasok di Pembukuan
 * Bulanan — baris yang sama yang dijumlahkan Buku Besar — jadi total di
 * kaki tabelnya sama dengan kartu Belanja tunai dan Transfer pemasok di sana
 * untuk rentang yang sama. Asal angkanya ditulis di atas tabel.
 */
abstract class ExpenseReport extends Page implements HasTable
{
    use ForAdmin;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected string $view = 'filament.admin.pages.reports.expenses';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.reports');
    }

    /** Baris yang dilaporkan, sebelum saringan periode. */
    abstract protected function baseQuery(): Builder;

    /** Kalimat asal angka, ditampilkan di atas tabel. */
    abstract public function source(): string;

    /** Kolom di antara tanggal dan jumlah. */
    abstract protected function detailColumns(): array;

    protected function dateColumn(): string
    {
        return 'date';
    }

    protected function amountColumn(): string
    {
        return 'total';
    }

    /** Kolom sesudah jumlah. */
    protected function trailingColumns(): array
    {
        return [];
    }

    /** Penyaring tambahan khas laporan ini, di samping saringan periode. */
    protected function extraFilters(): array
    {
        return [];
    }

    /* ----------------------------------------------------------- unduhan */

    /**
     * Kolom unduhan Excel dan PDF. Sengaja terpisah dari kolom tabel: di
     * layar pemasok ditulis kecil di bawah nama barang, sedangkan di berkas
     * ia harus jadi kolomnya sendiri supaya bisa disaring dan dijumlahkan.
     *
     * @return array<int, array{label: string, value: callable, money?: bool, date?: bool}>
     */
    public function exportColumns(): array
    {
        $date = $this->dateColumn();
        $amount = $this->amountColumn();

        return [
            ['label' => __('field.date'), 'value' => fn ($row) => $row->{$date}, 'date' => true],
            ...$this->exportDetails(),
            ['label' => __('field.amount'), 'value' => fn ($row) => (int) $row->{$amount}, 'money' => true],
            ...$this->exportTrailing(),
        ];
    }

    /** @return array<int, array{label: string, value: callable, money?: bool, date?: bool}> */
    abstract protected function exportDetails(): array;

    /** @return array<int, array{label: string, value: callable, money?: bool, date?: bool}> */
    protected function exportTrailing(): array
    {
        return [];
    }

    /**
     * Kolom yang dipakai penyaring tambahan, supaya unduhan bisa menerapkan
     * penyaring yang sama lewat alamat PDF-nya.
     *
     * @return array<int, string>
     */
    public function filterColumns(): array
    {
        return [];
    }

    /**
     * Kolom yang ikut dicari kotak pencarian; dipakai PDF supaya isinya sama
     * dengan yang sedang tampil.
     *
     * @return array<int, string>
     */
    public function searchColumns(): array
    {
        return [];
    }

    /**
     * Baris laporan untuk rentang, penyaring, dan pencarian yang diberikan —
     * dipakai PDF, yang dibuka di tab baru dan karena itu tidak bisa membaca
     * keadaan tabel di layar.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Model>
     */
    public function filteredQuery(array $filters): Builder
    {
        $date = $this->dateColumn();

        $query = $this->baseQuery()
            ->when($filters['from'] ?? null, fn ($q, $value) => $q->whereDate($date, '>=', $value))
            ->when($filters['to'] ?? null, fn ($q, $value) => $q->whereDate($date, '<=', $value));

        foreach ($this->filterColumns() as $column) {
            $value = $filters[$column] ?? null;

            if ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }

        if (($search = trim((string) ($filters['search'] ?? ''))) !== '') {
            $query->where(function (Builder $q) use ($search, $date) {
                foreach ($this->searchColumns() as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }

                // Tanggal dicari sama seperti di layar; lihat table().
                if ($day = static::searchDate($search)) {
                    $q->orWhereDate($date, $day);
                } elseif ($month = static::searchMonth($search)) {
                    $q->orWhere(fn (Builder $inner) => $inner
                        ->whereYear($date, $month->year)
                        ->whereMonth($date, $month->month));
                }
            });
        }

        return $query->orderByDesc($date)->orderByDesc('id');
    }

    /**
     * Rentang, penyaring, dan pencarian yang sedang aktif di layar.
     *
     * @return array<string, mixed>
     */
    public function currentFilters(): array
    {
        $period = $this->tableFilters['period'] ?? [];

        $filters = [
            'from' => $period['from'] ?? null,
            'to' => $period['until'] ?? null,
            'search' => $this->tableSearch ?: null,
        ];

        foreach ($this->filterColumns() as $column) {
            $filters[$column] = $this->tableFilters[$column]['value'] ?? null;
        }

        return array_filter($filters, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Pintasan rentang: sehari, sepekan, sebulan. Tanggalnya tetap bisa
     * diketik sendiri di isian di atas tabel — pintasan ini hanya mengisinya.
     */
    public function applyPeriod(string $preset): void
    {
        $today = Carbon::today();

        [$from, $until] = match ($preset) {
            'today' => [$today->copy(), $today->copy()],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'last_7' => [$today->copy()->subDays(6), $today->copy()],
            'last_30' => [$today->copy()->subDays(29), $today->copy()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()],
            // "Semua": rentangnya dikosongkan, bukan dipasang selebar-lebarnya.
            default => [null, null],
        };

        $this->tableFilters['period'] = [
            'from' => $from?->toDateString(),
            'until' => $until?->toDateString(),
        ];

        $this->updatedTableFilters();
    }

    /**
     * Tanggal dari kotak pencarian: "15/08/2026", "15-8-2026", "2026-08-15".
     * Hari ditulis lebih dulu, sama seperti yang tampil di kolom tanggal.
     */
    public static function searchDate(string $search): ?Carbon
    {
        $search = trim($search);

        foreach (['d/m/Y', 'd-m-Y', 'j/n/Y', 'j-n-Y', 'Y-m-d'] as $format) {
            if (Carbon::hasFormat($search, $format)) {
                return Carbon::createFromFormat('!'.$format, $search);
            }
        }

        return null;
    }

    /** Satu bulan penuh dari kotak pencarian: "08/2026" atau "2026-08". */
    public static function searchMonth(string $search): ?Carbon
    {
        $search = trim($search);

        foreach (['m/Y', 'n/Y', 'Y-m'] as $format) {
            if (Carbon::hasFormat($search, $format)) {
                return Carbon::createFromFormat('!'.$format, $search)->startOfMonth();
            }
        }

        return null;
    }
    public function pdfUrl(): string
    {
        return route('filament.admin.pdf.expenses', [
            'report' => static::reportKey(),
            ...$this->currentFilters(),
        ]);
    }

    /** Nama laporan di alamat PDF; lihat App\Http\Controllers\PdfController. */
    public static function reportKey(): string
    {
        return str(class_basename(static::class))->kebab()->toString();
    }

    /**
     * Unduhan Excel dibuat dari kueri tabel yang sedang tampil, jadi
     * penyaring, pencarian, dan urutan yang dipilih ikut apa adanya.
     */
    public function exportExcel(): StreamedResponse
    {
        $export = new ListExport(
            title: $this->getTitle(),
            source: $this->source(),
            rows: $this->getFilteredSortedTableQuery()->get(),
            columns: $this->exportColumns(),
            filters: $this->currentFilters(),
        );

        $book = $export->build();

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $export->filename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function table(Table $table): Table
    {
        $date = $this->dateColumn();

        return $table
            ->query(fn () => $this->baseQuery())
            ->defaultSort($date, 'desc')
            ->columns([
                TextColumn::make($date)
                    ->label(__('field.date'))
                    ->date('d/m/Y')
                    ->sortable()
                    // Mengetik tanggal di kotak pencarian menyaring ke hari itu;
                    // "08/2026" menyaring ke satu bulan. Yang bukan tanggal tidak
                    // boleh mencocoki semua baris, jadi dipadamkan dengan 1 = 0.
                    ->searchable(query: fn (Builder $query, string $search) => match (true) {
                        (bool) ($day = static::searchDate($search)) => $query->whereDate($date, $day),
                        (bool) ($month = static::searchMonth($search)) => $query
                            ->whereYear($date, $month->year)
                            ->whereMonth($date, $month->month),
                        default => $query->whereRaw('1 = 0'),
                    }),

                ...$this->detailColumns(),

                TextColumn::make($this->amountColumn())
                    ->label(__('field.amount'))
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label(__('report.total'))
                            ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ),

                ...$this->trailingColumns(),
            ])
            ->filters([
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('report.from'))->native(false),
                        DatePicker::make('until')->label(__('report.to'))->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $value) => $q->whereDate($date, '>=', $value))
                        ->when($data['until'] ?? null, fn ($q, $value) => $q->whereDate($date, '<=', $value)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = __('report.from').': '.$data['from'];
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = __('report.to').': '.$data['until'];
                        }

                        return $indicators;
                    }),

                ...$this->extraFilters(),
            ])
            // Rentang tanggalnya isian yang paling sering dipakai; di balik ikon
            // corong ia tidak terlihat, dan laporan dibaca apa adanya sebulan
            // penuh tanpa ada yang sadar rentangnya bisa diubah.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->searchPlaceholder(__('report.search_placeholder'))
            ->emptyStateHeading(__('report.no_data'));
    }
}
