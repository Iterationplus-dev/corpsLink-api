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

        // Simulate enough time passing that both the booking and its
        // backing hold are old, then let the hold actually lapse.
        // created_at isn't mass-fillable, hence forceFill.
        $booking->forceFill(['created_at' => now()->subMinutes(config('corpslink.seat_hold.duration_minutes') + 5)])->save();
        SeatHold::query()->where('seat_id', $booking->seat_id)->update(['expires_at' => now()->subMinute()]);

        Artisan::call('corpslink:expire-abandoned-bookings');

        $this->assertSame('expired', $booking->fresh()->status->value);
    }

    public function test_it_leaves_a_booking_alone_while_its_hold_is_still_active(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $booking = $this->pendingBooking($user, $vehicle);

        $booking->forceFill(['created_at' => now()->subMinutes(config('corpslink.seat_hold.duration_minutes') + 5)])->save();
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
}
