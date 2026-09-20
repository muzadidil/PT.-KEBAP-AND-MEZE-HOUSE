<?php

namespace App\Filament\Admin\Pages;

use App\Models\Setting;
use App\Support\Branding;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
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
use UnitEnum;

/**
 * Tampilan halaman masuk: logo, nama, tagline, dan slide latar.
 *
 * Gambarnya diunggah ke public/uploads/branding — bukan ke storage/ lalu
 * di-symlink — karena halaman masuk harus bisa menampilkannya kepada orang
 * yang belum masuk, dan shared hosting sering menolak symlink.
 *
 * Kalau daftar slide dibiarkan kosong, halaman masuk memakai tiga gambar
 * vektor bawaan di public/img. Jadi menghapus semua unggahan tidak pernah
 * menghasilkan halaman masuk yang kosong melompong.
 */
class Appearance extends Page
{
    protected static ?int $navigationSort = 60;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected string $view = 'filament.admin.pages.appearance';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.group.master');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.appearance');
    }

    public function getTitle(): string
    {
        return __('appearance.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'name' => Setting::get('brand.name'),
            'tagline' => Setting::get('brand.tagline'),
            'logo' => Setting::get('brand.logo'),
            'slides' => Setting::get('brand.slides', []),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('appearance.brand'))
                ->description(__('appearance.intro'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('appearance.name'))
                        ->helperText(__('appearance.name_help'))
                        ->placeholder(config('business.name'))
                        ->maxLength(120),

                    TextInput::make('tagline')
                        ->label(__('appearance.tagline'))
                        ->placeholder(__('auth.default_tagline'))
                        ->maxLength(120),

                    FileUpload::make('logo')
                        ->label(__('appearance.logo'))
                        ->helperText(__('appearance.logo_help'))
                        ->disk(Branding::DISK)
                        ->directory('logo')
                        ->image()
                        ->maxSize(2048)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make(__('appearance.slides'))
                ->description(__('appearance.slides_help'))
                ->schema([
                    Repeater::make('slides')
                        ->hiddenLabel()
                        ->addActionLabel(__('appearance.add_slide'))
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->collapsible()
                        ->reorderable()
                        ->defaultItems(0)
                        ->maxItems(6)
                        ->schema([
                            FileUpload::make('image')
                                ->label(__('appearance.image'))
                                ->helperText(__('appearance.image_help'))
                                ->disk(Branding::DISK)
                                ->directory('slides')
                                ->image()
                                ->maxSize(5120)
                                ->columnSpanFull(),

                            TextInput::make('eyebrow')
                                ->label(__('appearance.eyebrow'))
                                ->maxLength(60),

                            TextInput::make('title')
                                ->label(__('appearance.slide_title'))
                                ->maxLength(120),

                            Textarea::make('text')
                                ->label(__('appearance.text'))
                                ->rows(2)
                                ->maxLength(300)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ]),
        ]);
    }

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

                        Action::make('preview')
                            ->label(__('appearance.preview'))
                            ->color('gray')
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->url(fn () => route('filament.admin.auth.login'))
                            ->openUrlInNewTab(),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Setting::put('brand.name', $state['name'] ?: null);
        Setting::put('brand.tagline', $state['tagline'] ?: null);
        Setting::put('brand.logo', $state['logo'] ?: null);

        // Slide tanpa isi apa pun dibuang di sini, bukan saat dirender,
        // supaya yang tersimpan sama dengan yang nanti tampil.
        Setting::put('brand.slides', collect($state['slides'] ?? [])
            ->filter(fn (array $slide) => filled($slide['image'] ?? null)
                || filled($slide['title'] ?? null)
                || filled($slide['text'] ?? null))
            ->values()
            ->all());

        Notification::make()->success()->title(__('appearance.saved'))->send();
    }
}
