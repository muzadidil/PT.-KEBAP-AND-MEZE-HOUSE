<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Barang yang biasa dibelanjakan, beserta harga terakhirnya.
 *
 * Bukan Product: Product adalah menu yang dijual ke tamu, ini bahan yang
 * dibeli dari pemasok. Harga di sini cuma tawaran awal — begitu dipilih di
 * formulir belanja, angkanya masih bisa ditimpa, karena harga pemasok
 * berubah terus dan formulir yang memaksakan harga lama membuat catatannya
 * salah dengan rapi.
 */
class PurchaseItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'active' => 'boolean',
        ];
    }
}
