<?php

namespace App\Support\Excel;

/**
 * Pilihan yang belum ada dan akan dibuat saat baris disimpan — misalnya
 * proyek baru di Progres Rapat. Dibuat belakangan, di dalam transaksi
 * impor, supaya berkas yang ditolak tidak meninggalkan proyek kosong.
 */
final class PendingChoice
{
    public function __construct(public readonly string $label) {}
}
