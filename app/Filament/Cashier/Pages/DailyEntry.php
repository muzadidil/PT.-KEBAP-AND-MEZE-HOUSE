<?php

namespace App\Filament\Cashier\Pages;

use App\Enums\SalesChannel;
use App\Enums\SaleSource;
use App\Models\Sale;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Rekap harian: untuk hari yang tidak memakai mesin kasir, kasir cukup
 * mengisi total pemasukan per channel.
 *
 * Isiannya disimpan ke tabel `sales` yang sama dengan transaksi kasir, satu
 * baris per channel, hanya dengan source `quick` dan tanpa rincian item.
 * Dengan begitu seluruh laporan tetap membaca satu sumber, dan tidak ada
 * angka yang perlu dijumlahkan dari dua tempat.
 *
 * Menyimpan tanggal yang sama mengganti isian sebelumnya, bukan
 * menambahkannya — kasir yang ragu boleh menyimpan ulang tanpa takut
 * angkanya terhitung dua kali.
 */
class DailyEntry extends Page
{
    protected static ?int $navigationSort = -1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected string $view = 'filament.cashier.pages.daily-entry';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('nav.daily_entry');
    }

    public function getTitle(): string
    {
        return __('pos.daily_title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'date' => Carbon::today()->toDateString(),
            ...$this->existingFor(Carbon::today()),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description(__('pos.daily_intro'))
                ->schema([
                    DatePicker::make('date')
                        ->label(__('pos.daily_date'))
                        ->native(false)
                        ->required()
                        // Mengubah tanggal langsung memuat isian tanggal itu,
                        // jadi yang terlihat selalu keadaan tanggal terpilih.
                        ->live()
                        ->afterStateUpdated(function (?string $state) {
                            if (blank($state)) {
                                return;
                            }

                            foreach ($this->existingFor(Carbon::parse($state)) as $key => $value) {
                                $this->data[$key] = $value;
                            }
                        }),

                    ...array_map(
                        fn (SalesChannel $channel) => TextInput::make($channel->value)
                            ->label($channel->getLabel())
                            ->prefix('Rp')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        SalesChannel::cases(),
                    ),

                    Textarea::make('note')
                        ->label(__('field.note'))
                        ->rows(2),
                ])
                ->columns(2),
        ]);
    }

    /**
     * Isi halaman: formulirnya sendiri, dengan tombol simpan menempel di
     * kakinya. Kirim formulir memanggil save().
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('filament-actions::create.single.label'))
                            ->submit('save'),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $date = Carbon::parse($state['date'])->startOfDay();

        $amounts = [];

        foreach (SalesChannel::cases() as $channel) {
            $amounts[$channel->value] = Money::parse($state[$channel->value] ?? 0);
        }

        DB::transaction(function () use ($date, $amounts, $state) {
            // Ganti, bukan tambah: hapus dulu rekap tanggal ini.
            Sale::where('sold_on', $date->toDateString())
                ->where('source', SaleSource::Quick)
                ->delete();

            foreach ($amounts as $channel => $amount) {
                if ($amount <= 0) {
                    continue;
                }

                Sale::create([
                    'code' => null,
                    'sold_on' => $date,
                    'channel' => $channel,
                    'source' => SaleSource::Quick,
                    'subtotal' => $amount,
                    'discount' => 0,
                    'total' => $amount,
                    'paid' => $amount,
                    'change' => 0,
                    'note' => filled($state['note'] ?? null) ? $state['note'] : null,
                    'user_id' => Auth::id(),
                ]);
            }
        });

        Notification::make()
            ->success()
            ->title(__('pos.daily_saved'))
            ->body(Money::format(array_sum($amounts)))
            ->send();
    }

    /**
     * Rekap yang sudah tersimpan untuk satu tanggal, supaya membuka tanggal
     * yang sudah diisi menampilkan angkanya, bukan kolom kosong.
     *
     * @return array<string, mixed>
     */
    protected function existingFor(Carbon $date): array
    {
        $rows = Sale::where('sold_on', $date->toDateString())
            ->where('source', SaleSource::Quick)
            ->get();

        $values = [];

        foreach (SalesChannel::cases() as $channel) {
            $values[$channel->value] = (int) $rows->where('channel', $channel)->sum('total');
        }

        $values['note'] = $rows->first()?->note;

        return $values;
    }

    /** Transaksi kasir di tanggal terpilih, untuk peringatan di halaman. */
    public function registerSalesOnSelectedDate(): array
    {
        $date = $this->data['date'] ?? null;

        if (blank($date)) {
            return ['count' => 0, 'total' => 0];
        }

        $sales = Sale::where('sold_on', Carbon::parse($date)->toDateString())
            ->where('source', SaleSource::Pos)
            ->get();

        return ['count' => $sales->count(), 'total' => (int) $sales->sum('total')];
    }
}
