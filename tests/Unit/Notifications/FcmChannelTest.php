<?php

namespace Tests\Unit\Notifications;

use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\DepartureReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\MessagingError;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery;
use Tests\TestCase;

class FcmChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('firebase.default', 'app');
        Config::set('firebase.projects.app.credentials', '/fake/path/service-account.json');
    }

    protected function notification(): DepartureReminderNotification
    {
        $booking = Booking::factory()->make();

        return new DepartureReminderNotification($booking, 24);
    }

    public function test_it_skips_silently_when_firebase_is_not_configured(): void
    {
        Config::set('firebase.projects.app.credentials', null);

        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create();

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('sendMulticast');
        $this->app->instance(Messaging::class, $messaging);

        (new FcmChannel)->send($user, $this->notification());
    }

    public function test_it_skips_silently_when_notifiable_has_no_device_tokens(): void
    {
        $user = User::factory()->create();

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('sendMulticast');
        $this->app->instance(Messaging::class, $messaging);

        (new FcmChannel)->send($user, $this->notification());
    }

    public function test_it_does_nothing_when_the_notification_has_no_to_fcm_method(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create();

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('sendMulticast');
        $this->app->instance(Messaging::class, $messaging);

        $notification = new class extends Notification {};

        (new FcmChannel)->send($user, $notification);
    }

    public function test_it_sends_a_multicast_message_with_the_notifications_payload(): void
    {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->for($user)->create();

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')
            ->once()
            ->withArgs(function (CloudMessage $message, array $tokens) use ($token) {
                return $tokens === [$token->token];
            })
            ->andReturn(MulticastSendReport::withItems([]));
        $this->app->instance(Messaging::class, $messaging);

        (new FcmChannel)->send($user, $this->notification());
    }

    public function test_it_prunes_invalid_and_unknown_tokens_after_sending(): void
    {
        $user = User::factory()->create();
        $invalid = DeviceToken::factory()->for($user)->create();
        $unknown = DeviceToken::factory()->for($user)->create();
        $valid = DeviceToken::factory()->for($user)->create();

        $invalidTarget = MessageTarget::with(MessageTarget::TOKEN, $invalid->token);
        $unknownTarget = MessageTarget::with(MessageTarget::TOKEN, $unknown->token);
        $validTarget = MessageTarget::with(MessageTarget::TOKEN, $valid->token);

        $report = MulticastSendReport::withItems([
            SendReport::failure($invalidTarget, new MessagingError('The registration token is not a valid FCM registration token.')),
            SendReport::failure($unknownTarget, NotFound::becauseTokenNotFound($unknown->token)),
            SendReport::success($validTarget, []),
        ]);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->once()->andReturn($report);
        $this->app->instance(Messaging::class, $messaging);

        (new FcmChannel)->send($user, $this->notification());

        $this->assertDatabaseMissing('device_tokens', ['id' => $invalid->id]);
        $this->assertDatabaseMissing('device_tokens', ['id' => $unknown->id]);
        $this->assertDatabaseHas('device_tokens', ['id' => $valid->id]);
    }

    public function test_it_logs_and_does_not_throw_when_the_send_fails(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create();

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->once()->andThrow(new MessagingError('boom'));
        $this->app->instance(Messaging::class, $messaging);

        Log::shouldReceive('warning')->once()->with('FCM push failed.', Mockery::on(
            fn ($context) => ($context['error'] ?? null) === 'boom',
        ));

        (new FcmChannel)->send($user, $this->notification());
    }
}
