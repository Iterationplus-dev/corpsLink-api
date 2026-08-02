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

    protected function pendingMonnifyPayment(User $user, Vehicle $vehicle, Seat $seat): Payment
    {
        $hold = SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $user->id]);
        $result = app(CreateBookingAction::class)->handle($user, $hold->id);

        $payment = $result['payment'];
        // gateway_reference set here to mimic a real initialize() call —
        // it's Monnify's transactionReference for our own (unrelated,
        // possibly-abandoned) hosted-checkout attempt, distinct from
        // whatever the native SDK actually settles under.
        $payment->update(['gateway' => PaymentGateway::Monnify, 'gateway_reference' => 'MNFY|HOSTED|000001']);

        return $payment->fresh();
    }

    protected function fakeMonnifyVerify(string $transactionReference, string $status, float $amountNaira): void
    {
        Http::fake([
            'https://sandbox.monnify.com/api/v1/auth/login' => Http::response([
                'requestSuccessful' => true,
                'responseBody' => ['accessToken' => 'mnfy_test_token', 'expiresIn' => 3599],
            ]),
            'https://sandbox.monnify.com/api/v2/merchant/transactions/query*' => Http::response([
                'requestSuccessful' => true,
                'responseBody' => [
                    'paymentStatus' => $status,
                    'amountPaid' => $amountNaira,
                    'currencyCode' => 'NGN',
                    'transactionReference' => $transactionReference,
                ],
            ]),
        ]);
    }

    public function test_monnify_native_sdk_reference_is_used_when_it_differs_from_the_payment_reference(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['fare' => 1500]);
        $seat = $vehicle->seats()->first();
        $payment = $this->pendingMonnifyPayment($user, $vehicle, $seat);

        // The native SDK's own transactionReference — never touched our
        // initialize() endpoint, so it's not what gateway_reference holds.
        $this->fakeMonnifyVerify('MNFY|NATIVE|999999', 'PAID', 1500);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => 'MNFY|NATIVE|999999']);

        $response->assertOk();
        $response->assertJsonPath('status', 'confirmed');
        $this->assertSame('successful', $payment->fresh()->status->value);
        $this->assertSame('MNFY|NATIVE|999999', $payment->fresh()->gateway_reference);

        Http::assertSent(function ($request) {
            if (! str_starts_with($request->url(), 'https://sandbox.monnify.com/api/v2/merchant/transactions/query')) {
                return false;
            }

            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['transactionReference'] ?? null) === 'MNFY|NATIVE|999999';
        });
    }

    public function test_monnify_falls_back_to_stored_gateway_reference_when_client_echoes_back_payment_reference(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['fare' => 1500]);
        $seat = $vehicle->seats()->first();
        $payment = $this->pendingMonnifyPayment($user, $vehicle, $seat);

        $this->fakeMonnifyVerify('MNFY|HOSTED|000001', 'PAID', 1500);

        // Old hosted-checkout contract: the client just echoes back the
        // reference `initialize` gave it, which is Payment::reference, not
        // a real Monnify identifier — must not be trusted as one.
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => $payment->reference]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            if (! str_starts_with($request->url(), 'https://sandbox.monnify.com/api/v2/merchant/transactions/query')) {
                return false;
            }

            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['transactionReference'] ?? null) === 'MNFY|HOSTED|000001';
        });
    }

    public function test_monnify_rejects_a_reference_already_claimed_by_another_successful_payment(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['fare' => 1500]);
        $seats = $vehicle->seats()->orderBy('seat_number')->take(2)->get();

        Payment::factory()->successful()->create([
            'gateway' => PaymentGateway::Monnify,
            'gateway_reference' => 'MNFY|NATIVE|999999',
            'amount' => 1500,
        ]);

        $payment = $this->pendingMonnifyPayment($user, $vehicle, $seats[1]);

        $this->fakeMonnifyVerify('MNFY|NATIVE|999999', 'PAID', 1500);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => 'MNFY|NATIVE|999999']);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'payment_failed');
        $this->assertSame('failed', $payment->fresh()->status->value);
        $this->assertDatabaseMissing('bookings', ['payment_id' => $payment->id, 'status' => 'confirmed']);
    }

    protected function pendingOpayPayment(User $user, Vehicle $vehicle, Seat $seat): Payment
    {
        $hold = SeatHold::factory()->create(['seat_id' => $seat->id, 'user_id' => $user->id]);
        $result = app(CreateBookingAction::class)->handle($user, $hold->id);

        $payment = $result['payment'];
        // gateway_reference set here to mimic a real initialize() call —
        // Opay's echo of our own suffixed attempt reference, for the
        // hosted-checkout path.
        $payment->update([
            'gateway' => PaymentGateway::Opay,
            'gateway_reference' => "{$payment->reference}_abc12345",
        ]);

        return $payment->fresh();
    }

    /**
     * Responds to whichever lookup key OpayGateway::verify() actually sent
     * (`reference` or `orderNo`) with a matching successful transaction, so
     * each test only needs to assert which key/value was used, not fake a
     * different body shape per case.
     */
    protected function fakeOpayVerify(float $amountNaira, string $orderNo = '256611110000000001'): void
    {
        Http::fake([
            'https://testapi.opaycheckout.com/api/v1/international/cashier/status' => function ($request) use ($amountNaira, $orderNo) {
                return Http::response([
                    'code' => '00000',
                    'message' => 'SUCCESSFUL',
                    'data' => [
                        'reference' => $request['reference'] ?? null,
                        'orderNo' => $orderNo,
                        'status' => 'SUCCESS',
                        'amount' => ['total' => (int) round($amountNaira * 100), 'currency' => 'NGN'],
                    ],
                ]);
            },
        ]);
    }

    public function test_opay_native_sdk_ordernumber_is_used_when_it_is_not_our_own_reference_format(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['fare' => 1500]);
        $seat = $vehicle->seats()->first();
        $payment = $this->pendingOpayPayment($user, $vehicle, $seat);

        // A real Opay orderNo never carries our own CL-PAY- prefix — that's
        // how OpayGateway::verify() knows to query by orderNo instead of
        // reference.
        $this->fakeOpayVerify(1500, '256611110000000099');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => '256611110000000099']);

        $response->assertOk();
        $response->assertJsonPath('status', 'confirmed');
        $this->assertSame('successful', $payment->fresh()->status->value);
        $this->assertSame('256611110000000099', $payment->fresh()->gateway_reference);

        Http::assertSent(fn ($request) => $request['orderNo'] === '256611110000000099' && ! isset($request['reference']));
    }

    public function test_opay_falls_back_to_stored_gateway_reference_when_client_echoes_back_payment_reference(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['fare' => 1500]);
        $seat = $vehicle->seats()->first();
        $payment = $this->pendingOpayPayment($user, $vehicle, $seat);

        $this->fakeOpayVerify(1500);

        // Old hosted-checkout contract: the client just echoes back
        // Payment::reference, which must not be trusted as a distinct
        // native-SDK identifier — the currently-working hosted-checkout
        // path depends on gateway_reference (the suffixed attempt
        // reference) still being used here instead.
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => $payment->reference]);

        $response->assertOk();

        Http::assertSent(fn ($request) => $request['reference'] === $payment->gateway_reference && ! isset($request['orderNo']));
    }

    public function test_opay_rejects_an_ordernumber_already_claimed_by_another_successful_payment(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['fare' => 1500]);
        $seats = $vehicle->seats()->orderBy('seat_number')->take(2)->get();

        Payment::factory()->successful()->create([
            'gateway' => PaymentGateway::Opay,
            'gateway_reference' => '256611110000000099',
            'amount' => 1500,
        ]);

        $payment = $this->pendingOpayPayment($user, $vehicle, $seats[1]);

        $this->fakeOpayVerify(1500, '256611110000000099');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['reference' => '256611110000000099']);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'payment_failed');
        $this->assertSame('failed', $payment->fresh()->status->value);
        $this->assertDatabaseMissing('bookings', ['payment_id' => $payment->id, 'status' => 'confirmed']);
    }

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
