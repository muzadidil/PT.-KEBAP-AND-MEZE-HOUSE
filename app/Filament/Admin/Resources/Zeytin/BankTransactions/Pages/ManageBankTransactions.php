<?php

namespace App\Filament\Admin\Resources\Zeytin\BankTransactions\Pages;

use App\Filament\Admin\Resources\Zeytin\BankTransactions\BankTransactionResource;
use App\Support\Bank\BankMailbox;
use App\Support\Bank\BankQueue;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

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
                ->action(function () {
                    try {
                        $result = BankMailbox::fetch();
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
