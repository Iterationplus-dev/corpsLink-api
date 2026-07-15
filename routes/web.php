<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Where Paystack/Flutterwave redirect the in-app checkout browser back to
// after a transaction — the actual verification happens through the API
// (POST /payments/{id}/verify, called once the app detects the browser
// closed), so this page is purely a friendly landing spot, not a functional
// step. It renders regardless of whether the transaction succeeded, since
// the gateway redirects here either way and this page has no way to know.
Route::get('/payments/callback', function () {
    return view('payments.callback');
})->name('payments.callback');
