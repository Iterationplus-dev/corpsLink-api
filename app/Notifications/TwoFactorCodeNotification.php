<?php

namespace App\Notifications;

use App\Notifications\Channels\TermiiChannel;
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
        return [TermiiChannel::class];
    }

    public function toTermii(object $notifiable): string
    {
        return "Your CorpsLink sign-in code is {$this->code}. It expires in {$this->expiryMinutes} minutes. Don't share this code.";
    }
}
