<?php

namespace App\Filament\Admin\Actions;

use App\Support\Excel\ExcelSource;
use App\Support\Excel\ImportReport;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Tombol "Unduh template" dan "Impor Excel" di tiap menu input.
 *
 * Halaman hanya menyebutkan sumbernya (`ExcelActions::make(Resource::excel())`);
 * seluruh perilakunya ada di sini, jadi kedua tombol berbunyi dan bekerja
 * sama di semua menu.
 */
class ExcelActions
{
    /** @return array<int, Action> */
    public static function make(ExcelSource $source): array
    {
        return [static::template($source), static::import($source)];
    }

    public static function template(ExcelSource $source): Action
    {
        $action = Action::make('excelTemplate')
            ->label(__('excel.action.template'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn (array $data = []) => static::download($source, static::month($data)));

        if ($source->templateNeedsMonth()) {
            $action
                ->modalHeading(__('excel.template.heading', ['title' => $source->title()]))
                ->modalDescription(__('excel.template.month_hint'))
                ->modalSubmitActionLabel(__('excel.action.download'))
                ->schema([static::monthField()]);
        }

        return $action;
    }

    public static function import(ExcelSource $source): Action
    {
        return Action::make('excelImport')
            ->label(__('excel.action.import'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->modalHeading(__('excel.import.heading', ['title' => $source->title()]))
            ->modalDescription(__('excel.import.description'))
            ->modalSubmitActionLabel(__('excel.action.import'))
            ->schema(array_values(array_filter([
                FileUpload::make('file')
                    ->label(__('excel.import.file'))
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    // Tidak disimpan di server: yang dibaca berkas sementara
                    // milik unggahan, yang dibuang sendiri sesudahnya.
                    ->storeFiles(false)
                    ->maxSize(20480)
                    ->required(),

                $source->importNeedsMonth() ? static::monthField() : null,
            ])))
            ->action(function (array $data) use ($source) {
                /** @var TemporaryUploadedFile $file */
                $file = $data['file'];

                try {
                    $report = $source->import($file->getRealPath(), static::month($data));
                } catch (Throwable $e) {
                    report($e);

                    $report = ImportReport::failed([__('excel.error.failed', ['message' => $e->getMessage()])]);
                }

                static::notify($report);
            });
    }

    public static function download(ExcelSource $source, ?Carbon $month = null): StreamedResponse
    {
        $book = $source->template($month);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $source->filename($month), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public static function notify(ImportReport $report): void
    {
        if (! $report->ok()) {
            // Tetap tampil sampai ditutup: daftarnya dibaca sambil membetulkan
            // berkasnya, bukan sekilas lalu hilang.
            Notification::make()
                ->danger()
                ->persistent()
                ->title(trans_choice('excel.result.failed', count($report->errors), ['count' => count($report->errors)]))
                ->body(static::lines([...array_slice($report->errors, 0, 10), ...(count($report->errors) > 10
                    ? [__('excel.result.more', ['count' => count($report->errors) - 10])]
                    : []), __('excel.result.nothing_saved')]))
                ->send();

            return;
        }

        Notification::make()
            ->status($report->changed() ? 'success' : 'warning')
            ->title($report->changed() ? __('excel.result.done') : __('excel.result.unchanged'))
            ->body(static::lines(array_filter([$report->summary() ?: __('excel.result.empty'), $report->note])))
            ->send();
    }

    /** @param  array<int, string>  $lines */
    protected static function lines(array $lines): HtmlString
    {
        return new HtmlString(implode('<br>', array_map('e', $lines)));
    }

    protected static function monthField(): DatePicker
    {
        return DatePicker::make('month')
            ->label(__('excel.month'))
            ->native(false)
            ->displayFormat('F Y')
            ->default(now()->startOfMonth())
            ->required();
    }

    /** @param  array<string, mixed>  $data */
    protected static function month(array $data): ?Carbon
    {
        return filled($data['month'] ?? null) ? Carbon::parse($data['month'])->startOfMonth() : null;
    }
}
