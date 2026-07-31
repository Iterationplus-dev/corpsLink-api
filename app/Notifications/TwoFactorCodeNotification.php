<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        protected int $expiryMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [SmsChannel::class, WhatsAppChannel::class];
    }

    public function toSms(object $notifiable): string
    {
        return "Your CorpersLink sign-in code is {$this->code}. It expires in {$this->expiryMinutes} minutes. Don't share this code.";
    }

    /**
     * @return array<int, string>
     */
    public function toWhatsApp(object $notifiable): array
    {
        return [$this->code, (string) $this->expiryMinutes];
    }
}
