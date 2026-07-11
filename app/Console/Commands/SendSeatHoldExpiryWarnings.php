<?php

namespace App\Console\Commands;

use App\Models\SeatHold;
use App\Notifications\SeatHoldExpiringNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('corpslink:send-seat-hold-expiry-warnings')]
#[Description('Notify corps members whose active seat hold is about to expire.')]
class SendSeatHoldExpiryWarnings extends Command
{
    public function handle(): int
    {
        $warningMinutes = (int) config('corpslink.reminders.seat_hold_warning_minutes');

        $holds = SeatHold::query()
            ->active()
            ->whereNull('expiry_warning_sent_at')
            ->where('expires_at', '<=', now()->addMinutes($warningMinutes))
            ->with(['seat.vehicle', 'user'])
            ->get();

        foreach ($holds as $hold) {
            $minutesRemaining = (int) ceil(now()->diffInSeconds($hold->expires_at, true) / 60);

            $hold->user->notify(new SeatHoldExpiringNotification($hold, $minutesRemaining));

            $hold->update(['expiry_warning_sent_at' => now()]);
        }

        $this->info("Sent {$holds->count()} seat-hold expiry warning(s).");

        return self::SUCCESS;
    }
}
