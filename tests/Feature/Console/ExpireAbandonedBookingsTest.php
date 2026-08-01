<?php

namespace Tests\Feature\Console;

use App\Actions\Bookings\CreateBookingAction;
use App\Models\SeatHold;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ExpireAbandonedBookingsTest extends TestCase
{
    use RefreshDatabase;

    protected function pendingBooking(User $user, Vehicle $vehicle)
    {
        $seat = $vehicle->seats()->first();
        $hold = SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $user->id]);

        return app(CreateBookingAction::class)->handle($user, $hold->id)['booking'];
    }

    public function test_it_expires_a_pending_booking_whose_hold_has_lapsed(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $booking = $this->pendingBooking($user, $vehicle);

        SeatHold::query()->where('seat_id', $booking->seat_id)->update(['expires_at' => now()->subMinute()]);

        Artisan::call('corpslink:expire-abandoned-bookings');

        $this->assertSame('expired', $booking->fresh()->status->value);
    }

    public function test_it_leaves_a_booking_alone_while_its_hold_is_still_active(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $booking = $this->pendingBooking($user, $vehicle);

        // Hold is still active — user could be mid-checkout on the gateway.

        Artisan::call('corpslink:expire-abandoned-bookings');

        $this->assertSame('pending_payment', $booking->fresh()->status->value);
    }

    public function test_it_leaves_a_recent_pending_booking_alone(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $booking = $this->pendingBooking($user, $vehicle);

        Artisan::call('corpslink:expire-abandoned-bookings');

        $this->assertSame('pending_payment', $booking->fresh()->status->value);
    }

    /**
     * Regression test: the command used to gate expiry on
     * `bookings.created_at + hold-duration`, a clock disconnected from the
     * hold's own `expires_at`. If the hold sat around for a while before
     * CreateBookingAction converted it into a booking, the hold could lapse
     * well before that booking.created_at-based deadline arrived — leaving
     * the seat stuck looking "taken" long after the UI's countdown said it
     * would free up. Expiry must track the hold's real expires_at, not the
     * booking's age.
     */
    public function test_it_expires_a_booking_whose_hold_lapsed_soon_after_a_delayed_booking_creation(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();

        // The hold was claimed a while ago and is nearly done; the booking
        // is only created from it now, right before the hold lapses.
        $hold = SeatHold::factory()->create([
            'seat_id' => $seat->id,
            'user_id' => $user->id,
            'expires_at' => now()->addMinute(),
        ]);
        $booking = app(CreateBookingAction::class)->handle($user, $hold->id)['booking'];

        // The hold lapses moments later — the booking itself is still
        // brand new, well inside the old (buggy) created_at-based window.
        SeatHold::query()->whereKey($hold->id)->update(['expires_at' => now()->subMinute()]);

        Artisan::call('corpslink:expire-abandoned-bookings');

        $this->assertSame('expired', $booking->fresh()->status->value);
    }
}
