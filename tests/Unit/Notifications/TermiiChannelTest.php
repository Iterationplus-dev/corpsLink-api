<?php

namespace Tests\Unit\Notifications;

use App\Notifications\Channels\TermiiChannel;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TermiiChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_to_the_normalized_international_number(): void
    {
        Config::set('services.termii.api_key', 'test-key');
        Config::set('services.termii.sender_id', 'CorpsLink');
        Http::fake(['*' => Http::response(['message_id' => 'abc'], 200)]);

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        (new TermiiChannel)->send($notifiable, $notification);

        Http::assertSent(function ($request) {
            return $request['to'] === '2348012345678'
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

        (new TermiiChannel)->send($notifiable, $notification);

        Http::assertSent(fn ($request) => $request['to'] === '2348012345678');
    }

    public function test_it_skips_silently_when_no_api_key_is_configured(): void
    {
        Config::set('services.termii.api_key', null);
        Http::fake();

        $notifiable = (object) ['phone' => '08012345678'];
        $notification = new TwoFactorCodeNotification('1234', 10);

        (new TermiiChannel)->send($notifiable, $notification);

        Http::assertNothingSent();
    }

    public function test_it_skips_silently_when_notifiable_has_no_phone(): void
    {
        Config::set('services.termii.api_key', 'test-key');
        Http::fake();

        $notifiable = (object) ['phone' => null];
        $notification = new TwoFactorCodeNotification('1234', 10);

        (new TermiiChannel)->send($notifiable, $notification);

        Http::assertNothingSent();
    }

    public function test_it_throws_when_the_http_call_fails(): void
    {
        Config::set('services.termii.api_key', 'test-key');
        Http::fake(['*' => Http::response(['error' => 'bad request'], 400)]);

        $this->expectException(RuntimeException::class);

        $notifiable = (object) ['phone' => '08012345678'];
        (new TermiiChannel)->send($notifiable, new TwoFactorCodeNotification('1234', 10));
    }
}
