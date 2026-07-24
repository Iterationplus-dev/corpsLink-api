<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to CorpersLink 🎉')
            ->greeting("Welcome to CorpersLink, {$notifiable->name}!")
            ->line('Your account is verified. Find your institution to book your first camp trip.')
            ->line('Campus transport, sorted.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'welcome',
            'title' => 'Welcome to CorpersLink 🎉',
            'body' => 'Your account is verified. Find your institution to book your first camp trip.',
        ];
    }
}
