<?php

namespace App\Filament\Admin\Resources\Zeytin\BankTransactions\Pages;

use App\Filament\Admin\Resources\Zeytin\BankTransactions\BankTransactionResource;
use App\Support\Bank\BankMailbox;
use App\Support\Bank\BankQueue;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ManageBankTransactions extends ManageRecords
{
    protected static string $resource = BankTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fetch')
                ->label(__('bank.fetch.action'))
                ->icon(Heroicon::OutlinedEnvelopeOpen)
                ->color('gray')
                ->visible(fn () => filled(config('bank.imap.user')) && filled(config('bank.imap.password')))
                ->modalHeading(__('bank.fetch.heading'))
                ->modalDescription(__('bank.fetch.description'))
                ->modalSubmitActionLabel(__('bank.fetch.submit'))
                ->fillForm(fn () => ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()])
                ->schema([
                    DatePicker::make('from')
                        ->label(__('bank.fetch.from'))
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->maxDate(now())
                        ->required(),
                    DatePicker::make('to')
                        ->label(__('bank.fetch.to'))
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->maxDate(now())
                        ->afterOrEqual('from')
                        // Dari web, rentang panjang bisa melewati batas waktu server.
                        ->rule(fn ($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                            if ($get('from') && Carbon::parse($value)->diffInDays(Carbon::parse($get('from'))) > 92) {
                                $fail(__('bank.fetch.too_long'));
                            }
                        })
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $result = BankMailbox::fetch(null, null, Carbon::parse($data['from']), Carbon::parse($data['to']));
                    } catch (\Throwable $e) {
                        report($e);

                        Notification::make()->title(__('bank.fetch.failed'))->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    $lines = array_filter([
                        __('bank.fetch.summary', ['checked' => $result['checked'], 'added' => $result['added'], 'duplicate' => $result['duplicate']]),
                        $result['other_format'] ? __('bank.fetch.other_format', ['count' => $result['other_format']]) : null,
                        $result['skipped'] ? __('bank.fetch.skipped', ['count' => $result['skipped']]) : null,
                        $result['rejected'] ? __('bank.fetch.rejected', ['count' => $result['rejected']]) : null,
                        ...$result['problems'],
                    ]);

                    Notification::make()
                        ->title(__('bank.fetch.done'))
                        ->body(implode("\n", $lines))
                        ->status($result['added'] > 0 ? 'success' : 'warning')
                        ->persistent($result['rejected'] > 0 || $result['other_format'] > 0 || $result['problems'] !== [])
                        ->send();
                }),

            Action::make('paste')
                ->label(__('bank.paste.action'))
                ->icon(Heroicon::OutlinedClipboardDocument)
                ->modalHeading(__('bank.paste.heading'))
                ->modalDescription(__('bank.paste.description'))
                ->modalSubmitActionLabel(__('bank.paste.submit'))
                ->schema([
                    Textarea::make('text')
                        ->label(__('bank.paste.field'))
                        ->rows(12)
                        ->required()
                        ->maxLength(60000),
                ])
                ->action(function (array $data) {
                    $result = BankQueue::addFromText((string) $data['text']);

                    $lines = array_filter([
                        __('bank.paste.added', ['count' => $result['added']]),
                        $result['duplicate'] ? __('bank.paste.duplicate', ['count' => $result['duplicate']]) : null,
                        ...$result['problems'],
                    ]);

                    Notification::make()
                        ->title(__('bank.paste.done'))
                        ->body(implode("\n", $lines))
                        ->status($result['added'] > 0 ? 'success' : 'warning')
                        ->persistent($result['problems'] !== [])
                        ->send();
                }),
        ];
    }
}
