<?php

namespace Tests\Unit\Notifications;

use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\TwoFactorCodeNotification;
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

        Config::set('services.whatsapp.url', 'https://graph.facebook.com');
        Config::set('services.whatsapp.api_version', 'v21.0');
        Config::set('services.whatsapp.otp_template', 'otp_code');
        Config::set('services.whatsapp.otp_template_language', 'en_US');
    }

    public function test_it_sends_a_template_message_to_the_normalized_international_number(): void
    {
        Config::set('services.whatsapp.phone_number_id', '1234567890');
        Config::set('services.whatsapp.access_token', 'test-token');
        Http::fake(['*' => Http::response(['messages' => [['id' => 'abc']]], 200)]);

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        (new WhatsAppChannel)->send($notifiable, $notification);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v21.0/1234567890/messages'
                && $request['to'] === '2348012345678'
                && $request['type'] === 'template'
                && $request['template']['name'] === 'otp_code'
                && $request['template']['components'][0]['parameters'][0]['text'] === '1234';
        });
    }

    public function test_it_skips_silently_when_credentials_are_not_configured(): void
    {
        Config::set('services.whatsapp.phone_number_id', null);
        Config::set('services.whatsapp.access_token', null);
        Http::fake();

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        (new WhatsAppChannel)->send($notifiable, $notification);

        Http::assertNothingSent();
    }

    public function test_it_skips_silently_when_notifiable_has_no_phone(): void
    {
        Config::set('services.whatsapp.phone_number_id', '1234567890');
        Config::set('services.whatsapp.access_token', 'test-token');
        Http::fake();

        $notifiable = (object) ['phone' => null];
        $notification = new TwoFactorCodeNotification('1234', 10);

        (new WhatsAppChannel)->send($notifiable, $notification);

        Http::assertNothingSent();
    }

    public function test_it_throws_when_the_http_call_fails(): void
    {
        Config::set('services.whatsapp.phone_number_id', '1234567890');
        Config::set('services.whatsapp.access_token', 'test-token');
        Http::fake(['*' => Http::response(['error' => ['message' => 'bad request']], 400)]);

        $this->expectException(RuntimeException::class);

        $notifiable = (object) ['phone' => '08012345678'];
        (new WhatsAppChannel)->send($notifiable, new TwoFactorCodeNotification('1234', 10));
    }
}
