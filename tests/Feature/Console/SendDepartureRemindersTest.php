<?php

namespace Tests\Feature\Console;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\DepartureReminderNotification;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendDepartureRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function bookingDepartingIn(User $user, CarbonInterface $when): Booking
    {
        $booking = Booking::factory()->create(['user_id' => $user->id]);
        $booking->vehicle()->update(['departure_at' => $when]);

        return $booking;
    }

    public function test_it_sends_the_one_hour_reminder(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $booking = $this->bookingDepartingIn($user, now()->addMinutes(45));

        Artisan::call('corpslink:send-departure-reminders');

        Notification::assertSentTo(
            $user,
            DepartureReminderNotification::class,
            fn ($notification) => $notification->hoursBefore === 1,
        );
        $this->assertNotNull($booking->fresh()->departure_reminder_1h_sent_at);
    }

    public function test_it_sends_the_twenty_four_hour_reminder(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $booking = $this->bookingDepartingIn($user, now()->addHours(20));

        Artisan::call('corpslink:send-departure-reminders');

        Notification::assertSentTo(
            $user,
            DepartureReminderNotification::class,
            fn ($notification) => $notification->hoursBefore === 24,
        );
        $this->assertNotNull($booking->fresh()->departure_reminder_24h_sent_at);
    }

    public function test_it_does_not_resend_a_reminder_already_sent(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        // Already reminded for the 24h window, so only the 1h window is in play here.
        $booking = $this->bookingDepartingIn($user, now()->addMinutes(45));
        $booking->update(['departure_reminder_24h_sent_at' => now()->subHours(23)]);

        Artisan::call('corpslink:send-departure-reminders');
        Artisan::call('corpslink:send-departure-reminders');

        Notification::assertSentToTimes($user, DepartureReminderNotification::class, 1);
    }

    public function test_it_skips_users_who_disabled_departure_reminders(): void
    {
        Notification::fake();

        $user = User::factory()->create(['notification_preferences' => ['departure_reminders' => false]]);
        $this->bookingDepartingIn($user, now()->addMinutes(45));

        Artisan::call('corpslink:send-departure-reminders');

        Notification::assertNotSentTo($user, DepartureReminderNotification::class);
    }

    public function test_it_does_not_remind_about_a_trip_outside_any_window(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->bookingDepartingIn($user, now()->addDays(5));

        Artisan::call('corpslink:send-departure-reminders');

        Notification::assertNotSentTo($user, DepartureReminderNotification::class);
    }
}
