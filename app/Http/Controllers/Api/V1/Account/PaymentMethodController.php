<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * No saved-payment-method concept exists in this app yet — tokenized card
 * storage via Paystack/Flutterwave would be a real feature to build, not a
 * contract-alignment fix. Stubbed as an always-empty list so the frontend's
 * screen renders its empty state instead of failing the request outright.
 */
class PaymentMethodController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success([]);
    }
}
