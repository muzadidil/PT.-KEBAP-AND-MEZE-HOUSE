<?php

namespace App\Filament\Admin\Actions;

use App\Support\Excel\ImportReport;
use App\Support\Zeytin\PosSalesImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/** Tombol "Impor penjualan kasir" di Pemasukan Harian; lihat PosSalesImporter. */
class PosSalesAction
{
    public static function make(): Action
    {
        return Action::make('posSalesImport')
            ->label(__('excel.pos.action'))
            ->icon(Heroicon::OutlinedReceiptPercent)
            ->color('gray')
            ->modalHeading(__('excel.pos.heading'))
            ->modalDescription(__('excel.pos.description'))
            ->modalSubmitActionLabel(__('excel.action.import'))
            ->schema([
                FileUpload::make('file')
                    ->label(__('excel.pos.file'))
                    // Peramban sering memberi CSV tipe berbeda-beda; yang
                    // dibatasi adalah ekstensinya.
                    ->acceptedFileTypes([
                        'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->storeFiles(false)
                    ->maxSize(20480)
                    ->required(),
            ])
            ->action(function (array $data) {
                /** @var TemporaryUploadedFile $file */
                $file = $data['file'];

                try {
                    $report = (new PosSalesImporter)->import($file->getRealPath());
                } catch (Throwable $e) {
                    report($e);

                    $report = ImportReport::failed([__('excel.error.failed', ['message' => $e->getMessage()])]);
                }

                ExcelActions::notify($report);
            });
    }
}
