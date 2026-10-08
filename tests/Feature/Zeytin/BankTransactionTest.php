<?php

namespace Tests\Feature\Zeytin;

use App\Filament\Admin\Resources\Zeytin\BankTransactions\Pages\ManageBankTransactions;
use App\Models\BankTransaction;
use App\Models\SupplierTransfer;
use App\Support\Bank\BankQueue;
use App\Support\Bank\BniNotification;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

/** Notifikasi bank BNI: dibaca, masuk antrean, dan baru dicatat setelah disetujui. */
class BankTransactionTest extends TestCase
{
    /** Bentuk email asli BNI: blok Indonesia lalu Inggris, nomor rekening disamarkan. */
    protected function email(string $reference = '20261003175133601966', string $remitter = '*******882 PT KEBAP AND MEZE HOUSE', string $status = 'Berhasil', string $statusEn = 'Success'): string
    {
        return <<<TXT
        Berikut kami informasikan transaksi yang telah dilakukan dengan detail sebagai berikut:


        No. Referensi BNI	:	{$reference}
        Tanggal/Jam	:	05-10-2026 12:21:21
        Jenis Transaksi	:	BI-FAST Transfer
        Nominal	:	IDR 1,010,000.00
        Pengirim	:	{$remitter}
        Penerima	:	*******788 - SERDAR BAGLAYAN
        Bank Penerima	:	CENAIDJA - BANK CENTRAL ASIA
        Keterangan Pembayaran	:	Yogurt & Peynir 25 sep
        Status	:	{$status}

        Terima kasih.
        ==========================================================================================================

        We would like to inform you the following transaction:


        BNI Reference Number	:	{$reference}
        Date/Time	:	05-10-2026 12:21:21
        Transaction Type	:	BI-FAST Transfer
        Amount	:	IDR 1,010,000.00
        Remitter	:	{$remitter}
        Beneficiary	:	*******788 - SERDAR BAGLAYAN
        Beneficiary Bank	:	CENAIDJA - BANK CENTRAL ASIA
        Transaction Remark	:	Yogurt & Peynir 25 sep
        Status	:	{$statusEn}

        Thank you.
        TXT;
    }

    public function test_email_asli_dibaca_dan_blok_inggris_tidak_dihitung_dua_kali(): void
    {
        $result = BniNotification::parse($this->email());

        $this->assertSame([], $result['problems']);
        $this->assertCount(1, $result['parsed']);

        $row = $result['parsed'][0];
        $this->assertSame('20261003175133601966', $row['reference']);
        $this->assertSame('2026-10-05 12:21:21', $row['occurred_at']->format('Y-m-d H:i:s'));
        $this->assertSame(1_010_000, $row['amount']);
        $this->assertSame('out', $row['direction']);
        $this->assertSame('SERDAR BAGLAYAN', $row['beneficiary']);
        $this->assertSame('PT KEBAP AND MEZE HOUSE', $row['remitter']);
        $this->assertSame('Yogurt & Peynir 25 sep', $row['remark']);
        $this->assertSame('BI-FAST Transfer', $row['type']);
    }

    public function test_beberapa_email_sekaligus_dan_transaksi_gagal_dilewati(): void
    {
        $text = $this->email('111111111111') . "\n\n" . $this->email('222222222222', status: 'Gagal', statusEn: 'Failed');

        $result = BniNotification::parse($text);

        $this->assertCount(1, $result['parsed']);
        $this->assertSame('111111111111', $result['parsed'][0]['reference']);
        $this->assertNotEmpty($result['problems']);
    }

    public function test_teks_tanpa_nomor_referensi_dilaporkan_bukan_ditebak(): void
    {
        $result = BniNotification::parse('Halo, ini bukan email bank.');

        $this->assertSame([], $result['parsed']);
        $this->assertNotEmpty($result['problems']);
    }

    public function test_pengirim_lain_dianggap_uang_masuk(): void
    {
        $row = BniNotification::parse($this->email(remitter: '*******111 BUDI SANTOSO'))['parsed'][0];

        $this->assertSame('in', $row['direction']);
    }

    public function test_antrean_tidak_menggandakan_dan_mencatat_hanya_setelah_disetujui(): void
    {
        $first = BankQueue::addFromText($this->email());
        $again = BankQueue::addFromText($this->email());

        $this->assertSame(1, $first['added']);
        $this->assertSame(0, $again['added']);
        $this->assertSame(1, $again['duplicate']);
        $this->assertSame(1, BankTransaction::count());
        $this->assertSame(0, SupplierTransfer::count());   // belum disetujui: belum tercatat

        $transaction = BankTransaction::first();
        $transfer = BankQueue::record($transaction);

        $this->assertSame(1_010_000, $transfer->total);
        $this->assertSame('2026-10-05', $transfer->date->toDateString());
        $this->assertSame('SERDAR BAGLAYAN', $transfer->vendor);
        $this->assertSame('Yogurt & Peynir 25 sep', $transfer->item);
        $this->assertSame('BNI', $transfer->method);
        $this->assertSame('bni:20261003175133601966', $transfer->import_key);
        $this->assertSame(BankTransaction::RECORDED, $transaction->fresh()->status);

        // Disetujui dua kali tidak menambah baris kedua.
        $this->assertNull(BankQueue::record($transaction->fresh()));
        $this->assertSame(1, SupplierTransfer::count());
    }

    public function test_uang_masuk_tidak_bisa_dicatat_sebagai_transfer_pemasok(): void
    {
        BankQueue::addFromText($this->email(remitter: '*******111 BUDI SANTOSO'));

        $this->assertNull(BankQueue::record(BankTransaction::first()));
        $this->assertSame(0, SupplierTransfer::count());
    }

    public function test_admin_menempel_email_dan_menyetujui_dari_halaman(): void
    {
        Filament::setCurrentPanel('admin');

        $page = Livewire::actingAs($this->admin())->test(ManageBankTransactions::class);

        $page->callAction('paste', ['text' => $this->email()])->assertHasNoActionErrors();
        $this->assertSame(1, BankTransaction::count());

        $transaction = BankTransaction::first();
        $page->callTableAction('record', $transaction);

        $this->assertSame(1, SupplierTransfer::count());
        $this->assertSame(BankTransaction::RECORDED, $transaction->fresh()->status);
    }
}
