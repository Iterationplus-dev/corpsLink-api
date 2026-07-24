<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
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
            $report = $messaging->sendMulticast($message, $tokens->all());

            $this->pruneStaleTokens($report->invalidTokens(), $report->unknownTokens());
        } catch (Throwable $e) {
            // A push failure shouldn't fail the whole notification dispatch
            // the way a failed SMS/email would — stale device tokens are
            // routine, not exceptional.
            Log::warning('FCM push failed.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Devices that uninstalled the app or had their token rotated are
     * reported back per-send, not upfront — without pruning them here they'd
     * accumulate forever, wasting an API call on every future push.
     *
     * @param  list<non-empty-string>  $invalidTokens
     * @param  list<non-empty-string>  $unknownTokens
     */
    protected function pruneStaleTokens(array $invalidTokens, array $unknownTokens): void
    {
        $staleTokens = [...$invalidTokens, ...$unknownTokens];

        if ($staleTokens === []) {
            return;
        }

        DeviceToken::query()->whereIn('token', $staleTokens)->delete();
    }
}
