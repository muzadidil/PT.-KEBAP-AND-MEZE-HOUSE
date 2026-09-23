<?php

namespace App\Support\Excel;

/**
 * Hasil satu impor, untuk notifikasi di layar.
 *
 * Kalau ada kesalahan, tidak ada yang disimpan sama sekali: `errors` berisi
 * satu pesan per baris yang salah, lengkap dengan nomor barisnya di Excel,
 * supaya bisa dibetulkan lalu diunggah ulang.
 */
final class ImportReport
{
    /**
     * @param  array<string, int>  $counts  jenis => jumlah baris (created, updated, skipped, imported, replaced)
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public array $counts = [],
        public array $errors = [],
        public ?string $note = null,
    ) {}

    /** @param  array<int, string>  $errors */
    public static function failed(array $errors): self
    {
        return new self(errors: $errors);
    }

    public function ok(): bool
    {
        return ! $this->errors;
    }

    /** Baris yang benar-benar masuk atau berubah; yang dilewati tidak dihitung. */
    public function changed(): int
    {
        return array_sum(array_diff_key($this->counts, ['skipped' => true, 'manual' => true]));
    }

    public function count(string $kind): int
    {
        return $this->counts[$kind] ?? 0;
    }

    /** "3 baru · 2 diperbarui · 1 sudah ada" */
    public function summary(): string
    {
        $parts = [];

        foreach ($this->counts as $kind => $count) {
            if ($count) {
                $parts[] = trans_choice('excel.count.'.$kind, $count, ['count' => $count]);
            }
        }

        return implode(' · ', $parts);
    }
}
