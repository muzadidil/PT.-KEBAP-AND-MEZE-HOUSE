<?php

namespace App\Filament\Admin\Actions;

use App\Support\Excel\ImportReport;
use App\Support\Moka\ItemSalesImport;
use App\Support\Moka\MokaImport;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Tombol "Impor dari Moka": satu tombol untuk semua laporan Moka, dipasang
 * di Penjualan per Menu dan di Pemasukan Harian. Jenis laporannya dikenali
 * dari isi berkas, bukan dari tombol mana yang ditekan; lihat MokaImport.
 *
 * Berkas Moka tidak memuat tanggalnya sendiri, jadi periode ditanyakan.
 * Begitu berkasnya dipilih, kotak Dari/Sampai diisi dari namanya ("Report
 * Item Sales - 01-10-2026 - 31-10-2026 - ….xlsx") — tetap bisa diubah, dan
 * dibiarkan kosong kalau namanya tidak terbaca.
 */
class MokaImportAction
{
    public static function make(string $name = 'mokaImport', string $color = 'primary'): Action
    {
        return Action::make($name)
            ->label(__('moka.action.import'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color($color)
            ->modalHeading(__('moka.import.heading'))
            ->modalDescription(__('moka.import.description'))
            ->modalSubmitActionLabel(__('excel.action.import'))
            ->schema([
                FileUpload::make('file')
                    ->label(__('moka.import.file'))
                    // Berkas xlsx dari Moka menaruh entri `_rels/.rels` di urutan
                    // pertama zip-nya, sehingga pendeteksi jenis berkas server
                    // (libmagic) menyebutnya application/octet-stream dan aturan
                    // jenis-berkas menolaknya; peramban juga memberi CSV tipe
                    // yang berbeda-beda. Ekstensinya yang dijaga di sini; isinya
                    // diperiksa oleh MokaImport dan pengimpor di bawahnya.
                    ->acceptedFileTypes([
                        'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/octet-stream',
                    ])
                    ->rules(['extensions:csv,txt,xlsx,xls'])
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
                    ->maxDate(now()),

                DatePicker::make('until')
                    ->label(__('report.to'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->afterOrEqual('from')
                    ->helperText(__('moka.import.period_hint')),
            ])
            ->action(function (array $data) {
                /** @var TemporaryUploadedFile $file */
                $file = $data['file'];

                try {
                    // Kotak yang diisi (atau dikoreksi) pengguna didahulukan;
                    // kalau kosong, periode dari nama berkas bawaan Moka.
                    $period = filled($data['from'] ?? null) && filled($data['until'] ?? null)
                        ? [Carbon::parse($data['from'])->startOfDay(), Carbon::parse($data['until'])->startOfDay()]
                        : ItemSalesImport::periodFromFilename($file->getClientOriginalName());

                    $report = (new MokaImport($period))->import($file->getRealPath());
                } catch (Throwable $e) {
                    report($e);

                    $report = ImportReport::failed([__('excel.error.failed', ['message' => $e->getMessage()])]);
                }

                ExcelActions::notify($report);
            });
    }
}
