<?php

namespace App\Actions\Vehicles;

use App\Exceptions\SeatUnavailableException;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HoldSeatAction
{
    /**
     * Claims a seat for the user, atomically releasing any other seat
     * they currently hold (one active hold per corps member).
     *
     * @throws SeatUnavailableException
     */
    public function handle(User $user, Seat $seat): SeatHold
    {
        return DB::transaction(function () use ($user, $seat) {
            // Row lock serializes concurrent claims on this exact seat: the
            // second transaction blocks here until the first commits, then
            // re-reads and correctly sees it's no longer available.
            $locked = Seat::query()->whereKey($seat->id)->lockForUpdate()->firstOrFail();

            $booked = $locked->booking()->exists();

            $heldByAnother = ! $booked && SeatHold::query()->active()
                ->where('seat_id', $locked->id)
                ->where('user_id', '!=', $user->id)
                ->exists();

            if ($booked || $heldByAnother) {
                throw SeatUnavailableException::make($locked->nearestAvailableSeatNumber());
            }

            SeatHold::query()->active()->where('user_id', $user->id)->update(['released_at' => now()]);

            return SeatHold::query()->create([
                'seat_id' => $locked->id,
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(config('corpslink.seat_hold.duration_minutes')),
            ]);
        });
    }
}
