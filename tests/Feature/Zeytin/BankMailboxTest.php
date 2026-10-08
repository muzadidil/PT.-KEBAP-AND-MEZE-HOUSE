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

    public function test_email_bank_lain_dilewati_bukan_dianggap_masalah(): void
    {
        config(['bank.imap.user' => 'kotak@contoh.id', 'bank.imap.password' => 'sandi-aplikasi']);

        $promo = "From: BNI <noreply@bni.co.id>\r\nSubject: Promo\r\nAuthentication-Results: mx; dkim=pass header.i=@bni.co.id\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nDapatkan cashback hari ini.\r\n";
        [$client] = $this->fakeServer([3 => $promo, 4 => $promo, 5 => $this->raw()]);

        $result = BankMailbox::fetch($client);

        $this->assertSame(3, $result['checked']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(1, $result['added']);
        $this->assertSame([], $result['problems']);
    }

    public function test_notifikasi_berbentuk_tabel_html_terbaca(): void
    {
        $html = '<table><tr><td>No. Referensi BNI</td><td>:</td><td>20261003175133601966</td></tr>'
            .'<tr><td>Tanggal/Jam</td><td>:</td><td>05-10-2026 12:21:21</td></tr>'
            .'<tr><td>Jenis Transaksi</td><td>:</td><td>BI-FAST Transfer</td></tr>'
            .'<tr><td>Nominal</td><td>:</td><td>IDR 1,010,000.00</td></tr>'
            .'<tr><td>Pengirim</td><td>:</td><td>*******882 PT KEBAP AND MEZE HOUSE</td></tr>'
            .'<tr><td>Penerima</td><td>:</td><td>*******788 - SERDAR BAGLAYAN</td></tr>'
            .'<tr><td>Keterangan Pembayaran</td><td>:</td><td>Yogurt &amp; Peynir 25 sep</td></tr>'
            .'<tr><td>Status</td><td>:</td><td>Berhasil</td></tr></table>';

        $raw = "From: BNI <noreply@bni.co.id>\r\nAuthentication-Results: mx; dkim=pass header.i=@bni.co.id\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n";
        $message = MailMessage::parse($raw);

        $parsed = \App\Support\Bank\BniNotification::parse($message->text);

        $this->assertSame([], $parsed['problems']);
        $this->assertSame(1_010_000, $parsed['parsed'][0]['amount']);
        $this->assertSame('SERDAR BAGLAYAN', $parsed['parsed'][0]['beneficiary']);
    }

    public function test_email_satu_baris_tanpa_pemisah_tetap_terbaca(): void
    {
        $flat = 'Berikut kami informasikan transaksi yang telah dilakukan dengan detail sebagai berikut: '
            .'No. Referensi BNI : 20261003175133601966 Tanggal/Jam : 05-10-2026 12:21:21 Jenis Transaksi : BI-FAST Transfer '
            .'Nominal : IDR 1,010,000.00 Pengirim : *******882 PT KEBAP AND MEZE HOUSE Penerima : *******788 - SERDAR BAGLAYAN '
            .'Bank Penerima : CENAIDJA - BANK CENTRAL ASIA Keterangan Pembayaran : Yogurt & Peynir 25 sep Status : Berhasil Terima kasih. PT Bank Negara Indonesia';

        $parsed = \App\Support\Bank\BniNotification::parse($flat);

        $this->assertSame([], $parsed['problems']);
        $this->assertCount(1, $parsed['parsed']);
        $row = $parsed['parsed'][0];
        $this->assertSame('SERDAR BAGLAYAN', $row['beneficiary']);
        $this->assertSame('CENAIDJA - BANK CENTRAL ASIA', $row['beneficiary_bank']);
        $this->assertSame('Yogurt & Peynir 25 sep', $row['remark']);
        $this->assertSame('out', $row['direction']);
        $this->assertSame(1_010_000, $row['amount']);
    }

    public function test_format_lain_tanpa_nomor_referensi_dihitung_sendiri_dan_judulnya_terlihat(): void
    {
        config(['bank.imap.user' => 'kotak@contoh.id', 'bank.imap.password' => 'sandi-aplikasi']);

        $other = "From: BNI <noreply@bni.co.id>\r\nAuthentication-Results: mx; dkim=pass header.i=@bni.co.id\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n"
            ."Berikut kami informasikan transaksi:\r\nTanggal/Jam : 05-10-2026 12:21:21\r\nJenis Transaksi : Informasi lain\r\n";
        [$client] = $this->fakeServer([4 => $other]);

        $result = BankMailbox::fetch($client);

        $this->assertSame(1, $result['other_format']);
        $this->assertSame(0, $result['added']);
        $this->assertContains('Jenis Transaksi', \App\Support\Bank\BniNotification::labels($other));
    }

    /** Bentuk BI-FAST kedua: Dari/Ke Rekening, nomor referensi di tengah, blok Inggris tanpa status. */
    public function test_format_bi_fast_dari_ke_rekening_terbaca(): void
    {
        $text = 'Berikut kami informasikan transaksi yang telah dilakukan dengan detail sebagai berikut: '
            .'Tanggal/Jam : 05-10-2026 12:21:23 Jenis Transaksi : BI-FAST Transfer Dari Rekening : PT KEBAP AND MEZE HOUSE '
            .'Ke Rekening : CV. BAYU LESTARI Nominal : IDR 2,500,000.00 Keterangan : Bayar nota 30 sep Jenis Transfer : Langsung '
            .'No. Referensi : 20261005BIFAST0001 Status : Berhasil NPWP : 001 '
            .'Date/Time : 05-10-2026 12:21:23 Transaction Type : BI-FAST Transfer From Account : PT KEBAP AND MEZE HOUSE '
            .'To Account : CV. BAYU LESTARI Amount : IDR 2,500,000.00 Remark : Bayar nota 30 sep Instruction Mode : Immediate Reference No. : 20261005BIFAST0001';

        $result = \App\Support\Bank\BniNotification::parse($text);

        $this->assertSame([], $result['problems']);
        $this->assertCount(1, $result['parsed']);

        $row = $result['parsed'][0];
        $this->assertSame('20261005BIFAST0001', $row['reference']);
        $this->assertSame('2026-10-05 12:21:23', $row['occurred_at']->format('Y-m-d H:i:s'));
        $this->assertSame(2_500_000, $row['amount']);
        $this->assertSame('out', $row['direction']);
        $this->assertSame('PT KEBAP AND MEZE HOUSE', $row['remitter']);
        $this->assertSame('CV. BAYU LESTARI', $row['beneficiary']);
        $this->assertSame('Bayar nota 30 sep', $row['remark']);
        $this->assertSame('BI-FAST Transfer', $row['type']);
    }

    public function test_dua_bentuk_email_dalam_satu_teks_dan_nama_berawalan_x_utuh(): void
    {
        $second = 'Tanggal/Jam : 06-10-2026 08:00:00 Jenis Transaksi : BI-FAST Transfer Dari Rekening : PT KEBAP AND MEZE HOUSE '
            .'Ke Rekening : XENIA SUPPLY Nominal : IDR 100,000.00 Keterangan : Es Jenis Transfer : Langsung '
            .'No. Referensi : REF222 Status : Berhasil NPWP : 001';

        $result = \App\Support\Bank\BniNotification::parse($this->notification().' '.$second);

        $references = array_column($result['parsed'], 'reference');
        $this->assertContains('20261003175133601966', $references);
        $this->assertContains('REF222', $references);
        $this->assertSame('XENIA SUPPLY', collect($result['parsed'])->firstWhere('reference', 'REF222')['beneficiary']);
    }
}
