<?php

namespace App\Support\Bank\Imap;

/** Saluran ke server IMAP; dipisah supaya klien bisa diuji tanpa jaringan. */
interface Transport
{
    /** Satu baris tanpa CRLF, atau null bila koneksi putus. */
    public function readLine(): ?string;

    public function readBytes(int $length): string;

    public function write(string $data): void;

    public function close(): void;
}
