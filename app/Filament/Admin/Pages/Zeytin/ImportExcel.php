<?php

namespace App\Filament\Admin\Pages\Zeytin;

use App\Filament\Admin\Concerns\ForAdmin;
use App\Support\Zeytin\Workbook\Importer;
use App\Support\Zeytin\Workbook\TemplateBuilder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

/**
 * Mengimpor berkas Excel bulanan klien.
 *
 * Halaman ini sengaja tidak menjanjikan bahwa impornya berhasil seluruhnya.
 * Ia menampilkan satu baris hasil per sheet: berapa baris masuk, rentang
 * tanggal yang benar-benar terbaca, dan berapa baris lama yang diganti.
 * Sheet yang bentuknya tidak dikenali dilaporkan apa adanya, bukan dilewati
 * diam-diam — galat diam di pengimpor adalah cara tercepat merusak laporan
 * tanpa ada yang sadar.
 */
class ImportExcel extends Page
{
    use ForAdmin;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.admin.pages.zeytin.import-excel';

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('zeytin.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('zeytin.nav.import');
    }

    public function getTitle(): string
    {
        return __('zeytin.import.title');
    }

    public function mount(): void
    {
        $this->form->fill(['payroll_month' => now()->startOfMonth()]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('zeytin.import.title'))
                ->description(__('zeytin.import.intro'))
                ->schema([
                    FileUpload::make('file')
                        ->label(__('zeytin.import.pick'))
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        // Berkasnya tidak disimpan di server sama sekali: yang
                        // dibaca berkas sementara milik unggahan, lalu dibuang
                        // sendiri. Isinya nomor rekening pemasok dan gaji tiap
                        // karyawan — tidak ada gunanya menumpuk salinannya.
                        ->storeFiles(false)
                        ->maxSize(20480)
                        ->required()
                        ->columnSpanFull(),

                    DatePicker::make('payroll_month')
                        ->label(__('zeytin.import.payroll_month'))
                        ->helperText(__('zeytin.import.payroll_month_hint'))
                        ->native(false)
                        ->displayFormat('M Y')
                        // Tombol template ikut membaca bulan ini.
                        ->live()
                        ->required()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('import')
                ->footer([
                    Actions::make([
                        Action::make('import')
                            ->label(__('zeytin.import.run'))
                            ->icon(Heroicon::OutlinedArrowUpTray)
                            ->submit('import'),

                        Action::make('template')
                            ->label(fn () => __('zeytin.template.download_month', ['month' => $this->templateMonth()->translatedFormat('F Y')]))
                            ->color('gray')
                            ->icon(Heroicon::OutlinedArrowDownTray)
                            ->action('downloadTemplate'),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function import(): void
    {
        $state = $this->form->getState();

        /** @var TemporaryUploadedFile $file */
        $file = $state['file'];
        $month = Carbon::parse($state['payroll_month'])->startOfMonth();

        try {
            /*
             * Seluruh berkas masuk dalam satu transaksi. Kalau sheet keempat
             * gagal di tengah jalan, tiga sheet pertama ikut dibatalkan —
             * separuh bulan yang terimpor lebih sulit dibereskan daripada
             * tidak ada yang terimpor sama sekali, karena tidak ada yang tahu
             * separuh mana.
             */
            $this->results = DB::transaction(
                fn () => (new Importer($month))->import($file->getRealPath()),
            );
        } catch (Throwable $e) {
            $this->results = [];

            Notification::make()
                ->danger()
                ->title(__('zeytin.import.error.failed', ['message' => $e->getMessage()]))
                ->send();

            return;
        }

        $imported = array_sum(array_column($this->results, 'imported'));

        Notification::make()
            ->status($imported ? 'success' : 'warning')
            ->title($imported
                ? __('zeytin.import.done').' — '.$imported.' '.mb_strtolower(__('zeytin.import.rows'))
                : __('zeytin.import.nothing'))
            ->send();
    }

    /**
     * Template untuk bulan yang dipilih di formulir.
     *
     * Dibangun saat tombolnya ditekan, dari daftar kolom yang sama dipakai
     * pembacanya dan dari daftar pemasok/barang yang sedang berlaku — bukan
     * berkas contoh yang disimpan di repo. Berkas contoh yang disimpan akan
     * diam-diam ketinggalan zaman begitu satu kolom atau satu pemasok
     * berubah.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $builder = new TemplateBuilder($this->templateMonth());
        $book = $builder->build();

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $builder->filename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function templateMonth(): Carbon
    {
        $month = $this->data['payroll_month'] ?? null;

        return (filled($month) ? Carbon::parse($month) : now())->startOfMonth();
    }
}
