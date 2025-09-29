<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';
    protected $description = 'Set Telegram bot webhook URL';

    public function handle()
    {
        $url = env('TELEGRAM_WEBHOOK_URL');

        if (!$url) {
            $this->error('TELEGRAM_WEBHOOK_URL is not set in .env');
            return;
        }

        $response = Telegram::setWebhook([
            'url' => $url,
        ]);

        $this->info('Webhook set successfully!');
        $this->info(json_encode($response, JSON_PRETTY_PRINT));
    }
}
