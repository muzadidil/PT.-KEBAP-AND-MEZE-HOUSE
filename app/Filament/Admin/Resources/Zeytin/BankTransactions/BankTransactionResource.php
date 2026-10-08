<?php

namespace App\Filament\Admin\Resources\Zeytin\BankTransactions;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Resources\Zeytin\BankTransactions\Pages\ManageBankTransactions;
use App\Models\BankTransaction;
use App\Support\Bank\BankQueue;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Transaksi Bank: notifikasi BNI yang menunggu keputusan. Email ditempel
 * (atau dibaca otomatis), lalu Admin mencatatnya sebagai Transfer Pemasok
 * atau mengabaikannya. Lihat App\Support\Bank\BankQueue.
 */
class BankTransactionResource extends Resource
{
    use ForAdmin;

    protected static ?string $model = BankTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?int $navigationSort = 35;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('zeytin.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('bank.nav');
    }

    public static function getModelLabel(): string
    {
        return __('bank.nav');
    }

    public static function getPluralModelLabel(): string
    {
        return __('bank.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = BankTransaction::query()->where('status', BankTransaction::PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('bank.col.when'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('beneficiary')
                    ->label(__('bank.col.to'))
                    ->description(fn (BankTransaction $record) => $record->remark)
                    ->searchable(['beneficiary', 'remark'])
                    ->wrap(),

                TextColumn::make('amount')
                    ->label(__('bank.col.amount'))
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('direction')
                    ->label(__('bank.col.direction'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('bank.direction.'.$state))
                    ->color(fn (string $state) => $state === 'out' ? 'warning' : 'info'),

                TextColumn::make('status')
                    ->label(__('bank.col.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('bank.status.'.$state))
                    ->color(fn (string $state) => match ($state) {
                        BankTransaction::RECORDED => 'success',
                        BankTransaction::IGNORED => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('reference')
                    ->label(__('bank.col.reference'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('bank.col.status'))
                    ->options([
                        BankTransaction::PENDING => __('bank.status.pending'),
                        BankTransaction::RECORDED => __('bank.status.recorded'),
                        BankTransaction::IGNORED => __('bank.status.ignored'),
                    ])
                    ->default(BankTransaction::PENDING),
            ])
            ->recordActions([
                Action::make('record')
                    ->label(__('bank.action.record'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('bank.action.record_heading'))
                    ->modalDescription(fn (BankTransaction $record) => __('bank.action.record_description', [
                        'amount' => Money::format($record->amount),
                        'to' => $record->beneficiary,
                    ]))
                    ->visible(fn (BankTransaction $record) => $record->status === BankTransaction::PENDING && $record->isOutgoing())
                    ->action(function (BankTransaction $record) {
                        BankQueue::record($record);

                        Notification::make()->title(__('bank.action.recorded'))->success()->send();
                    }),

                Action::make('ignore')
                    ->label(__('bank.action.ignore'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('gray')
                    ->visible(fn (BankTransaction $record) => $record->status === BankTransaction::PENDING)
                    ->action(fn (BankTransaction $record) => BankQueue::ignore($record)),

                DeleteAction::make()
                    ->visible(fn (BankTransaction $record) => $record->status !== BankTransaction::RECORDED),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBankTransactions::route('/'),
        ];
    }
}
