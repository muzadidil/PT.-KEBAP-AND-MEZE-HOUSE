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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

        if (($search = trim((string) ($filters['search'] ?? ''))) !== '' && $this->searchColumns()) {
            $query->where(function (Builder $q) use ($search) {
                foreach ($this->searchColumns() as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
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
                    ->sortable(),

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
            ->emptyStateHeading(__('report.no_data'));
    }
}
