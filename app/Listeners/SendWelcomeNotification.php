<?php

namespace App\Listeners;

use App\Events\RegistrationCompleted;
use App\Notifications\WelcomeNotification;

class SendWelcomeNotification
{
    /**
     * Handle the event. Not queued itself — WelcomeNotification is already
     * a queued notification, so queueing the listener too would just add
     * an extra hop.
     */
    public function handle(RegistrationCompleted $event): void
    {
        $event->user->notify(new WelcomeNotification);
    }
}
