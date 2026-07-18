<?php

use App\Http\Controllers\Api\V1\Account\ChecklistController;
use App\Http\Controllers\Api\V1\Account\DeviceTokenController;
use App\Http\Controllers\Api\V1\Account\EmailController;
use App\Http\Controllers\Api\V1\Account\NextOfKinController;
use App\Http\Controllers\Api\V1\Account\NotificationController;
use App\Http\Controllers\Api\V1\Account\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Account\PasswordController as AccountPasswordController;
use App\Http\Controllers\Api\V1\Account\PaymentMethodController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Account\SeatHoldController;
use App\Http\Controllers\Api\V1\Account\SecurityController;
use App\Http\Controllers\Api\V1\Account\SessionController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\PasswordController as AuthPasswordController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\Bookings\BookingController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Institutions\InstitutionController;
use App\Http\Controllers\Api\V1\Institutions\VehicleController as InstitutionVehicleController;
use App\Http\Controllers\Api\V1\Payments\PaymentController;
use App\Http\Controllers\Api\V1\Support\FaqController;
use App\Http\Controllers\Api\V1\Vehicles\SeatController;
use App\Http\Controllers\Api\V1\Vehicles\VehicleController;
use Illuminate\Support\Facades\Route;

// Public — used by uptime monitors/load balancers to verify the API and its
// database connection are reachable, separate from the app-boot-only /up
// route Laravel registers outside the api/ prefix.
Route::get('health', [HealthController::class, 'show'])->name('health.show');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::prefix('register')->name('register.')->group(function () {
        Route::post('start', [RegistrationController::class, 'start'])->name('start')->middleware('throttle:registration');
        Route::post('change-email', [RegistrationController::class, 'changeEmail'])->name('change-email')->middleware('throttle:otp-resend');
        Route::patch('{registrationId}/school', [RegistrationController::class, 'school'])->name('school');
        Route::patch('{registrationId}/next-of-kin', [RegistrationController::class, 'nextOfKin'])->name('next-of-kin');
        Route::post('{registrationId}/complete', [RegistrationController::class, 'complete'])->name('complete');
    });

    Route::post('otp/verify', [OtpController::class, 'verify'])->name('otp.verify')->middleware('throttle:otp-verify');
    Route::post('otp/resend', [OtpController::class, 'resend'])->name('otp.resend')->middleware('throttle:otp-resend');

    Route::post('login', [AuthController::class, 'login'])->name('login')->middleware('throttle:login');
    Route::post('login/2fa-verify', [TwoFactorController::class, 'verify'])->name('login.2fa-verify')->middleware('throttle:otp-verify');
    Route::post('login/2fa-resend', [TwoFactorController::class, 'resend'])->name('login.2fa-resend')->middleware('throttle:otp-resend');
    Route::post('forgot-password', [AuthPasswordController::class, 'forgot'])->name('forgot-password')->middleware('throttle:password-reset');
    Route::post('reset-password', [AuthPasswordController::class, 'reset'])->name('reset-password')->middleware('throttle:otp-verify');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh')->middleware('auth:sanctum');

    Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth:sanctum');
    Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all')->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->prefix('profile')->name('profile.')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])->name('show');
    Route::patch('/', [ProfileController::class, 'update'])->name('update');
    Route::post('avatar', [ProfileController::class, 'avatar'])->name('avatar');
    Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');

    Route::post('change-password', [AccountPasswordController::class, 'update'])->name('change-password');

    Route::post('change-email/request', [EmailController::class, 'requestChange'])->name('change-email.request')->middleware('throttle:otp-resend');
    Route::post('change-email/confirm', [EmailController::class, 'confirmChange'])->name('change-email.confirm')->middleware('throttle:otp-verify');

    Route::get('emergency-contact', [NextOfKinController::class, 'show'])->name('emergency-contact.show');
    Route::patch('emergency-contact', [NextOfKinController::class, 'update'])->name('emergency-contact.update');
    Route::delete('emergency-contact', [NextOfKinController::class, 'destroy'])->name('emergency-contact.destroy');

    Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::delete('sessions', [SessionController::class, 'destroyAll'])->name('sessions.destroy-all');
    Route::delete('sessions/{token}', [SessionController::class, 'destroy'])->name('sessions.destroy');

    Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
});

