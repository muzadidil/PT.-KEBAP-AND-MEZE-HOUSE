<?php

namespace App\Filament\Admin\Resources\Zeytin\MokaItemSales\Pages;

use App\Filament\Admin\Actions\ExcelActions;
use App\Filament\Admin\Resources\Zeytin\MokaItemSales\MokaItemSaleResource;
use App\Support\Excel\ImportReport;
use App\Support\Moka\ItemSalesImport;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ManageMokaItemSales extends ManageRecords
{
    protected static string $resource = MokaItemSaleResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('moka.page.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [$this->importAction()];
    }

    /**
     * Berkas Moka tidak memuat tanggalnya sendiri, jadi periode ditanyakan.
     * Begitu berkasnya dipilih, kotak tanggal diisi dari namanya
     * ("Report Item Sales - 01-10-2026 - 31-10-2026 - ….xlsx") — tetap bisa
     * diubah, dan dibiarkan kosong kalau namanya tidak terbaca.
     */
    protected function importAction(): Action
    {
        return Action::make('mokaImport')
            ->label(__('moka.action.import'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading(__('moka.import.heading'))
            ->modalDescription(__('moka.import.description'))
            ->modalSubmitActionLabel(__('excel.action.import'))
            ->schema([
                FileUpload::make('file')
                    ->label(__('moka.import.file'))
                    // Berkas xlsx dari Moka menaruh entri `_rels/.rels` di urutan
                    // pertama zip-nya, sehingga pendeteksi jenis berkas server
                    // (libmagic) menyebutnya application/octet-stream dan aturan
                    // jenis-berkas menolaknya. Ekstensinya yang dijaga di sini;
                    // isinya diperiksa ketat oleh ItemSalesImport, dan berkas
                    // yang bukan spreadsheet ditolak di sana dengan pesan jelas.
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'application/octet-stream',
                    ])
                    ->rules(['extensions:xlsx,xls'])
                    // Tidak disimpan di server: yang dibaca berkas sementara
                    // milik unggahan, yang dibuang sendiri sesudahnya.
                    ->storeFiles(false)
                    ->maxSize(20480)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        $file = Arr::first(Arr::wrap($state));

                        if (! $file instanceof TemporaryUploadedFile) {
                            return;
                        }

                        if ($period = ItemSalesImport::periodFromFilename($file->getClientOriginalName())) {
                            $set('from', $period[0]->toDateString());
                            $set('until', $period[1]->toDateString());
                        }
                    }),

                DatePicker::make('from')
                    ->label(__('report.from'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->required(),

                DatePicker::make('until')
                    ->label(__('report.to'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->afterOrEqual('from')
                    ->required()
                    ->helperText(__('moka.import.period_hint')),
            ])
            ->action(function (array $data) {
                /** @var TemporaryUploadedFile $file */
                $file = $data['file'];

                try {
                    $report = (new ItemSalesImport(Carbon::parse($data['from']), Carbon::parse($data['until'])))
                        ->import($file->getRealPath());
                } catch (Throwable $e) {
                    report($e);

                    $report = ImportReport::failed([__('excel.error.failed', ['message' => $e->getMessage()])]);
                }

                ExcelActions::notify($report);
            });
    }
}
