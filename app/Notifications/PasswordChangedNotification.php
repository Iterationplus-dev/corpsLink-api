<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your password was changed — CorpersLink')
            ->greeting('Your password was changed')
            ->line('This is a confirmation that the password for your CorpersLink account was just changed.')
            ->line('All other sessions have been signed out for your security.')
            ->line("If you didn't make this change, please contact support immediately.");
    }
}
