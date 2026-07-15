<?php

namespace Tests\Feature\Payments;

use App\Models\SeatHold;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InitializePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function createBooking(User $user, SeatHold $hold): array
    {
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookings', ['holdId' => $hold->id]);

        $response->assertCreated();

        return [$response->json('booking'), $response->json('payment')];
    }

    public function test_requires_an_active_seat_hold(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookings', ['holdId' => 999999]);

        $response->assertStatus(410);
        $response->assertJsonPath('error.code', 'hold_expired');
    }

    public function test_initializes_a_paystack_payment_for_the_held_seat(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/abc123',
                    'access_code' => 'abc123',
                    'reference' => 'PSK_REF_1',
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['fare' => 2500]);
        $seat = $vehicle->seats()->first();
        $hold = SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $user->id]);

        [$booking, $payment] = $this->createBooking($user, $hold);
        $this->assertSame('pending_payment', $booking['status']);
        $this->assertSame(250000, $payment['amountKobo']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment['id']}/initialize", ['gateway' => 'paystack']);

        $response->assertOk();
        $response->assertJsonPath('authorizationUrl', 'https://checkout.paystack.com/abc123');

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'seat_id' => $seat->id,
            'vehicle_id' => $vehicle->id,
            'gateway' => 'paystack',
            'status' => 'pending',
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $request['callback_url'] === config('services.paystack.callback_url'));
    }

    public function test_initializes_a_flutterwave_payment(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/xyz789'],
            ]),
        ]);

        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $hold = SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $user->id]);

        [, $payment] = $this->createBooking($user, $hold);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment['id']}/initialize", ['gateway' => 'flutterwave']);

        $response->assertOk();
        $response->assertJsonPath('authorizationUrl', 'https://checkout.flutterwave.com/xyz789');

        $this->assertDatabaseHas('payments', ['user_id' => $user->id, 'gateway' => 'flutterwave']);
    }

    public function test_initializes_a_monnify_payment(): void
    {
        Http::fake([
            'https://sandbox.monnify.com/api/v1/auth/login' => Http::response([
                'requestSuccessful' => true,
                'responseBody' => ['accessToken' => 'mnfy_test_token', 'expiresIn' => 3599],
            ]),
            'https://sandbox.monnify.com/api/v1/merchant/transactions/init-transaction' => Http::response([
                'requestSuccessful' => true,
                'responseBody' => [
                    'transactionReference' => 'MNFY|20260713|000001',
                    'checkoutUrl' => 'https://sandbox.sdk.monnify.com/checkout/MNFY|20260713|000001',
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $hold = SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $user->id]);

        [, $payment] = $this->createBooking($user, $hold);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment['id']}/initialize", ['gateway' => 'monnify']);

        $response->assertOk();
        $response->assertJsonPath('authorizationUrl', 'https://sandbox.sdk.monnify.com/checkout/MNFY|20260713|000001');

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'gateway' => 'monnify',
            'gateway_reference' => 'MNFY|20260713|000001',
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.monnify.com/api/v1/auth/login'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode(
                config('services.monnify.api_key').':'.config('services.monnify.secret_key')
            )));

        Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.monnify.com/api/v1/merchant/transactions/init-transaction'
            && $request['contractCode'] === config('services.monnify.contract_code')
            && $request['redirectUrl'] === config('services.monnify.redirect_url')
            // Suffixed, not the bare Payment reference — Monnify rejects
            // init-transaction outright if a paymentReference is reused,
            // which a retried checkout (same Payment row) would otherwise do.
            && $request['paymentReference'] !== $payment['reference']
            && str_starts_with($request['paymentReference'], $payment['reference'].'_')
            && $request->hasHeader('Authorization', 'Bearer mnfy_test_token'));
    }

    public function test_reinitializing_a_monnify_payment_sends_a_different_reference_each_time(): void
    {
        Http::fake([
            'https://sandbox.monnify.com/api/v1/auth/login' => Http::response([
                'requestSuccessful' => true,
                'responseBody' => ['accessToken' => 'mnfy_test_token', 'expiresIn' => 3599],
            ]),
            'https://sandbox.monnify.com/api/v1/merchant/transactions/init-transaction' => Http::sequence()
                ->push([
                    'requestSuccessful' => true,
                    'responseBody' => [
                        'transactionReference' => 'MNFY|attempt-1',
                        'checkoutUrl' => 'https://sandbox.sdk.monnify.com/checkout/attempt-1',
                    ],
                ])
                ->push([
                    'requestSuccessful' => true,
                    'responseBody' => [
                        'transactionReference' => 'MNFY|attempt-2',
                        'checkoutUrl' => 'https://sandbox.sdk.monnify.com/checkout/attempt-2',
                    ],
                ]),
        ]);

        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $hold = SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $user->id]);

        [, $payment] = $this->createBooking($user, $hold);

        // Simulates the checkout session expiring/being abandoned and the
        // user retrying — same Payment row, initialize() called again.
        $first = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment['id']}/initialize", ['gateway' => 'monnify']);
        $second = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment['id']}/initialize", ['gateway' => 'monnify']);

        $first->assertOk();
        $second->assertOk();
        $this->assertNotSame($first->json('authorizationUrl'), $second->json('authorizationUrl'));

        $sentReferences = [];
        Http::assertSent(function ($request) use (&$sentReferences) {
            if ($request->url() === 'https://sandbox.monnify.com/api/v1/merchant/transactions/init-transaction') {
                $sentReferences[] = $request['paymentReference'];
            }

            return true;
        });

        $this->assertCount(2, array_unique($sentReferences));

        $this->assertDatabaseHas('payments', ['id' => $payment['id'], 'gateway_reference' => 'MNFY|attempt-2']);
    }

    public function test_rejects_an_unknown_gateway(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $hold = SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $user->id]);

        [, $payment] = $this->createBooking($user, $hold);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment['id']}/initialize", ['gateway' => 'bank-transfer']);

        $response->assertUnprocessable();
        $this->assertValidationError($response, 'gateway');
    }

    public function test_user_cannot_initialize_another_users_payment(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $hold = SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $owner->id]);

        [, $payment] = $this->createBooking($owner, $hold);

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/v1/payments/{$payment['id']}/initialize", ['gateway' => 'paystack'])
            ->assertStatus(403);
    }
}
