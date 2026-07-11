<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Notifications\PaymentConfirmedNotification;

class SendPaymentConfirmedNotification
{
    /**
     * Not queued itself — PaymentConfirmedNotification is already a
     * queued notification, so queueing the listener too would just add
     * an extra hop.
     */
    public function handle(PaymentConfirmed $event): void
    {
        $event->booking->user->notify(new PaymentConfirmedNotification($event->booking));
    }
}
