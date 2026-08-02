# Payments API — Opay Integration Guide (Frontend)

This documents how to take a booking through payment using **Opay**. The same three endpoints (`show`, `initialize`, `verify`) also work for `paystack`, `flutterwave`, and `monnify` — only the `gateway` value changes — so this doc is written generally where it applies to all four, and calls out Opay specifics explicitly.

All endpoints are under `/api/v1`, require `auth:sanctum` (Bearer token) unless noted, and errors follow `{ "error": { "code", "message", "fields"? } }`.

---

## 1. Flow overview

```
1. Client holds a seat            → POST /api/v1/seat-holds            (existing endpoint)
2. Client creates a booking       → POST /api/v1/bookings               (existing endpoint)
                                     creates a `payment` (status: pending, gateway: null)
                                     and a `booking` (status: pending_payment)
3. Client picks a gateway         → POST /api/v1/payments/{payment}/initialize   { "gateway": "opay" }
                                     returns a checkout URL to open
4. User completes checkout        → in an in-app browser / WebView, on Opay's hosted page
5a. Opay redirects the browser    → to OPAY_RETURN_URL after payment (informational only)
5b. Opay POSTs a webhook          → server reconciles automatically (no client action needed)
6. Client confirms on return      → POST /api/v1/payments/{payment}/verify
                                     returns the confirmed `booking`
```

**Important:** step 5a (the browser redirect) does **not** confirm payment by itself — it's just where the WebView lands. The client must always call step 6 (`verify`) after detecting the redirect, because that's what actually reconciles state with Opay and returns the confirmed booking. The webhook (5b) may beat the client to it — `verify` is idempotent and safe to call regardless of whether the webhook already ran.

---

## 2. Initialize a payment

**POST** `/api/v1/payments/{payment}/initialize`
Auth: `auth:sanctum` — caller must own the payment (403 otherwise).

**Request body:**

| Field     | Type   | Required | Rules                                          |
|-----------|--------|----------|--------------------------------------------------|
| `gateway` | string | required | one of `paystack`, `flutterwave`, `monnify`, `opay` |

```json
{ "gateway": "opay" }
```

**Response `200`:**

```json
{
  "authorizationUrl": "https://sandbox.cashier.opaycheckout.com/checkout/abc123",
  "reference": "CL-PAY-a1b2c3d4e5f6",
  "accessCode": null
}
```

