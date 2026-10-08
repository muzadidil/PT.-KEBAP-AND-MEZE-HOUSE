<?php

namespace App\Support\Bank\Imap;

/**
 * Membaca satu email mentah (RFC 822): header yang dibutuhkan dan isi teksnya,
 * dengan sandi quoted-printable/base64 dan huruf non-UTF-8 sudah diterjemahkan.
 * Teks polos didahulukan; bila hanya ada HTML, tag-nya dibuang.
 */
class MailMessage
{
    /** @param  array<string, string>  $headers  nama header huruf kecil => nilai */
    public function __construct(
        public array $headers,
        public string $text,
    ) {}

    public static function parse(string $raw): self
    {
        [$headers, $body] = static::split($raw);

        return new self($headers, static::text($headers, $body));
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    /**
     * Apakah email ini benar dari domain yang diharapkan DAN lolos
     * pemeriksaan DKIM atau SPF penyedia email. Tanpa pemeriksaan ini,
     * siapa pun bisa menulis "From: bni.co.id" dan memasukkan catatan palsu.
     */
    public function isAuthenticFrom(string $domain, bool $requireAuthentication = true): bool
    {
        $domain = strtolower(ltrim($domain, '@'));

        // Alamat pengirim: bagian setelah @ pada "Nama <alamat@host>" atau "alamat@host".
        if (! preg_match('/@([a-z0-9.-]+)>?\s*$/i', trim($this->header('from')), $m)) {
            return false;
        }

        $host = strtolower($m[1]);

        // "bni.co.id.palsu.com" tidak boleh lolos: harus persis domain itu atau subdomainnya.
        if ($host !== $domain && ! str_ends_with($host, '.'.$domain)) {
            return false;
        }

        if (! $requireAuthentication) {
            return true;
        }

        $auth = strtolower($this->header('authentication-results'));
        $quoted = preg_quote($domain, '/');

        $dkim = (bool) preg_match('/dkim=pass[^;]*(header\.(d|i)=@?[a-z0-9.-]*'.$quoted.')/', $auth);
        $spf = (bool) preg_match('/spf=pass[^;]*(smtp\.mailfrom=[^;\s]*'.$quoted.')/', $auth);

        return $dkim || $spf;
    }

    /** @return array{0: array<string, string>, 1: string} */
    protected static function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $position = strpos($raw, "\n\n");
        $head = $position === false ? $raw : substr($raw, 0, $position);
        $body = $position === false ? '' : substr($raw, $position + 2);

        // Header yang berlanjut ke baris berikutnya disatukan.
        $head = preg_replace("/\n[ \t]+/", ' ', $head);
        $headers = [];

        foreach (explode("\n", $head) as $line) {
            if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $line, $m)) {
                $headers[strtolower($m[1])] ??= trim($m[2]);

                // Authentication-Results bisa muncul beberapa kali; semuanya dikumpulkan.
                if (strtolower($m[1]) === 'authentication-results' && isset($headers['authentication-results']) && $headers['authentication-results'] !== trim($m[2])) {
                    $headers['authentication-results'] .= '; '.trim($m[2]);
                }
            }
        }

        return [$headers, $body];
    }

    /** @param  array<string, string>  $headers */
    protected static function text(array $headers, string $body): string
    {
        $type = $headers['content-type'] ?? 'text/plain';

        if (preg_match('/^multipart\//i', $type) && preg_match('/boundary="?([^";\s]+)"?/i', $type, $m)) {
            $plain = null;
            $html = null;

            foreach (explode('--'.$m[1], $body) as $part) {
                if (trim($part) === '' || str_starts_with(trim($part), '--')) {
                    continue;
                }

                [$partHeaders, $partBody] = static::split(ltrim($part, "\r\n"));
                $partType = strtolower($partHeaders['content-type'] ?? 'text/plain');
                $text = static::text($partHeaders, $partBody);

                if (str_starts_with($partType, 'text/plain')) {
                    $plain ??= $text;
                } elseif (str_starts_with($partType, 'text/html')) {
                    $html ??= $text;
                } elseif (str_starts_with($partType, 'multipart/')) {
                    $plain ??= $text;
                }
            }

            return $plain ?? $html ?? '';
        }

        $decoded = static::decode($headers['content-transfer-encoding'] ?? '7bit', $body);
        $charset = preg_match('/charset="?([^";\s]+)"?/i', $type, $m) ? $m[1] : 'UTF-8';

        if (strcasecmp($charset, 'utf-8') !== 0) {
            $decoded = @mb_convert_encoding($decoded, 'UTF-8', $charset) ?: $decoded;
        }

        return str_starts_with(strtolower($type), 'text/html')
            ? html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/(p|div|tr)>/i', "\n", $decoded)))
            : $decoded;
    }

    protected static function decode(string $encoding, string $body): string
    {
        return match (strtolower(trim($encoding))) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body)),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }
}
