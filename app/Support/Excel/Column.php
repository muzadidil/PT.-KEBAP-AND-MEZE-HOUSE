<?php

namespace App\Support\Excel;

use App\Support\Zeytin\Workbook\Cells;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu kolom template Excel: judulnya, jenis isiannya, dan cara membacanya
 * balik saat diimpor.
 *
 * Satu deklarasi dipakai dua arah — menulis template dan membaca berkas
 * yang diunggah — jadi keduanya tidak mungkin berbeda pendapat soal kolom
 * mana yang wajib, pilihan mana yang sah, atau angka apa yang diterima.
 */
class Column
{
    public const TEXT = 'text';

    public const NUMBER = 'number';

    public const MONEY = 'money';

    public const DATE = 'date';

    public const BOOLEAN = 'boolean';

    public const CHOICE = 'choice';

    public bool $required = false;

    public mixed $default = null;

    public ?int $maxLength = null;

    public int $min = 0;

    public ?int $max = null;

    /** @var (Closure(): array<int|string, string>)|null nilai => label */
    public ?Closure $options = null;

    /** Pilihan di luar daftar ditolak. */
    public bool $closed = true;

    /** Label pilihan juga boleh ditulis sebagai nilainya ("cash" untuk "Tunai"). */
    public bool $matchValues = true;

    /** @var (Closure(string): int|string)|null membuat pilihan baru, mengembalikan nilainya */
    public ?Closure $creator = null;

    /** Disimpan sebagai teks di Excel, supaya nol di depan tidak hilang. */
    public bool $asText = false;

    public ?int $width = null;

    /** @var array<int|string, string>|null */
    protected ?array $resolvedOptions = null;

    /** @var array<int|string, array<int, string>>|null */
    protected ?array $allLabels = null;

    /**
     * @param  string  $label  kunci terjemahan judul kolom
     */
    public function __construct(public string $name, public string $label, public string $type = self::TEXT) {}

    public static function text(string $name, string $label): static
    {
        return new static($name, $label, self::TEXT);
    }

    public static function number(string $name, string $label): static
    {
        return new static($name, $label, self::NUMBER);
    }

    public static function money(string $name, string $label): static
    {
        return new static($name, $label, self::MONEY);
    }

    public static function date(string $name, string $label): static
    {
        return new static($name, $label, self::DATE);
    }

    public static function boolean(string $name, string $label): static
    {
        return new static($name, $label, self::BOOLEAN);
    }

    /** @param  Closure(): array<int|string, string>  $options  nilai => label */
    public static function choice(string $name, string $label, Closure $options): static
    {
        $column = new static($name, $label, self::CHOICE);
        $column->options = $options;

        return $column;
    }

    /**
     * Enum dengan label (Filament HasLabel) sebagai daftar tertutup.
     *
     * @param  class-string<\BackedEnum>  $enum
     */
    public static function enum(string $name, string $label, string $enum): static
    {
        return static::choice($name, $label, fn () => collect($enum::cases())
            ->mapWithKeys(fn ($case) => [$case->value => method_exists($case, 'getLabel') ? $case->getLabel() : $case->name])
            ->all());
    }

