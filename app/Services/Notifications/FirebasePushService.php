<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class FirebasePushService
{
    public function __construct(
        private readonly Messaging $messaging
    ) {
    }

    public function sendToUser(User $user, string $title, string $body, array $data): void
    {
        $tokens = $user->devices()
            ->pluck('fcm_token')
            ->filter()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $stringData = collect($data)
            ->map(fn (mixed $value): string => (string) $value)
            ->all();

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($stringData)
            ->withDefaultSounds()
            ->withHighestPossiblePriority();

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);

            $invalidTokens = array_values(array_unique(array_merge(
                $report->invalidTokens(),
                $report->unknownTokens()
            )));

            if ($invalidTokens !== []) {
                $user->devices()
                    ->whereIn('fcm_token', $invalidTokens)
                    ->delete();
            }
        } catch (MessagingException|Throwable $exception) {
            Log::warning('FCM notification could not be sent.', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
