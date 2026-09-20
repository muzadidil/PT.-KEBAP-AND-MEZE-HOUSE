<?php

namespace App\Filament\Admin\Pages\Reports;

use App\Models\Expense;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Induk laporan pengeluaran: Cash Expenses, Online Transfers, Salary, Tax.
 *
 * Keempatnya adalah tabel `expenses` yang sama dengan penyaring berbeda,
 * bukan tabel tersendiri. Karena itu satu pengeluaran tidak pernah perlu
 * dicatat dua kali, dan total di halaman Expenses selalu cocok dengan
 * jumlah seluruh laporan turunannya.
 */
abstract class ExpenseReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected string $view = 'filament.admin.pages.reports.expenses';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.reports');
    }

    /** Penyaring yang membedakan satu laporan dari yang lain. */
    abstract protected function scopeQuery(Builder $query): Builder;

    /** Kolom tambahan khas laporan ini, disisipkan sebelum kolom jumlah. */
    protected function extraColumns(): array
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
        return $table
            ->query(fn () => $this->scopeQuery(Expense::query()->with('supplier', 'paidByOwner')))
            ->defaultSort('spent_on', 'desc')
            ->columns([
                TextColumn::make('spent_on')
                    ->label(__('field.date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('description')
                    ->label(__('field.description'))
                    ->description(fn (Expense $record) => $record->supplier?->name)
                    ->searchable()
                    ->wrap(),

                ...$this->extraColumns(),

                TextColumn::make('amount')
                    ->label(__('field.amount'))
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->alignEnd()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label(__('report.total'))
                            ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ),

                IconColumn::make('is_paid')
                    ->label(__('field.is_paid'))
                    ->boolean(),
            ])
            ->filters([
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('report.from'))->native(false),
                        DatePicker::make('until')->label(__('report.to'))->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('spent_on', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('spent_on', '<=', $date)))
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
