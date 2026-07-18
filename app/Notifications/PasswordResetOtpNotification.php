<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PasswordResetOtpNotification extends Notification implements ShouldQueue
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
        return ['mail', WhatsAppChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your password — CorpsLink')
            ->greeting('Password reset requested')
            ->line('Use the code below to reset your CorpsLink password.')
            ->line(new HtmlString("<div style=\"font-size:28px;font-weight:700;letter-spacing:6px;\">{$this->code}</div>"))
            ->line("This code expires in {$this->expiryMinutes} minutes.")
            ->line("If you didn't request a password reset, no action is needed — your password won't change.");
    }

    /**
     * @return array<int, string>
     */
    public function toWhatsApp(object $notifiable): array
    {
        return [$this->code, (string) $this->expiryMinutes];
    }
}
