<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Notifications\DepartureReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('corpslink:send-departure-reminders')]
#[Description('Notify corps members whose confirmed trip departs soon (24h and 1h thresholds).')]
class SendDepartureReminders extends Command
{
    public function handle(): int
    {
        $sent = 0;

        foreach (config('corpslink.reminders.departure_hours') as $hours) {
            $column = "departure_reminder_{$hours}h_sent_at";

            $bookings = Booking::query()
                ->where('status', BookingStatus::Confirmed)
                ->whereNull($column)
                ->whereHas('vehicle', fn ($query) => $query
                    ->where('departure_at', '>', now())
                    ->where('departure_at', '<=', now()->addHours($hours)))
                ->with(['vehicle', 'seat', 'user'])
                ->get();

            foreach ($bookings as $booking) {
                $booking->user->notify(new DepartureReminderNotification($booking, $hours));
                $booking->update([$column => now()]);
                $sent++;
            }
        }

        $this->info("Sent {$sent} departure reminder(s).");

        return self::SUCCESS;
    }
}
