<?php

namespace Tests\Feature\Console;

use App\Models\SeatHold;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\SeatHoldExpiringNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendSeatHoldExpiryWarningsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_holds_expiring_within_the_warning_window(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $user = User::factory()->create();
        $hold = SeatHold::factory()->create([
            'seat_id' => $seat->id,
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(3),
        ]);

        Artisan::call('corpslink:send-seat-hold-expiry-warnings');

        Notification::assertSentTo($user, SeatHoldExpiringNotification::class);
        $this->assertNotNull($hold->fresh()->expiry_warning_sent_at);
    }

    public function test_it_does_not_notify_holds_outside_the_warning_window(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $user = User::factory()->create();
        SeatHold::factory()->create([
            'seat_id' => $seat->id,
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(30),
        ]);

        Artisan::call('corpslink:send-seat-hold-expiry-warnings');

        Notification::assertNotSentTo($user, SeatHoldExpiringNotification::class);
    }

    public function test_it_does_not_resend_a_warning_already_sent(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $user = User::factory()->create();
        SeatHold::factory()->create([
            'seat_id' => $seat->id,
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(3),
        ]);

        Artisan::call('corpslink:send-seat-hold-expiry-warnings');
        Artisan::call('corpslink:send-seat-hold-expiry-warnings');

        Notification::assertSentToTimes($user, SeatHoldExpiringNotification::class, 1);
    }

    public function test_it_skips_users_who_disabled_seat_hold_alerts(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $user = User::factory()->create(['notification_preferences' => ['seat_hold_alerts' => false]]);
        SeatHold::factory()->create([
            'seat_id' => $seat->id,
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(3),
        ]);

        Artisan::call('corpslink:send-seat-hold-expiry-warnings');

        Notification::assertNotSentTo($user, SeatHoldExpiringNotification::class);
    }
}
