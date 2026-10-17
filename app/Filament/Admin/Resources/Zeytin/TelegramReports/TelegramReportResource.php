<?php

namespace App\Filament\Admin\Resources\Zeytin\TelegramReports;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Filament\Admin\Resources\Zeytin\TelegramReports\Pages\ManageTelegramReports;
use App\Models\TelegramReport;
use App\Support\Money;
use App\Support\Telegram\TelegramQueue;
use App\Support\Zeytin\Channels;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Antrean Telegram: laporan penjualan harian dari grup Telegram yang menunggu
 * konfirmasi. Admin memeriksa tanggal dan angkanya (boleh diubah), lalu
 * mencatatnya ke Pemasukan Harian atau menolaknya. Lihat App\Support\Telegram\TelegramQueue.
 */
class TelegramReportResource extends Resource
{
    use ForAdmin;

    protected static ?string $model = TelegramReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?int $navigationSort = 36;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('zeytin.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('telegram.nav');
    }

    public static function getModelLabel(): string
    {
        return __('telegram.nav');
    }

    public static function getPluralModelLabel(): string
    {
        return __('telegram.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = TelegramReport::query()->where('status', TelegramReport::PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('report_date', 'desc')
            ->columns([
                TextColumn::make('report_date')
                    ->label(__('telegram.col.date'))
                    ->date('D, d M Y')
                    ->description(fn (TelegramReport $record) => $record->status === TelegramReport::PENDING && $record->dateTaken()
                        ? __('telegram.date_taken')
                        : null)
                    ->sortable(),

                ...collect(Channels::all())->map(fn (array $channel) => TextColumn::make('amounts.'.$channel['key'])
                    ->label($channel['label'])
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: $channel['key'] === 'petty_cash'))->all(),

                TextColumn::make('total')
                    ->label(__('telegram.col.total'))
                    ->state(fn (TelegramReport $record) => Money::format($record->total()))
                    ->weight('bold')
                    ->alignEnd(),

                TextColumn::make('status')
                    ->label(__('telegram.col.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('telegram.status.'.$state))
                    ->color(fn (string $state) => match ($state) {
                        TelegramReport::RECORDED => 'success',
                        TelegramReport::IGNORED => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('sender')
                    ->label(__('telegram.col.sender'))
                    ->description(fn (TelegramReport $record) => $record->sent_at->timezone(config('telegram.timezone'))->format('d M Y H:i'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('telegram.col.status'))
                    ->options([
                        TelegramReport::PENDING => __('telegram.status.pending'),
                        TelegramReport::RECORDED => __('telegram.status.recorded'),
                        TelegramReport::IGNORED => __('telegram.status.ignored'),
                    ])
                    ->default(TelegramReport::PENDING),

                Filter::make('period')
                    ->label(__('telegram.filter.period'))
                    ->schema([
                        DatePicker::make('from')->label(__('telegram.filter.from'))->native(false)->displayFormat('d M Y'),
                        DatePicker::make('to')->label(__('telegram.filter.to'))->native(false)->displayFormat('d M Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('report_date', '>=', $from))
                        ->when($data['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('report_date', '<=', $to))),
            ])
            ->recordActions([
                Action::make('record')
                    ->label(__('telegram.action.record'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->modalHeading(__('telegram.action.record_heading'))
                    ->modalDescription(__('telegram.action.record_description'))
                    ->modalSubmitActionLabel(__('telegram.action.record_submit'))
                    ->visible(fn (TelegramReport $record) => $record->status === TelegramReport::PENDING)
                    ->fillForm(fn (TelegramReport $record) => [
                        'date' => $record->report_date->toDateString(),
                        ...collect(Channels::keys())->mapWithKeys(fn (string $key) => ['amt_'.$key => (int) ($record->amounts[$key] ?? 0)])->all(),
                    ])
                    ->schema([
                        DatePicker::make('date')
                            ->label(__('telegram.col.date'))
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->maxDate(now()->addDay())
                            ->required(),
                        ...collect(Channels::all())->map(fn (array $channel) => TextInput::make('amt_'.$channel['key'])
                            ->label($channel['label'])
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp')
                            ->required())->all(),
                    ])
                    ->action(function (TelegramReport $record, array $data, Action $action) {
                        $amounts = collect(Channels::keys())->mapWithKeys(fn (string $key) => [$key => (int) ($data['amt_'.$key] ?? 0)])->all();

                        if (! TelegramQueue::record($record, (string) $data['date'], $amounts)) {
                            Notification::make()->danger()->title(__('telegram.action.taken'))->body(__('telegram.action.taken_body'))->send();

                            $action->halt();

                            return;
                        }

                        Notification::make()->success()->title(__('telegram.action.recorded'))->send();

                        TelegramQueue::tell($record->refresh(), '✅ '.__('telegram.reply.recorded', [
                            'date' => $record->report_date->translatedFormat('j M Y'),
                            'total' => Money::format($record->total()),
                        ]));
                    }),

                Action::make('ignore')
                    ->label(__('telegram.action.ignore'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(__('telegram.action.ignore_heading'))
                    ->modalDescription(__('telegram.action.ignore_description'))
                    ->visible(fn (TelegramReport $record) => $record->status === TelegramReport::PENDING)
                    ->action(function (TelegramReport $record) {
                        TelegramQueue::ignore($record);

                        TelegramQueue::tell($record->refresh(), '🚫 '.__('telegram.reply.ignored', [
                            'date' => $record->report_date->translatedFormat('j M Y'),
                        ]));
                    }),

                DeleteAction::make()
                    ->visible(fn (TelegramReport $record) => $record->status !== TelegramReport::RECORDED),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTelegramReports::route('/'),
        ];
    }
}
