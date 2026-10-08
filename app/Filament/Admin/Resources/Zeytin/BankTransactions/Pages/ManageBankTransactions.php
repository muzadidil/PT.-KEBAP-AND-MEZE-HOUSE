<?php

namespace App\Filament\Admin\Resources\Zeytin\BankTransactions\Pages;

use App\Filament\Admin\Resources\Zeytin\BankTransactions\BankTransactionResource;
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
