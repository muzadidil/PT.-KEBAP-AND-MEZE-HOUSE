<?php

namespace App\Filament\Admin\Actions;

use App\Support\Telegram\TelegramBot;
use App\Support\Telegram\TelegramSalesPoller;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Tombol "Tarik dari bot Telegram": membaca pesan baru di grup SAAT ITU JUGA,
 * sama persis dengan perintah `telegram:fetch` yang dijalankan cron. Berguna
 * untuk mencoba dan kalau cron belum terpasang.
 */
class TelegramPullAction
{
    public static function make(): Action
    {
        return Action::make('telegramPull')
            ->label(__('excel.telegram.pull.action'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(__('excel.telegram.pull.heading'))
            ->modalDescription(__('excel.telegram.pull.description'))
            ->modalSubmitActionLabel(__('excel.telegram.pull.submit'))
            ->action(function () {
                if (! (new TelegramBot)->configured()) {
                    static::fail(__('excel.telegram.pull.no_token'));

                    return;
                }

                try {
                    // Id grup belum diisi: hanya cari id-nya, jangan mencatat apa pun.
                    $discover = ! config('telegram.chat_ids');
                    $result = (new TelegramSalesPoller(new TelegramBot))->run($discover);
                } catch (Throwable $e) {
                    static::fail($e->getMessage());

                    return;
                }

                if ($discover) {
                    $chats = collect($result['chats'])->map(fn ($title, $id) => "{$id}  {$title}")->values()->all();

                    Notification::make()
                        ->warning()
                        ->persistent()
                        ->title(__('excel.telegram.pull.no_chat_ids'))
                        ->body(new HtmlString($chats
                            ? e(__('excel.telegram.pull.chat_ids_found')).'<br>'.implode('<br>', array_map('e', $chats))
                            : e(__('excel.telegram.pull.no_messages'))))
                        ->send();

                    return;
                }

                Notification::make()
                    ->status($result['recorded'] ? 'success' : 'info')
                    ->title(__('excel.telegram.pull.done'))
                    ->body(__('excel.telegram.pull.summary', [
                        'checked' => $result['checked'],
                        'recorded' => $result['recorded'],
                        'skipped' => $result['skipped'],
                        'failed' => $result['failed'],
                        'ignored' => $result['ignored'],
                    ]))
                    ->send();
            });
    }

    protected static function fail(string $message): void
    {
        Notification::make()->danger()->persistent()->title(__('excel.telegram.pull.failed'))->body($message)->send();
    }
}
