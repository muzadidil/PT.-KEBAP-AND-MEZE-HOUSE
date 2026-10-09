<?php

namespace App\Filament\Admin\Actions;

use App\Support\Excel\ImportReport;
use App\Support\Zeytin\TelegramSalesImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/** Tombol "Tarik dari Telegram" di Pemasukan Harian; lihat TelegramSalesImporter. */
class TelegramSalesAction
{
    public static function make(): Action
    {
        return Action::make('telegramSalesImport')
            ->label(__('excel.telegram.action'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->modalHeading(__('excel.telegram.heading'))
            ->modalDescription(__('excel.telegram.description'))
            ->modalSubmitActionLabel(__('excel.action.import'))
            ->schema([
                Textarea::make('text')
                    ->label(__('excel.telegram.text'))
                    ->placeholder("Penjualan 26 Sep 2026\nPetty Cash: 0\nCash: 1.545.390\nBNI: 9.320.850\nGrab Food: 207.900\nGo Food: 0\nGo Pay: 0")
                    ->rows(9),
                FileUpload::make('file')
                    ->label(__('excel.telegram.file'))
                    ->helperText(__('excel.telegram.file_help'))
                    ->acceptedFileTypes(['application/json', 'text/plain', 'text/json'])
                    ->storeFiles(false)
                    ->maxSize(20480),
            ])
            ->action(function (array $data) {
                $input = trim((string) ($data['text'] ?? ''));

                if (($data['file'] ?? null) instanceof TemporaryUploadedFile) {
                    $input = trim($input."\n".file_get_contents($data['file']->getRealPath()));
                }

                if ($input === '') {
                    ExcelActions::notify(ImportReport::failed([__('excel.telegram.empty')]));

                    return;
                }

                try {
                    $report = (new TelegramSalesImporter)->import($input);
                } catch (Throwable $e) {
                    report($e);

                    $report = ImportReport::failed([__('excel.error.failed', ['message' => $e->getMessage()])]);
                }

                ExcelActions::notify($report);
            });
    }
}
