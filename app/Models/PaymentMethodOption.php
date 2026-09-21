<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cara pembayaran ke pemasok, didaftar sekali lalu dipakai sebagai saran.
 *
 * Di berkas Excel isinya diketik ulang tiap baris ("Transfer", "COD",
 * "Cash"), jadi ejaannya gampang berbeda-beda dan pengelompokan laporannya
 * ikut berantakan.
 *
 * Namanya PaymentMethodOption, bukan PaymentMethod, supaya tidak tertukar
 * dengan enum App\Enums\PaymentMethod: enum itu menentukan ke mana uang
 * bergerak di neraca dan hanya boleh berisi tiga nilai yang dikenal Ledger,
 * sedangkan yang ini sekadar label yang boleh ditambah pemilik sendiri.
 */
class PaymentMethodOption extends Model
{
    protected $table = 'payment_methods';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
