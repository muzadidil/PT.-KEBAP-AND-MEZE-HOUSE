<?php

namespace App\Support\Zeytin;

/**
 * Dari mana sebuah baris pembukuan datang.
 *
 * Satu pembedaan, dan seluruh janji "impor tidak menimpa ketikan Anda"
 * bergantung padanya: pengimpor hanya boleh mengganti dan membuang baris
 * miliknya sendiri. Nilainya ditulis di sini, bukan sebagai teks 'import'
 * yang diketik ulang di sepuluh tempat — satu salah ketik di salah satunya
 * membuat baris itu tidak pernah ikut dibersihkan, dan tidak ada yang akan
 * menyadarinya sampai angkanya terhitung dua kali.
 */
final class RecordSource
{
    /** Diketik orang lewat halaman; tidak pernah disentuh impor. */
    public const MANUAL = 'manual';

    /** Dibawa berkas Excel; boleh diganti impor berikutnya. */
    public const IMPORT = 'import';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::MANUAL => __('zeytin.source.manual'),
            self::IMPORT => __('zeytin.source.import'),
        ];
    }
}
