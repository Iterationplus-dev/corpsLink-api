# Push Notifications API

All endpoints below are under the base path `/api/v1` and require authentication via `auth:sanctum` (Bearer token). None are public.

Errors follow the shape: `{ "error": { "code", "message", "fields"? } }`.

---

## 1. Register a device push token

Registers/refreshes a token (FCM/APNs/web push) so the backend knows where to deliver push notifications for the current device.

**POST** `/api/v1/account/device-tokens`

**Request body:**

| Field      | Type   | Required | Rules                                  |
|------------|--------|----------|-----------------------------------------|
| `token`    | string | required | `max:255`                               |
| `platform` | string | required | one of `android`, `ios`, `web`          |

**Behavior notes:**
- Registration is **keyed by token, not by user** (`updateOrCreate(['token' => ...], ...)`). If the same token is submitted while logged in as a different user (shared device, re-login), it silently reassigns ownership to the new user.
- There is no separate "update" endpoint — POST again with the same token to refresh `lastUsedAt` or reassign ownership.

**Response `200`:**

```json
{
  "id": 1,
  "platform": "android",
  "lastUsedAt": "2026-07-24T12:00:00.000000Z"
}
```

---

## 2. Unregister a device push token

**DELETE** `/api/v1/account/device-tokens/{deviceToken}`

**Path param:** `deviceToken` — integer ID of the device token row (not the token string itself).

**Response `200`:** empty body.

**Errors:** `404 not_found` if the token doesn't exist or doesn't belong to the authenticated user (ownership enforced via the user's own tokens, so cross-user delete attempts 404, not 403).

---

## 3. List notifications (notification center / inbox)

**GET** `/api/v1/notifications`

No query params supported — no pagination, hardcoded to the 100 most recent, newest first.

**Response `200`:**

```json
[
  {
    "id": "uuid-string",
    "category": "booking",
    "title": "string|null",
    "body": "string|null",
    "bookingId": "string|null",
    "read": true,
    "createdAt": "2026-07-24T12:00:00.000000Z"
  }
]
```

`category` is one of `booking`, `seat_hold`, `departure`, `promo` — derived server-side from the notification's internal type (`payment_confirmed`→`booking`, `seat_hold_expiring`→`seat_hold`, `departure_reminder`→`departure`, `welcome`→`promo`; unmapped/future types fall back to `promo`).

---

## 4. Mark a single notification read

**PATCH** `/api/v1/notifications/{notification}/read`

**Path param:** `notification` — the notification's UUID.

No request body. **Response `200`:** empty body. Idempotent (no-ops if already read).

**Errors:** `404` if the notification doesn't belong to the authenticated user.

---

## 5. Mark all notifications read

**POST** `/api/v1/notifications/read-all`

No request body. **Response `200`:** empty body.

---

## 6. Get notification preferences

**GET** `/api/v1/notifications/preferences`

**Response `200`:**

```json
{
  "bookingUpdates": true,
  "seatHoldAlerts": true,
  "departureReminders": true,
  "tripChanges": true,
  "tipsAnnouncements": false
}
```

Defaults: all `true` except `tipsAnnouncements` (`false`).

---

## 7. Update notification preferences

**PATCH** `/api/v1/notifications/preferences`

**Request body (all optional — partial update supported):**

| Field                 | Type    | Required | Rules              |
|-----------------------|---------|----------|---------------------|
| `bookingUpdates`      | boolean | optional | `sometimes\|boolean` |
| `seatHoldAlerts`      | boolean | optional | `sometimes\|boolean` |
| `departureReminders`  | boolean | optional | `sometimes\|boolean` |
| `tripChanges`         | boolean | optional | `sometimes\|boolean` |
| `tipsAnnouncements`   | boolean | optional | `sometimes\|boolean` |

Only keys present in the request are changed; others are left as-is (merged, not replaced).

**Response `200`:** same shape as the GET endpoint (full preferences object, after merge).

---

## Open items for follow-up

- No bulk "list my device tokens" endpoint exists — only store and destroy-by-id.
- Actual push delivery (FCM) isn't wired up yet beyond storing tokens — `kreait/firebase-php` is a dependency, but no send job/controller currently dispatches pushes.
- The `/account` route group (including device-token endpoints) is flagged in `routes/api/v1.php` as capabilities the current mobile build doesn't have screens for yet — confirm with the mobile team before building against it.
