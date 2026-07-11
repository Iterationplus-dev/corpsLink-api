<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vehicle = $this->booking->vehicle;
        $seat = $this->booking->seat;

        return (new MailMessage)
            ->subject("Seat confirmed! — {$this->booking->reference}")
            ->greeting('Your seat is booked 🎉')
            ->line("Payment of {$this->booking->fare} {$this->booking->payment->currency} received.")
            ->line("{$vehicle->name} · Seat {$seat->seat_number} · {$vehicle->pickup_point} → {$vehicle->destination}")
            ->line('Departs: '.$vehicle->departure_at->format('D d M Y, g:i A'))
            ->line("Booking reference: {$this->booking->reference}")
            ->line('Show this reference at boarding for manifest check-in.');
    }

    /**
     * @return array{title: string, body: string}
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Seat confirmed!',
            'body' => "Payment of {$this->booking->fare} {$this->booking->payment->currency} received — Seat {$this->booking->seat->seat_number}.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_confirmed',
            'title' => 'Seat confirmed!',
            'body' => "Payment of {$this->booking->fare} {$this->booking->payment->currency} received.",
            'booking_id' => $this->booking->id,
        ];
    }
}