- `authorizationUrl` — open this in an in-app browser / WebView (or `SFSafariViewController` / Chrome Custom Tabs) for the user to complete payment on the gateway's hosted checkout page.
- `reference` — CorpsLink's own payment reference. Keep it around for support/debugging; you don't need to pass it back to the API yourself (the `{payment}` id already identifies which payment you're verifying).
- `accessCode` — **Paystack only**, `null` for every other gateway. Required by Paystack's native mobile SDK (`PaystackNative.chargeCard()` on Android) to charge a card in-app instead of opening `authorizationUrl` in a WebView. If you're using the native in-app charge flow, use this instead of the hosted checkout page; if you're using the hosted checkout page (any gateway, including Paystack), ignore this field entirely.

**Note:** `initialize` can be called again for the same `payment` if the user backs out and retries (e.g. the checkout session expired) — it will return a fresh `authorizationUrl` (and a fresh `accessCode` for Paystack). Each attempt uses a new gateway-side transaction reference internally, so retries are always safe — this used to fail with a "Duplicate Transaction Reference" error on Paystack/Opay before it was fixed server-side.

---

## 3. Detecting checkout completion (client side)

Watch for the WebView navigating to `OPAY_RETURN_URL` (configured server-side; ask backend for the current value, it's environment-specific). When that happens:

1. Close the WebView.
2. Call `verify` (below), regardless of any status query params Opay appended to the return URL — those are not authoritative. The server always re-checks the real status directly with Opay before confirming anything.

If the user abandons the WebView without ever reaching the return URL, you can still call `verify` — a payment that was never completed just returns a `422 payment_failed` error, which is a normal, expected outcome (show a "payment not completed" state, let them retry `initialize`).

---

## 4. Verify a payment

**POST** `/api/v1/payments/{payment}/verify`
Auth: `auth:sanctum` — caller must own the payment (403 otherwise).

**Request body:** none required for Paystack or Flutterwave (an optional `reference` field is accepted for parity but ignored — the `{payment}` id in the URL is what's checked).

**Monnify/Opay native-SDK charges only:** if you charge in-app via Monnify's or Opay's native mobile SDK instead of opening `authorizationUrl` in a WebView, you **must** send the reference the SDK returned as `reference` in this request body — that transaction was never routed through this API's `initialize` call, so it's the only way the server learns which one to check. Sending your own `reference` (echoing back the one this API gave you from `initialize`, or omitting the field) still works and falls back to the hosted-checkout behavior above.

```json
{ "reference": "<transactionReference from Monnify's native SDK>" }
```

```json
{ "reference": "<orderNo from Opay's native SDK charge result>" }
```

**Opay caveat:** send Opay's own `orderNo` from the SDK's result, not this API's own reference — the server tells the two apart by format (its own references always look like `CL-PAY-...`) and will not correctly resolve a native-SDK charge if you send anything else. If Opay's SDK result doesn't give you an `orderNo` at all, ask backend before falling back to this API's own reference — that fallback isn't currently guaranteed to resolve correctly.

**Response `200`** (payment successful — the booking, now confirmed):

```json
{
  "id": 42,
  "reference": "CL-BOOK-xyz789",
  "status": "confirmed",
  "institution": { "id": 3, "name": "NYSC Lagos Camp" },
  "vehicle": { "id": 7, "name": "Bus 12", "route": "Lagos → Abuja", "pickupPoint": "Lagos" },
  "seat": { "id": 101, "label": "14", "position": "window" },
  "departureAt": "2026-07-25T06:00:00.000000Z",
  "fareKobo": 250000,
  "fareDisplay": "₦2,500.00",
  "passengerName": "Jane Doe",
  "stateCode": "LA/26A/1234",
  "callUpNumber": "NYSC/2026/001234",
  "qrPayload": "CL-BOOK-xyz789|3|SEAT14|LA/26A/1234",
  "paymentMethod": "opay",
  "paidAt": "2026-07-24T12:05:00.000000Z",
  "payment": {
    "id": 42,
    "bookingId": 42,
    "gateway": "opay",
    "reference": "CL-PAY-a1b2c3d4e5f6",
    "amountKobo": 250000,
    "amountDisplay": "₦2,500.00",
    "currency": "NGN",
    "status": "successful",
    "failureReason": null,
    "paidAt": "2026-07-24T12:05:00.000000Z"
  },
  "createdAt": "2026-07-24T11:50:00.000000Z"
}
```

Note: `seat.id` may differ from the seat originally selected — if someone else took that exact seat while this payment was processing, the paid booking is automatically reassigned to the nearest available seat on the same vehicle rather than being lost. Always render `booking.seat` from this response, not whatever the client held earlier.

**Error responses:**

| Status | `error.code`         | Meaning                                                                 | Suggested UI |
|--------|-----------------------|--------------------------------------------------------------------------|--------------|
| 422    | `payment_failed`      | Opay reports the payment didn't succeed, or the amount paid didn't match the fare | "Payment not completed" — offer to retry `initialize` |
| 409    | `no_seats_available`  | Payment succeeded but the vehicle is completely full (rare race) | "Payment received — contact support", do NOT let them retry payment |
| 403    | —                      | Not this user's payment | Shouldn't happen in normal flow — treat as a bug |
| 404    | `not_found`           | Payment id doesn't exist | Shouldn't happen in normal flow — treat as a bug |

`verify` is **idempotent** — calling it multiple times (e.g. once from a redirect handler and again from a manual "I've paid" button) is safe and returns the same confirmed booking without reprocessing.

---

## 5. Checking payment status directly

**GET** `/api/v1/payments/{payment}`
Auth: `auth:sanctum` — caller must own the payment.

Returns the `PaymentResource` shape shown nested above (`gateway`, `reference`, `amountKobo`/`amountDisplay`, `currency`, `status`, `failureReason`, `paidAt`). Useful for a "payment history" / "receipt" screen, or for polling status without re-triggering verification against the gateway.

`status` values: `pending`, `successful`, `failed`.

---

## 6. What the client does NOT need to do

- **No webhook handling on the client** — `POST /api/v1/payments/webhook/opay` is a server-to-server endpoint Opay calls directly; it's not reachable from a mobile/web client and requires no client code.
- **No signature verification** — that's a server responsibility.
- **No amount calculation** — the server computes the fare from the seat/vehicle server-side; the client never sends an amount.

---

## 7. Quick reference — gateway values

| Gateway       | `gateway` value |
|---------------|------------------|
| Paystack      | `paystack`       |
| Flutterwave   | `flutterwave`    |
| Monnify       | `monnify`        |
| **Opay**      | `opay`           |

Sending any other value returns `422` with a validation error on the `gateway` field.
