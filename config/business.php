<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas Usaha
    |--------------------------------------------------------------------------
    |
    | Dipakai di kepala struk dan kepala laporan cetak. Diisi lewat .env
    | supaya satu kode yang sama bisa dipakai untuk cabang lain tanpa diubah.
    |
    */

    'name' => env('BUSINESS_NAME', 'PT. Kebap and Meze House'),
    'address' => env('BUSINESS_ADDRESS', ''),
    'phone' => env('BUSINESS_PHONE', ''),
    'tax_id' => env('BUSINESS_TAX_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Mata Uang
    |--------------------------------------------------------------------------
    |
    | Seluruh angka disimpan sebagai bilangan bulat dalam satuan terkecil yang
    | dipakai (rupiah, tanpa sen). Lihat App\Support\Money.
    |
    */

    'currency' => env('BUSINESS_CURRENCY', 'IDR'),

];
