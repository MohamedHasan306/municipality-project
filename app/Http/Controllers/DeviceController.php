<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponse;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    use ApiResponse;

    public function upsertFcmToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', 'string', Rule::in(['android', 'ios'])],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        UserDevice::query()->updateOrCreate(
            ['fcm_token' => $data['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'last_used_at' => now(),
            ]
        );

        return $this->successResponse(null, 'Device token registered successfully.');
    }

    public function destroyFcmToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:4096'],
        ]);

        $request->user()
            ->devices()
            ->where('fcm_token', $data['fcm_token'])
            ->delete();

        return $this->successResponse(null, 'Device token removed successfully.');
    }
}
