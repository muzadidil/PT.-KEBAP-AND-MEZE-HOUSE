<?php

namespace App\Support\Bank\Imap;

use RuntimeException;

/**
 * Klien IMAP secukupnya: masuk, pilih kotak masuk, cari, dan ambil satu
 * email tanpa menandainya sudah dibaca (BODY.PEEK). Hanya itu.
 *
 * Ditulis sendiri karena hosting bersama sering tanpa ekstensi imap dan
 * tanpa Composer, jadi paket tambahan tidak bisa dipasang di sana.
 */
class ImapClient
{
    protected int $tag = 0;

    public function __construct(protected Transport $transport) {}

    public static function connect(string $host, int $port, int $timeout = 25): self
    {
        $client = new self(new SocketTransport($host, $port, $timeout));
        $client->greeting();

        return $client;
    }

    public function greeting(): void
    {
        $line = $this->transport->readLine();

        if ($line === null || ! str_starts_with($line, '* OK')) {
            throw new RuntimeException('Server IMAP tidak menyapa dengan benar.');
        }
    }

    public function login(string $user, string $password): void
    {
        $this->command('LOGIN '.$this->quote($user).' '.$this->quote($password), 'Gagal masuk ke email. Periksa alamat dan kata sandi khusus aplikasi.');
    }

    public function selectInbox(): void
    {
        $this->command('SELECT INBOX', 'Kotak masuk tidak bisa dibuka.');
    }

    /**
     * Nomor unik email yang cocok. Dicari lewat kriteria IMAP, mis.
     * `SINCE 01-Oct-2026 FROM "bni.co.id"`.
     *
     * @return array<int, int>
     */
    public function search(string $criteria): array
    {
        $response = $this->command('UID SEARCH '.$criteria, 'Pencarian email gagal.');

        foreach ($response['lines'] as $line) {
            if (preg_match('/^\* SEARCH ?(.*)$/', $line, $m)) {
                return $m[1] === '' ? [] : array_map('intval', preg_split('/\s+/', trim($m[1])));
            }
        }

        return [];
    }

    /** Isi email utuh (header dan badan), tanpa menandainya terbaca. */
    public function fetch(int $uid): string
    {
        $response = $this->command("UID FETCH {$uid} BODY.PEEK[]", 'Email tidak bisa diambil.');

        return $response['literals'][0] ?? throw new RuntimeException("Isi email {$uid} kosong.");
    }

    public function logout(): void
    {
        try {
            $this->command('LOGOUT', 'Keluar gagal.');
        } catch (\Throwable) {
        }

        $this->transport->close();
    }

    /** @return array{lines: array<int, string>, literals: array<int, string>} */
    protected function command(string $command, string $failure): array
    {
        $tag = sprintf('A%03d', ++$this->tag);
        $this->transport->write("{$tag} {$command}\r\n");

        $lines = [];
        $literals = [];

        while (($line = $this->transport->readLine()) !== null) {
            // Isi sepanjang N byte menyusul setelah baris yang berakhir "{N}".
            while (preg_match('/\{(\d+)\}$/', $line, $m)) {
                $literals[] = $this->transport->readBytes((int) $m[1]);
                $line .= ($this->transport->readLine() ?? '');
            }

            if (str_starts_with($line, $tag.' ')) {
                if (! preg_match('/^'.$tag.' OK/i', $line)) {
                    throw new RuntimeException($failure);
                }

                return ['lines' => $lines, 'literals' => $literals];
            }

            $lines[] = $line;
        }

        throw new RuntimeException('Koneksi IMAP terputus.');
    }

    protected function quote(string $value): string
    {
        return '"'.addcslashes($value, "\"\\").'"';
    }
}
