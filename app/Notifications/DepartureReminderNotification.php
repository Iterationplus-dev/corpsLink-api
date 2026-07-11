<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Channels\TermiiChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DepartureReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public int $hoursBefore,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! ($notifiable->notification_preferences['departure_reminders'] ?? true)) {
            return [];
        }

        // The 1-hour reminder is time-critical enough to warrant SMS; the
        // 24-hour one is a lighter heads-up.
        return $this->hoursBefore === 1
            ? ['mail', 'database', TermiiChannel::class, FcmChannel::class]
            : ['mail', 'database', FcmChannel::class];
    }

    protected function summary(): string
    {
        $vehicle = $this->booking->vehicle;
        $when = $this->hoursBefore === 1 ? 'in 1 hour' : 'tomorrow';

        return "{$vehicle->name} departs {$when} from {$vehicle->pickup_point} — Seat {$this->booking->seat->seat_number}.";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Departure reminder — CorpsLink')
            ->greeting('Your trip is coming up')
            ->line($this->summary())
            ->line("Departure: {$this->booking->vehicle->departure_at->format('D d M Y, g:i A')}")
            ->line("Booking reference: {$this->booking->reference}");
    }

    public function toTermii(object $notifiable): string
    {
        return 'CorpsLink: '.$this->summary();
    }

    /**
     * @return array{title: string, body: string}
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Departure reminder',
            'body' => $this->summary(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'departure_reminder',
            'title' => 'Departure reminder',
            'body' => $this->summary(),
            'booking_id' => $this->booking->id,
        ];
    }
}
