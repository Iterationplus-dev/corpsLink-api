<?php

namespace Tests\Unit\Notifications;

use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\TwoFactorCodeNotification;
use App\Services\WhatsApp\WhatsAppGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WhatsAppChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned explicitly rather than relying on whatever WHATSAPP_URL/
        // TWILIO_URL happen to be configured in the running environment —
        // the assertSent calls below check requests against these values.
        Config::set('services.whatsapp.url', 'https://graph.facebook.com');
        Config::set('services.whatsapp.api_version', 'v21.0');
        Config::set('services.whatsapp.otp_template', 'otp_code');
        Config::set('services.whatsapp.otp_template_language', 'en_US');
        Config::set('services.twilio.url', 'https://api.twilio.com/2010-04-01');
    }

    protected function channel(): WhatsAppChannel
    {
        return new WhatsAppChannel(new WhatsAppGatewayResolver);
    }

    public function test_it_sends_via_meta_to_the_normalized_international_number(): void
    {
        Config::set('corpslink.whatsapp.providers', ['meta', 'twilio']);
        Config::set('services.whatsapp.phone_number_id', '1234567890');
        Config::set('services.whatsapp.access_token', 'test-token');
        Config::set('services.twilio.account_sid', null);
        Http::fake(['*' => Http::response(['messages' => [['id' => 'abc']]], 200)]);

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v21.0/1234567890/messages'
                && $request['to'] === '2348012345678'
                && $request['type'] === 'template'
                && $request['template']['name'] === 'otp_code'
                && $request['template']['components'][0]['parameters'][0]['text'] === '1234';
        });
    }

    public function test_it_skips_silently_when_no_provider_is_configured(): void
    {
        Config::set('corpslink.whatsapp.providers', ['meta', 'twilio']);
        Config::set('services.whatsapp.phone_number_id', null);
        Config::set('services.whatsapp.access_token', null);
        Config::set('services.twilio.account_sid', null);
        Http::fake();

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertNothingSent();
    }

    public function test_it_skips_silently_when_notifiable_has_no_phone(): void
    {
        Config::set('corpslink.whatsapp.providers', ['meta', 'twilio']);
        Config::set('services.whatsapp.phone_number_id', '1234567890');
        Config::set('services.whatsapp.access_token', 'test-token');
        Http::fake();

        $notifiable = (object) ['phone' => null];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertNothingSent();
    }

    public function test_it_falls_back_to_twilio_when_meta_fails(): void
    {
        Config::set('corpslink.whatsapp.providers', ['meta', 'twilio']);
        Config::set('services.whatsapp.phone_number_id', '1234567890');
        Config::set('services.whatsapp.access_token', 'test-token');
        Config::set('services.twilio.account_sid', 'AC_test');
        Config::set('services.twilio.auth_token', 'test-token');
        Config::set('services.twilio.whatsapp_from', '+15005550006');
        Config::set('services.twilio.whatsapp_content_sid', 'HX_test');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad request']], 400),
            'https://api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v21.0/1234567890/messages');
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC_test/Messages.json'
                && $request['To'] === 'whatsapp:+2348012345678'
                && $request['From'] === 'whatsapp:+15005550006'
                && $request['ContentSid'] === 'HX_test'
                && $request['ContentVariables'] === json_encode(['1' => '1234', '2' => '10']);
        });
    }

    public function test_it_skips_an_unconfigured_meta_and_sends_via_twilio_directly(): void
    {
        Config::set('corpslink.whatsapp.providers', ['meta', 'twilio']);
        Config::set('services.whatsapp.phone_number_id', null);
        Config::set('services.whatsapp.access_token', null);
        Config::set('services.twilio.account_sid', 'AC_test');
        Config::set('services.twilio.auth_token', 'test-token');
        Config::set('services.twilio.whatsapp_from', '+15005550006');
        Config::set('services.twilio.whatsapp_content_sid', 'HX_test');

        Http::fake(['https://api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201)]);

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC_test/Messages.json');
    }

    public function test_reordering_providers_makes_twilio_primary(): void
    {
        Config::set('corpslink.whatsapp.providers', ['twilio', 'meta']);
        Config::set('services.whatsapp.phone_number_id', '1234567890');
        Config::set('services.whatsapp.access_token', 'test-token');
        Config::set('services.twilio.account_sid', 'AC_test');
        Config::set('services.twilio.auth_token', 'test-token');
        Config::set('services.twilio.whatsapp_from', '+15005550006');
        Config::set('services.twilio.whatsapp_content_sid', 'HX_test');

        Http::fake(['*' => Http::response(['sid' => 'SM123'], 201)]);

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        $this->channel()->send($notifiable, $notification);

        Http::assertSentInOrder([
            fn ($request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC_test/Messages.json',
        ]);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_it_throws_when_every_configured_provider_fails(): void
    {
        Config::set('corpslink.whatsapp.providers', ['meta', 'twilio']);
        Config::set('services.whatsapp.phone_number_id', '1234567890');
        Config::set('services.whatsapp.access_token', 'test-token');
        Config::set('services.twilio.account_sid', 'AC_test');
        Config::set('services.twilio.auth_token', 'test-token');
        Config::set('services.twilio.whatsapp_from', '+15005550006');
        Config::set('services.twilio.whatsapp_content_sid', 'HX_test');

        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);

        $this->expectException(RuntimeException::class);

        $notifiable = (object) ['phone' => '08012345678'];
        $this->channel()->send($notifiable, new TwoFactorCodeNotification('1234', 10));
    }
}
