<?php

namespace App\Support\Bank;

use App\Support\Bank\Imap\ImapClient;
use App\Support\Bank\Imap\MailMessage;
use RuntimeException;

/**
 * Mengambil notifikasi bank dari kotak email lalu memasukkannya ke antrean
 * Transaksi Bank. Tidak mencatat ke pembukuan: itu tetap keputusan Admin.
 *
 * Email yang bukan dari domain bank, atau gagal pemeriksaan DKIM/SPF, tidak
 * dibaca dan dihitung di laporan hasilnya. Email tidak dihapus dan tidak
 * ditandai terbaca; nomor referensi mencegah dobel bila dijalankan berulang.
 */
class BankMailbox
{
    /**
     * @return array{checked: int, added: int, duplicate: int, rejected: int, problems: array<int, string>}
     */
    public static function fetch(?ImapClient $client = null): array
    {
        $config = config('bank');

        if (! $client && (blank($config['imap']['user']) || blank($config['imap']['password']))) {
            throw new RuntimeException('Email belum diatur. Isi BANK_IMAP_USER dan BANK_IMAP_PASSWORD di .env.');
        }

        $client ??= ImapClient::connect($config['imap']['host'], $config['imap']['port'], $config['imap']['timeout']);

        $result = ['checked' => 0, 'added' => 0, 'duplicate' => 0, 'rejected' => 0, 'problems' => []];

        try {
            if ($config['imap']['user']) {
                $client->login($config['imap']['user'], $config['imap']['password']);
            }

            $client->selectInbox();

            $since = now()->subDays(max(1, $config['lookback_days']))->format('d-M-Y');
            $uids = $client->search('SINCE '.$since.' FROM "'.$config['sender_domain'].'"');

            foreach ($uids as $uid) {
                $result['checked']++;
                $message = MailMessage::parse($client->fetch($uid));

                if (! $message->isAuthenticFrom($config['sender_domain'], $config['require_authentication'])) {
                    $result['rejected']++;

                    continue;
                }

                $queued = BankQueue::addFromText($message->text, 'imap');
                $result['added'] += $queued['added'];
                $result['duplicate'] += $queued['duplicate'];
                array_push($result['problems'], ...$queued['problems']);
            }
        } finally {
            $client->logout();
        }

        return $result;
    }
}