    /**
     * Relasi belongsTo: di Excel ditulis namanya, disimpan id-nya.
     *
     * @param  class-string<Model>  $model
     */
    public static function relation(string $name, string $label, string $model, string $column = 'name'): static
    {
        $relation = static::choice($name, $label, fn () => $model::query()->orderBy($column)->pluck($column, 'id')->all());

        // Id tidak diterima sebagai isian: "3" bisa berarti nama "3".
        $relation->matchValues = false;

        return $relation;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    /** Dipakai saat membuat baris baru dan selnya kosong. */
    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function maxLength(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    public function between(int $min, ?int $max = null): static
    {
        $this->min = $min;
        $this->max = $max;

        return $this;
    }

    /** Nama di luar daftar tetap boleh — seperti kotak isian bersaran di formulir. */
    public function open(): static
    {
        $this->closed = false;

        return $this;
    }

    /** @param  Closure(string): int|string  $creator */
    public function creates(Closure $creator): static
    {
        $this->creator = $creator;
        $this->closed = false;

        return $this;
    }

    public function asText(): static
    {
        $this->asText = true;

        return $this;
    }

    public function width(int $width): static
    {
        $this->width = $width;

        return $this;
    }

    /* ----------------------------------------------------------- judul */

    public function title(): string
    {
        return __($this->label).($this->required ? ' *' : '');
    }

    /**
     * Judul yang dikenali saat membaca balik: judul di kedua bahasa
     * aplikasi, dan nama kolomnya sendiri. Template yang diunduh dalam
     * bahasa Inggris tetap terbaca oleh pengguna yang memakai bahasa
     * Indonesia.
     *
     * @return array<int, string>
     */
    public function aliases(): array
    {
        return array_values(array_unique([
            static::norm(__($this->label, [], 'id')),
            static::norm(__($this->label, [], 'en')),
            static::norm($this->name),
        ]));
    }

    /** Huruf kecil, spasi dirapatkan, tanda wajib (*) dibuang. */
    public static function norm(mixed $value): string
    {
        return trim(rtrim(Cells::norm($value), '* '));
    }

    /* --------------------------------------------------------- pilihan */

    /** @return array<int|string, string> nilai => label */
    public function options(): array
    {
        if ($this->type === self::BOOLEAN) {
            return [1 => __('excel.yes'), 0 => __('excel.no')];
        }

        return $this->resolvedOptions ??= $this->options ? ($this->options)() : [];
    }

    /**
     * Label tiap pilihan di kedua bahasa aplikasi, sudah dinormalkan.
     *
     * @return array<int|string, array<int, string>>
     */
    protected function labelsInAllLocales(): array
    {
        if ($this->allLabels !== null) {
            return $this->allLabels;
        }

        $current = app()->getLocale();
        $labels = [];

        foreach (array_unique([$current, 'id', 'en']) as $locale) {
            app()->setLocale($locale);

            try {
                $options = $locale === $current ? $this->options() : ($this->options ? ($this->options)() : []);
            } finally {
                app()->setLocale($current);
            }

            foreach ($options as $value => $label) {
                $labels[$value][] = static::norm($label);
            }
        }

        return $this->allLabels = $labels;
    }

    /** Label yang ditampilkan di Excel untuk satu nilai. */
    public function display(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->type === self::BOOLEAN) {
            return $value ? __('excel.yes') : __('excel.no');
        }

        if ($this->type === self::CHOICE) {
            $key = $value instanceof \BackedEnum ? $value->value : $value;

            return $this->options()[$key] ?? $key;
        }

        return $value;
    }

    /* ---------------------------------------------------------- membaca */

    /**
     * Isi satu sel, dibaca jadi nilai siap simpan.
     *
     * @return array{0: mixed, 1: string|null} nilai dan pesan kesalahan
     */
    public function read(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = trim($raw);
        }

        if ($raw === null || $raw === '') {
            return [null, $this->required && $this->default === null ? __('excel.error.required') : null];
        }

        return match ($this->type) {
            self::NUMBER, self::MONEY => $this->readNumber($raw),
            self::DATE => $this->readDate($raw),
            self::BOOLEAN => $this->readBoolean($raw),
            self::CHOICE => $this->readChoice($raw),
            default => $this->readText($raw),
        };
    }

    /** @return array{0: mixed, 1: string|null} */
    protected function readText(mixed $raw): array
    {
        // Angka yang diketik di sel biasa (telepon, NIK) jangan jadi "8123.0".
        $text = is_float($raw) && floor($raw) === $raw ? sprintf('%.0f', $raw) : trim((string) $raw);

        if ($this->maxLength && mb_strlen($text) > $this->maxLength) {
            return [null, __('excel.error.too_long', ['max' => $this->maxLength])];
        }

        return [$text, null];
    }

    /** @return array{0: mixed, 1: string|null} */
    protected function readNumber(mixed $raw): array
    {
        $number = Cells::parseNumber($raw);

        if ($number === null) {
            return [null, __('excel.error.number', ['value' => $raw])];
        }

        if ($number < $this->min || ($this->max !== null && $number > $this->max)) {
            return [null, $this->max !== null
                ? __('excel.error.between', ['min' => $this->min, 'max' => $this->max])
                : __('excel.error.min', ['min' => $this->min])];
        }

        return [$number, null];
    }

    /** @return array{0: mixed, 1: string|null} */
    protected function readDate(mixed $raw): array
    {
        $date = Cells::toDate($raw);

        return $date ? [$date->toDateString(), null] : [null, __('excel.error.date', ['value' => $raw])];
    }

    /** @return array{0: mixed, 1: string|null} */
    protected function readBoolean(mixed $raw): array
    {
        $value = static::norm($raw);

        $yes = ['1', 'ya', 'yes', 'y', 'true', 'benar', 'aktif', 'active', static::norm(__('excel.yes'))];
        $no = ['0', 'tidak', 'no', 'n', 'false', 'salah', 'nonaktif', 'inactive', static::norm(__('excel.no'))];

        return match (true) {
            in_array($value, $yes, true) => [true, null],
            in_array($value, $no, true) => [false, null],
            default => [null, __('excel.error.boolean', ['value' => $raw])],
        };
    }

    /** @return array{0: mixed, 1: string|null} */
    protected function readChoice(mixed $raw): array
    {
        $text = is_float($raw) && floor($raw) === $raw ? sprintf('%.0f', $raw) : trim((string) $raw);
        $wanted = static::norm($text);

        // Label di bahasa mana pun: "Tunai" dan "Cash" sama-sama tunai.
        foreach ($this->labelsInAllLocales() as $value => $labels) {
            if (in_array($wanted, $labels, true)) {
                return [$value, null];
            }
        }

        if ($this->matchValues) {
            foreach (array_keys($this->options()) as $value) {
                if (static::norm($value) === $wanted) {
                    return [$value, null];
                }
            }
        }

        if ($this->creator) {
            return [new PendingChoice($text), null];
        }

        if (! $this->closed) {
            return $this->readText($text);
        }

        return [null, __('excel.error.choice', ['value' => $text])];
    }
}
