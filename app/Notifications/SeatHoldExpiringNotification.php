<?php

namespace App\Notifications;

use App\Models\SeatHold;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Channels\TermiiChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SeatHoldExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SeatHold $seatHold,
        protected int $minutesRemaining,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! ($notifiable->notification_preferences['seat_hold_alerts'] ?? true)) {
            return [];
        }

        return ['mail', 'database', TermiiChannel::class, FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vehicle = $this->seatHold->seat->vehicle;

        return (new MailMessage)
            ->subject("Seat {$this->seatHold->seat->seat_number} hold expires in {$this->minutesRemaining} minutes")
            ->greeting('Your seat hold is about to expire')
            ->line("Complete payment now to keep Seat {$this->seatHold->seat->seat_number} on {$vehicle->name}.")
            ->line("Hold expires at {$this->seatHold->expires_at->format('g:i A')}.");
    }

    public function toTermii(object $notifiable): string
    {
        return "CorpsLink: Seat {$this->seatHold->seat->seat_number} hold expires in {$this->minutesRemaining} minutes. Complete payment to keep it.";
    }

    /**
     * @return array{title: string, body: string}
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Seat hold expiring soon',
            'body' => "Seat {$this->seatHold->seat->seat_number} hold expires in {$this->minutesRemaining} minutes.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'seat_hold_expiring',
            'title' => 'Seat hold expiring soon',
            'body' => "Seat {$this->seatHold->seat->seat_number} hold expires in {$this->minutesRemaining} minutes.",
            'seat_hold_id' => $this->seatHold->id,
        ];
    }
}
