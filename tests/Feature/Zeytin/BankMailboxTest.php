<?php

namespace Tests\Feature\Zeytin;

use App\Models\BankTransaction;
use App\Support\Bank\BankMailbox;
use App\Support\Bank\Imap\ImapClient;
use App\Support\Bank\Imap\MailMessage;
use App\Support\Bank\Imap\Transport;
use Tests\TestCase;

/** Pembaca email bank: klien IMAP, penguraian email, dan pemeriksaan pengirim. */
class BankMailboxTest extends TestCase
{
    protected function notification(): string
    {
        return "No. Referensi BNI\t:\t20261003175133601966\nTanggal/Jam\t:\t05-10-2026 12:21:21\nJenis Transaksi\t:\tBI-FAST Transfer\n"
            ."Nominal\t:\tIDR 1,010,000.00\nPengirim\t:\t*******882 PT KEBAP AND MEZE HOUSE\nPenerima\t:\t*******788 - SERDAR BAGLAYAN\n"
            ."Bank Penerima\t:\tCENAIDJA - BANK CENTRAL ASIA\nKeterangan Pembayaran\t:\tYogurt & Peynir 25 sep\nStatus\t:\tBerhasil\n";
    }

    protected function raw(string $from = 'BNI <noreply@bni.co.id>', string $auth = 'mx.google.com; dkim=pass header.i=@bni.co.id header.s=sel; spf=pass smtp.mailfrom=bni.co.id'): string
    {
        $body = quoted_printable_encode($this->notification());

        return "From: {$from}\r\nSubject: Notifikasi Transaksi\r\nAuthentication-Results: {$auth}\r\n"
            ."Content-Type: multipart/alternative; boundary=\"b1\"\r\n\r\n"
            ."--b1\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n<p>abaikan html</p>\r\n"
            ."--b1\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n{$body}\r\n--b1--\r\n";
    }

    /** Server palsu: jawaban sudah disiapkan, perintah yang dikirim dicatat. */
    protected function fakeServer(array $emails): array
    {
        $uids = implode(' ', array_keys($emails));
        $out = "* OK ready\r\nA001 OK LOGIN done\r\n* 3 EXISTS\r\nA002 OK SELECT done\r\n* SEARCH {$uids}\r\nA003 OK SEARCH done\r\n";
        $tag = 3;

        foreach ($emails as $uid => $raw) {
            $tag++;
            $out .= sprintf("* %d FETCH (UID %d BODY[] {%d}\r\n%s)\r\nA%03d OK FETCH done\r\n", $uid, $uid, strlen($raw), $raw, $tag);
        }

        $out .= sprintf("* BYE\r\nA%03d OK LOGOUT done\r\n", $tag + 1);

        $transport = new class($out) implements Transport
        {
            public string $sent = '';

            public function __construct(protected string $buffer) {}

            public function readLine(): ?string
            {
                if ($this->buffer === '') {
                    return null;
                }

                $pos = strpos($this->buffer, "\r\n");
                $line = substr($this->buffer, 0, $pos);
                $this->buffer = substr($this->buffer, $pos + 2);

                return $line;
            }

            public function readBytes(int $length): string
            {
                $data = substr($this->buffer, 0, $length);
                $this->buffer = substr($this->buffer, $length);

                return $data;
            }

            public function write(string $data): void
            {
                $this->sent .= $data;
            }

            public function close(): void {}
        };

        return [new ImapClient($transport), $transport];
    }

    public function test_email_multipart_dibaca_dan_teks_polos_didahulukan(): void
    {
        $message = MailMessage::parse($this->raw());

        $this->assertStringContainsString('20261003175133601966', $message->text);
        $this->assertStringContainsString('Yogurt & Peynir 25 sep', $message->text);
        $this->assertStringNotContainsString('abaikan html', $message->text);
    }

    public function test_pengirim_dan_verifikasi_diperiksa(): void
    {
        $this->assertTrue(MailMessage::parse($this->raw())->isAuthenticFrom('bni.co.id'));

        // Domain mirip, atau tidak lolos DKIM/SPF: ditolak.
        $this->assertFalse(MailMessage::parse($this->raw('BNI <noreply@bni.co.id.palsu.com>'))->isAuthenticFrom('bni.co.id'));
        $this->assertFalse(MailMessage::parse($this->raw('BNI <noreply@bni.co.id>', 'mx.google.com; dkim=fail header.i=@bni.co.id; spf=fail'))->isAuthenticFrom('bni.co.id'));
        $this->assertFalse(MailMessage::parse($this->raw('BNI <noreply@bni.co.id>', 'mx.google.com; dkim=pass header.i=@penipu.com'))->isAuthenticFrom('bni.co.id'));
        $this->assertTrue(MailMessage::parse($this->raw('BNI <noreply@bni.co.id>', 'x; dkim=fail'))->isAuthenticFrom('bni.co.id', requireAuthentication: false));
    }

    public function test_pengambilan_email_masuk_antrean_dan_tidak_dobel(): void
    {
        config(['bank.imap.user' => 'kotak@contoh.id', 'bank.imap.password' => 'sandi-aplikasi']);
        [$client, $transport] = $this->fakeServer([7 => $this->raw()]);

        $result = BankMailbox::fetch($client);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['added']);
        $this->assertSame(1, BankTransaction::count());
        $this->assertSame('imap', BankTransaction::first()->source);

        // Perintah yang dikirim: pilih kotak masuk, cari, ambil tanpa menandai terbaca.
        $this->assertStringContainsString('LOGIN "kotak@contoh.id" "sandi-aplikasi"', $transport->sent);
        $this->assertStringContainsString('SELECT INBOX', $transport->sent);
        $this->assertStringContainsString('UID SEARCH SINCE', $transport->sent);
        $this->assertStringContainsString('BODY.PEEK[]', $transport->sent);

        [$again] = $this->fakeServer([7 => $this->raw()]);
        $second = BankMailbox::fetch($again);

        $this->assertSame(0, $second['added']);
        $this->assertSame(1, $second['duplicate']);
        $this->assertSame(1, BankTransaction::count());
    }

    public function test_email_palsu_ditolak_dan_tidak_masuk_antrean(): void
    {
        config(['bank.imap.user' => 'kotak@contoh.id', 'bank.imap.password' => 'sandi-aplikasi']);
        [$client] = $this->fakeServer([9 => $this->raw('BNI <noreply@bni.co.id>', 'mx; dkim=fail; spf=fail')]);

        $result = BankMailbox::fetch($client);

        $this->assertSame(1, $result['rejected']);
        $this->assertSame(0, BankTransaction::count());
    }

    public function test_tanpa_pengaturan_email_diberi_pesan_jelas(): void
    {
        config(['bank.imap.user' => null, 'bank.imap.password' => null]);

        $this->expectExceptionMessage('BANK_IMAP_USER');
        BankMailbox::fetch();
    }
}
