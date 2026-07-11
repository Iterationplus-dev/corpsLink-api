<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Throwable;

/**
 * Pushes via Firebase Cloud Messaging. Safely inert without credentials —
 * logs at debug level and returns instead of throwing, so nothing breaks
 * in dev/CI before a Firebase service-account JSON is configured.
 */
class FcmChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        if (! config('firebase.projects.'.config('firebase.default').'.credentials')) {
            Log::debug('FCM push skipped — Firebase credentials not configured.');

            return;
        }

        $tokens = $notifiable->deviceTokens()->pluck('token');

        if ($tokens->isEmpty()) {
            return;
        }

        $payload = $notification->toFcm($notifiable);

        $message = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($payload['title'], $payload['body']))
            ->withData($payload['data'] ?? []);

        try {
            /** @var Messaging $messaging */
            $messaging = app(Messaging::class);
            $messaging->sendMulticast($message, $tokens->all());
        } catch (Throwable $e) {
            // A push failure shouldn't fail the whole notification dispatch
            // the way a failed SMS/email would — stale device tokens are
            // routine, not exceptional.
            Log::warning('FCM push failed.', ['error' => $e->getMessage()]);
        }
    }
}
