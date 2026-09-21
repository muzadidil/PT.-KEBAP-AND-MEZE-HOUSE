<?php

namespace App\Filament\Admin\Resources\Expenses;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Expense;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Seluruh pengeluaran usaha, satu tabel.
 *
 * Laporan Cash Expenses, Online Transfers, Salary, Tax, dan Owner Expenses
 * semuanya tampilan tersaring dari sini, bukan tabel sendiri-sendiri. Satu
 * pengeluaran karena itu tidak pernah tercatat di dua tempat, dan tidak ada
 * dua laporan yang bisa menghasilkan angka berbeda untuk hal yang sama.
 */
class ExpenseResource extends Resource
{
    use ForAdmin;

    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingDown;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.expenses');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.expenses');
    }

    public static function getModelLabel(): string
    {
        return __('nav.expenses');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.expenses');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('spent_on')
                ->label(__('field.date'))
                ->native(false)
                ->default(now())
                ->required(),

            TextInput::make('amount')
                ->label(__('field.amount'))
                ->prefix('Rp')
                ->numeric()
                ->minValue(0)
                ->required(),

            TextInput::make('description')
                ->label(__('field.description'))
                ->required()
                ->maxLength(160)
                ->columnSpanFull(),

            Select::make('category')
                ->label(__('field.category'))
                ->options(ExpenseCategory::class)
                ->default(ExpenseCategory::Operational)
                ->required()
                ->live(),

            Select::make('method')
                ->label(__('field.method'))
                ->options(PaymentMethod::class)
                ->default(PaymentMethod::Cash)
                ->required(),

            Select::make('supplier_id')
                ->label(__('field.supplier'))
                ->relationship('supplier', 'name')
                ->searchable()
                ->preload()
                // Hanya relevan untuk belanja ke pemasok.
                ->visible(fn ($get) => $get('category') === ExpenseCategory::Supplier->value
                    || $get('category') === ExpenseCategory::Supplier),

            Select::make('paid_by_owner_id')
                ->label(__('field.paid_by_owner'))
                ->helperText(__('field.help.paid_by_owner'))
                ->relationship('paidByOwner', 'name')
                ->preload()
                ->live(),

            Toggle::make('is_paid')
                ->label(__('field.is_paid'))
                ->helperText(__('field.help.is_paid'))
                ->default(true)
                // Yang ditalangi pemilik pasti sudah dibayar; menawarkan
                // pilihan di sini hanya akan menyesatkan. Aturan yang sama
                // dipaksakan lagi di model, jadi tidak bisa ditembus.
                ->disabled(fn ($get) => filled($get('paid_by_owner_id')))
                ->dehydrated(),

            DatePicker::make('due_on')
                ->label(__('field.due_on'))
                ->native(false)
                ->visible(fn ($get) => ! $get('is_paid')),

            Textarea::make('note')
                ->label(__('field.note'))
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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

                TextColumn::make('category')
                    ->label(__('field.category'))
                    ->badge(),

                TextColumn::make('method')
                    ->label(__('field.method'))
                    ->badge(),

                TextColumn::make('paidByOwner.name')
                    ->label(__('field.paid_by_owner'))
                    ->placeholder('—')
                    ->badge()
                    ->color('warning'),

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
                SelectFilter::make('category')
                    ->label(__('field.category'))
                    ->options(ExpenseCategory::class)
                    ->multiple(),

                SelectFilter::make('method')
                    ->label(__('field.method'))
                    ->options(PaymentMethod::class)
                    ->multiple(),

                SelectFilter::make('paid_by_owner_id')
                    ->label(__('field.paid_by_owner'))
                    ->relationship('paidByOwner', 'name')
                    ->preload(),

                SelectFilter::make('supplier_id')
                    ->label(__('field.supplier'))
                    ->relationship('supplier', 'name')
                    ->preload(),

                TernaryFilter::make('is_paid')
                    ->label(__('field.is_paid')),

                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('report.from'))->native(false),
                        DatePicker::make('until')->label(__('report.to'))->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('spent_on', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('spent_on', '<=', $date))),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenses::route('/'),
        ];
    }
}