Route::middleware('auth:sanctum')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::patch('{notification}/read', [NotificationController::class, 'markRead'])->name('mark-read');
    Route::post('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');

    Route::get('preferences', [NotificationPreferenceController::class, 'show'])->name('preferences.show');
    Route::patch('preferences', [NotificationPreferenceController::class, 'update'])->name('preferences.update');
});

Route::middleware('auth:sanctum')->prefix('checklist')->name('checklist.')->group(function () {
    Route::get('/', [ChecklistController::class, 'index'])->name('index');
    Route::patch('{checklistItem}/toggle', [ChecklistController::class, 'toggle'])->name('toggle');
});

// Extra capabilities this particular frontend build doesn't have screens for
// yet (2FA toggle, seat-hold polling, push device registration) — left under
// the original /account prefix rather than guessed into the contract.
Route::middleware('auth:sanctum')->prefix('account')->name('account.')->group(function () {
    Route::patch('security', [SecurityController::class, 'update'])->name('security.update');

    Route::get('seat-hold', [SeatHoldController::class, 'show'])->name('seat-hold.show');
    Route::delete('seat-hold', [SeatHoldController::class, 'destroy'])->name('seat-hold.destroy');

    Route::post('device-tokens', [DeviceTokenController::class, 'store'])->name('device-tokens.store');
    Route::delete('device-tokens/{deviceToken}', [DeviceTokenController::class, 'destroy'])->name('device-tokens.destroy');
});

// Public — no auth required. The registration wizard's School step needs
// to populate the institution picker before the user has an account/token
// (institutions/{id}/vehicles kept alongside it for the same reason — a
// mid-registration user browsing institutions has no token to attach).
Route::prefix('institutions')->name('institutions.')->group(function () {
    Route::get('/', [InstitutionController::class, 'index'])->name('index');
    Route::get('{institution}', [InstitutionController::class, 'show'])->name('show');
    Route::get('{institution}/vehicles', [InstitutionVehicleController::class, 'index'])->name('vehicles.index');
});

Route::middleware('auth:sanctum')->prefix('vehicles')->name('vehicles.')->group(function () {
    Route::get('{vehicle}', [VehicleController::class, 'show'])->name('show');
    Route::get('{vehicle}/seats', [SeatController::class, 'index'])->name('seats.index');
    Route::post('{vehicle}/seats/{seat}/hold', [SeatController::class, 'hold'])->name('seats.hold')->scopeBindings();
});

Route::middleware('auth:sanctum')->delete('seat-holds/{holdId}', [SeatHoldController::class, 'destroyById'])->name('seat-holds.destroy');

Route::prefix('payments')->name('payments.')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('{payment}', [PaymentController::class, 'show'])->name('show');
        Route::post('{payment}/initialize', [PaymentController::class, 'initialize'])->name('initialize');
        Route::post('{payment}/verify', [PaymentController::class, 'verify'])->name('verify');
    });

    // Gateways can't authenticate as a user — signature verification is
    // this route's own security gate, not auth:sanctum.
    Route::post('webhook/{gateway}', [PaymentController::class, 'webhook'])->name('webhook');
});

Route::middleware('auth:sanctum')->prefix('bookings')->name('bookings.')->group(function () {
    Route::get('/', [BookingController::class, 'index'])->name('index');
    Route::post('/', [BookingController::class, 'store'])->name('store');
    Route::get('{booking}', [BookingController::class, 'show'])->name('show');
    Route::get('{booking}/receipt', [BookingController::class, 'receipt'])->name('receipt');
});

// Public — no auth required, matches the frontend contract ("the app's
// Support tab calls it on load").
Route::prefix('support/faqs')->name('support.faqs.')->group(function () {
    Route::get('/', [FaqController::class, 'index'])->name('index');
    Route::get('{faq}', [FaqController::class, 'show'])->name('show');
});
