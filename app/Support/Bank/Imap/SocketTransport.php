<?php

namespace App\Support\Bank\Imap;

use RuntimeException;

/** Koneksi TLS sungguhan (port 993). */
class SocketTransport implements Transport
{
    /** @var resource */
    protected $stream;

    public function __construct(string $host, int $port, int $timeout = 25)
    {
        $stream = @stream_socket_client("ssl://{$host}:{$port}", $errno, $error, $timeout);

        if (! $stream) {
            throw new RuntimeException("Tidak bisa terhubung ke {$host}:{$port} ({$error}).");
        }

        stream_set_timeout($stream, $timeout);
        $this->stream = $stream;
    }

    public function readLine(): ?string
    {
        $line = fgets($this->stream);

        return $line === false ? null : rtrim($line, "\r\n");
    }

    public function readBytes(int $length): string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($this->stream, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Koneksi IMAP terputus saat membaca email.');
            }

            $data .= $chunk;
        }

        return $data;
    }

    public function write(string $data): void
    {
        fwrite($this->stream, $data);
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
