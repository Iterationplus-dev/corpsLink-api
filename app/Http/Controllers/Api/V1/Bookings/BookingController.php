<?php

namespace App\Http\Controllers\Api\V1\Bookings;

use App\Actions\Bookings\CreateBookingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\CreateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    protected const array EAGER_LOAD = ['seat', 'vehicle.institution', 'user', 'payment'];

    public function index(Request $request): JsonResponse
    {
        $bookings = $request->user()->bookings()
            ->with(self::EAGER_LOAD)
            ->latest('booked_at')
            ->get();

        return $this->success(BookingResource::collection($bookings));
    }

    public function store(CreateBookingRequest $request, CreateBookingAction $action): JsonResponse
    {
        $result = $action->handle($request->user(), $request->validated('holdId'));

        $result['booking']->load(self::EAGER_LOAD);
        // Populate the inverse relation in-memory (no extra query — we
        // already have both models) so PaymentResource can expose bookingId.
        $result['payment']->setRelation('booking', $result['booking']);

        return $this->created([
            'booking' => BookingResource::make($result['booking']),
            'payment' => PaymentResource::make($result['payment']),
        ]);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        return $this->success(BookingResource::make($booking->load(self::EAGER_LOAD)));
    }

    public function receipt(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        $booking->load(self::EAGER_LOAD);

        return $this->success([
            'booking' => BookingResource::make($booking),
            'qrPayload' => $booking->reference.'|'
                .($booking->vehicle->institution->id ?? '').'|SEAT'
                .$booking->seat->seat_number.'|'
                .($booking->user->state_code ?? ''),
        ]);
    }
}
