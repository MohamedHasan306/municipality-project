<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Notifications\FirebasePushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendStatusPushNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;



    public function __construct(
        public readonly int $userId,
        public readonly string $type,
        public readonly int $entityId,
        public readonly string $status,
        public readonly string $title,
        public readonly string $body,
    ) {
    }

    public function handle(FirebasePushService $pushService): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $pushService->sendToUser($user, $this->title, $this->body, [
            'type' => $this->type,
            'id' => (string) $this->entityId,
            'status' => $this->status,
        ]);
    }
}
