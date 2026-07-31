<?php

namespace Tests\Unit\Notifications;

use App\Notifications\Channels\SmsChannel;
use App\Notifications\TwoFactorCodeNotification;
use App\Services\Sms\SmsGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SmsChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned explicitly rather than relying on whatever TERMII_URL/
        // TWILIO_URL happen to be configured in the running environment —
        // the assertSent calls below check requests against these values.
        Config::set('services.termii.url', 'https://v3.api.termii.com');
        Config::set('services.twilio.url', 'https://api.twilio.com/2010-04-01');
    }

    protected function channel(): SmsChannel
    {
        return new SmsChannel(new SmsGatewayResolver);
    }

    public function test_it_sends_via_termii_to_the_normalized_international_number(): void
    {
        Config::set('services.termii.api_key', 'test-key');
        Config::set('services.termii.sender_id', 'CorpsLink');
        Http::fake(['*' => Http::response(['message_id' => 'abc'], 200)]);

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://v3.api.termii.com/api/sms/send'
                && $request['to'] === '2348012345678'
                && $request['api_key'] === 'test-key'
                && str_contains($request['sms'], '1234');
        });
    }

    public function test_it_leaves_an_already_international_number_untouched(): void
    {
        Config::set('services.termii.api_key', 'test-key');
        Http::fake(['*' => Http::response(['message_id' => 'abc'], 200)]);

        $notifiable = (object) ['phone' => '2348012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertSent(fn ($request) => $request['to'] === '2348012345678');
    }

    public function test_it_skips_silently_when_no_provider_is_configured(): void
    {
        Config::set('services.termii.api_key', null);
        Config::set('services.twilio.account_sid', null);
        Http::fake();

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertNothingSent();
    }

    public function test_it_skips_silently_when_notifiable_has_no_phone(): void
    {
        Config::set('services.termii.api_key', 'test-key');
        Http::fake();

        $notifiable = (object) ['phone' => null];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertNothingSent();
    }

    public function test_it_falls_back_to_twilio_when_termii_fails(): void
    {
        Config::set('services.termii.api_key', 'test-key');
        Config::set('services.twilio.account_sid', 'AC_test');
        Config::set('services.twilio.auth_token', 'test-token');
        Config::set('services.twilio.from', '+15005550006');

        Http::fake([
            'https://v3.api.termii.com/*' => Http::response(['error' => 'account pending approval'], 401),
            'https://api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertSent(fn ($request) => $request->url() === 'https://v3.api.termii.com/api/sms/send');
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC_test/Messages.json'
                && $request['To'] === '+2348012345678'
                && $request['From'] === '+15005550006'
                && str_contains($request['Body'], '1234');
        });
    }

    public function test_it_skips_an_unconfigured_termii_and_sends_via_twilio_directly(): void
    {
        Config::set('services.termii.api_key', null);
        Config::set('services.twilio.account_sid', 'AC_test');
        Config::set('services.twilio.auth_token', 'test-token');
        Config::set('services.twilio.from', '+15005550006');

        Http::fake(['https://api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201)]);

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'termii'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC_test/Messages.json');
    }

    public function test_reordering_providers_makes_twilio_primary(): void
    {
        Config::set('corpslink.sms.providers', ['twilio', 'termii']);
        Config::set('services.termii.api_key', 'test-key');
        Config::set('services.twilio.account_sid', 'AC_test');
        Config::set('services.twilio.auth_token', 'test-token');
        Config::set('services.twilio.from', '+15005550006');

        Http::fake(['*' => Http::response(['sid' => 'SM123'], 201)]);

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertSentInOrder([
            fn ($request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC_test/Messages.json',
        ]);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'termii'));
    }

    public function test_it_throws_when_every_configured_provider_fails(): void
    {
        Config::set('services.termii.api_key', 'test-key');
        Config::set('services.twilio.account_sid', 'AC_test');
        Config::set('services.twilio.auth_token', 'test-token');
        Config::set('services.twilio.from', '+15005550006');

        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);

        $this->expectException(RuntimeException::class);

        $notifiable = (object) ['phone' => '08012345678'];
        $this->channel()->send($notifiable, new TwoFactorCodeNotification('1234', 10));
    }
}
