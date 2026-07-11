<?php

namespace Tests\Feature\Payments;

use App\Actions\Bookings\CreateBookingAction;
use App\Enums\PaymentGateway;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function pendingPaystackPayment(User $user, Vehicle $vehicle, Seat $seat): Payment
    {
        $hold = SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $user->id]);
        $result = app(CreateBookingAction::class)->handle($user, $hold->id);

        $payment = $result['payment'];
        $payment->update(['gateway' => PaymentGateway::Paystack]);

        return $payment->fresh();
    }

    protected function fakeVerify(Payment $payment): void
    {
        Http::fake([
            "https://api.paystack.co/transaction/verify/{$payment->reference}" => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => (int) round(((float) $payment->amount) * 100),
                    'currency' => 'NGN',
                    'reference' => $payment->reference,
                ],
            ]),
        ]);
    }

    public function test_valid_signature_finalizes_the_payment(): void
    {
        config(['services.paystack.secret_key' => 'whsec_test_123']);

        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $payment = $this->pendingPaystackPayment($user, $vehicle, $seat);

        $this->fakeVerify($payment);

        $payload = ['event' => 'charge.success', 'data' => ['reference' => $payment->reference]];
        $body = json_encode($payload);
        $signature = hash_hmac('sha512', $body, 'whsec_test_123');

        $response = $this->withHeaders(['X-Paystack-Signature' => $signature])
            ->postJson('/api/v1/payments/webhook/paystack', $payload);

        $response->assertOk();
        $this->assertDatabaseHas('bookings', ['payment_id' => $payment->id, 'seat_id' => $seat->id, 'status' => 'confirmed']);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config(['services.paystack.secret_key' => 'whsec_test_123']);

        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $payment = $this->pendingPaystackPayment($user, $vehicle, $seat);

        $payload = ['event' => 'charge.success', 'data' => ['reference' => $payment->reference]];

        $response = $this->withHeaders(['X-Paystack-Signature' => 'not-the-right-signature'])
            ->postJson('/api/v1/payments/webhook/paystack', $payload);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('bookings', ['payment_id' => $payment->id, 'status' => 'confirmed']);
    }

    public function test_webhook_after_payment_already_confirmed_is_a_noop(): void
    {
        config(['services.paystack.secret_key' => 'whsec_test_123']);

        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $payment = $this->pendingPaystackPayment($user, $vehicle, $seat);

        $this->fakeVerify($payment);

        // Confirmed once already via the client-verify path.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => $payment->reference])
            ->assertOk();
        $this->assertDatabaseCount('bookings', 1);

        $payload = ['event' => 'charge.success', 'data' => ['reference' => $payment->reference]];
        $body = json_encode($payload);
        $signature = hash_hmac('sha512', $body, 'whsec_test_123');

        $response = $this->withHeaders(['X-Paystack-Signature' => $signature])
            ->postJson('/api/v1/payments/webhook/paystack', $payload);

        $response->assertOk();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_unknown_gateway_segment_is_rejected(): void
    {
        $this->postJson('/api/v1/payments/webhook/bank-transfer', [])->assertStatus(401);
    }
}
