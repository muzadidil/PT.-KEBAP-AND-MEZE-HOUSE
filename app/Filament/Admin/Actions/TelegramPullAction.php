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
 * Tombol "Tarik dari bot Telegram" di Antrean Telegram: membaca pesan baru di
 * grup SAAT ITU JUGA, sama persis dengan perintah `telegram:fetch` yang
 * dijalankan cron. Hasilnya masuk antrean untuk dikonfirmasi, bukan langsung
 * ke pembukuan.
 */
class TelegramPullAction
{
    public static function make(): Action
    {
        return Action::make('telegramPull')
            ->label(__('telegram.pull.action'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('telegram.pull.heading'))
            ->modalDescription(__('telegram.pull.description'))
            ->modalSubmitActionLabel(__('telegram.pull.submit'))
            ->action(function () {
                if (! (new TelegramBot)->configured()) {
                    static::fail(__('telegram.pull.no_token'));

                    return;
                }

                try {
                    // Id grup belum diisi: hanya cari id-nya, jangan menerima apa pun.
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
                        ->title(__('telegram.pull.no_chat_ids'))
                        ->body(new HtmlString($chats
                            ? e(__('telegram.pull.chat_ids_found')).'<br>'.implode('<br>', array_map('e', $chats))
                            : e(__('telegram.pull.no_messages'))))
                        ->send();

                    return;
                }

                Notification::make()
                    ->status($result['queued'] ? 'success' : 'info')
                    ->title(__('telegram.pull.done'))
                    ->body(__('telegram.pull.summary', [
                        'checked' => $result['checked'],
                        'queued' => $result['queued'],
                        'failed' => $result['failed'],
                        'ignored' => $result['ignored'],
                    ]))
                    ->send();
            });
    }

    protected static function fail(string $message): void
    {
        Notification::make()->danger()->persistent()->title(__('telegram.pull.failed'))->body($message)->send();
    }
}
