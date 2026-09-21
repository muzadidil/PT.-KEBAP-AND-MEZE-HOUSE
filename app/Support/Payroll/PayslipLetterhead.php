<?php

namespace App\Support\Payroll;

use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Support\Facades\Storage;

/**
 * Kop dan penandatangan slip gaji — tab "Pengaturan" di aplikasi Slip Gaji
 * aslinya.
 *
 * Logonya tidak diunggah terpisah: dipakai logo usaha yang sama dengan
 * halaman masuk (Data Induk → Tampilan), supaya pemilik tidak perlu
 * memelihara dua logo yang lama-lama berbeda.
 */
class PayslipLetterhead
{
    public const FIELDS = ['company', 'address', 'city', 'signer_name', 'signer_title'];

    /** @return array<string, string> */
    public static function get(): array
    {
        $defaults = [
            'company' => Branding::businessName(),
            'address' => config('business.address') ?: config('zeytin.letterhead.address'),
            'city' => '',
            'signer_name' => '',
            'signer_title' => '',
        ];

        $values = [];

        foreach (static::FIELDS as $field) {
            $values[$field] = (string) (Setting::get('payslip.'.$field) ?: $defaults[$field]);
        }

        return $values;
    }

    /** @param  array<string, mixed>  $data */
    public static function save(array $data): void
    {
        foreach (static::FIELDS as $field) {
            Setting::put('payslip.'.$field, trim((string) ($data[$field] ?? '')));
        }
    }

    /** Berkas logo di disk, untuk PDF — dompdf membaca berkas, bukan URL. */
    public static function logoPath(): ?string
    {
        $path = Setting::get('brand.logo');
        $disk = Storage::disk(Branding::DISK);

        return $path && $disk->exists($path) ? $disk->path($path) : null;
    }

    public static function logoUrl(): ?string
    {
        return Branding::logoUrl();
    }
}
