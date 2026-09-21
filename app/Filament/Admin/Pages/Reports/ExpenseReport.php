<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Support\Money;
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
