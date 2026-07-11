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

class VerifyPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeVerify(string $status, float $amountNaira, string $reference): void
    {
        Http::fake([
            "https://api.paystack.co/transaction/verify/{$reference}" => Http::response([
                'status' => true,
                'data' => [
                    'status' => $status,
                    'amount' => (int) round($amountNaira * 100),
                    'currency' => 'NGN',
                    'reference' => $reference,
                ],
            ]),
        ]);
    }

    protected function pendingPayment(User $user, Vehicle $vehicle, Seat $seat): Payment
    {
        $hold = SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $user->id]);
        $result = app(CreateBookingAction::class)->handle($user, $hold->id);

        $payment = $result['payment'];
        $payment->update(['gateway' => PaymentGateway::Paystack]);

        return $payment->fresh();
    }

    public function test_successful_verification_creates_booking_and_releases_the_hold(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['fare' => 1500]);
        $seat = $vehicle->seats()->first();
        $payment = $this->pendingPayment($user, $vehicle, $seat);

        $this->fakeVerify('success', 1500, $payment->reference);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => $payment->reference]);

        $response->assertOk();
        $response->assertJsonPath('seat.id', $seat->id);
        $response->assertJsonPath('status', 'confirmed');
        $this->assertNotEmpty($response->json('reference'));

        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'seat_id' => $seat->id,
            'payment_id' => $payment->id,
            'status' => 'confirmed',
        ]);
        $this->assertSame('successful', $payment->fresh()->status->value);
        $this->assertNull($user->activeSeatHold()->first());
    }

    public function test_failed_verification_creates_no_confirmed_booking_and_leaves_the_hold(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $payment = $this->pendingPayment($user, $vehicle, $seat);

        $this->fakeVerify('abandoned', (float) $vehicle->fare, $payment->reference);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => $payment->reference]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'payment_failed');

        $this->assertDatabaseMissing('bookings', ['payment_id' => $payment->id, 'status' => 'confirmed']);
        $this->assertSame('failed', $payment->fresh()->status->value);
        $this->assertNotNull($user->activeSeatHold()->first());
    }

    public function test_verifying_twice_is_idempotent(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $payment = $this->pendingPayment($user, $vehicle, $seat);

        $this->fakeVerify('success', (float) $vehicle->fare, $payment->reference);

        $first = $this->actingAs($user, 'sanctum')->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => $payment->reference]);
        $second = $this->actingAs($user, 'sanctum')->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => $payment->reference]);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_seat_taken_by_another_user_during_payment_reassigns_to_nearest_seat(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seats = $vehicle->seats()->orderBy('seat_number')->take(2)->get();
        [$paidForSeat, $neighborSeat] = $seats;

        $payment = $this->pendingPayment($user, $vehicle, $paidForSeat);

        // Someone else grabs the exact seat this payment was for while it's
        // processing — this only becomes possible once the original hold
        // has lapsed, so simulate that by expiring it directly.
        SeatHold::query()->where('seat_id', $paidForSeat->id)->where('user_id', $user->id)
            ->update(['expires_at' => now()->subMinute()]);

        $otherUser = User::factory()->create();
        SeatHold::factory()->create(['seat_id' => $paidForSeat->id, 'user_id' => $otherUser->id]);

        $this->fakeVerify('success', (float) $vehicle->fare, $payment->reference);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => $payment->reference]);

        $response->assertOk();
        $response->assertJsonPath('seat.id', $neighborSeat->id);

        $this->assertDatabaseHas('bookings', ['payment_id' => $payment->id, 'seat_id' => $neighborSeat->id, 'status' => 'confirmed']);
    }

    public function test_whole_vehicle_full_throws_a_support_error(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['capacity' => 4]);
        $seats = $vehicle->seats()->orderBy('seat_number')->get();

        $payment = $this->pendingPayment($user, $vehicle, $seats[0]);

        // The original hold must lapse before anyone else can claim these
        // seats (Seat::isAvailable() only blocks on Confirmed bookings and
        // active holds).
        SeatHold::query()->where('seat_id', $seats[0]->id)->where('user_id', $user->id)
            ->update(['expires_at' => now()->subMinute()]);

        // Every other seat in the vehicle is taken by someone else.
        foreach ($seats->slice(1) as $seat) {
            SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => User::factory()]);
        }
        // And the paid-for seat itself is also now taken.
        SeatHold::factory()->create(['seat_id' => $seats[0]->id, 'user_id' => User::factory()]);

        $this->fakeVerify('success', (float) $vehicle->fare, $payment->reference);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => $payment->reference]);

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'no_seats_available');
        $this->assertDatabaseMissing('bookings', ['payment_id' => $payment->id, 'status' => 'confirmed']);
    }

    public function test_user_cannot_verify_another_users_payment(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $seat = $vehicle->seats()->first();
        $payment = $this->pendingPayment($owner, $vehicle, $seat);

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => $payment->reference])
            ->assertStatus(403);
    }
}
