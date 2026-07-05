<?php

namespace App\Http\Controllers;

use App\Models\BrowserPushSubscription;
use App\Services\BrowserPushPreparationService;
use App\Services\BrowserPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrowserPushSubscriptionController extends Controller
{
    public function store(
        Request $request,
        BrowserPushPreparationService $preparationService,
        BrowserPushService $browserPushService,
    ): JsonResponse {
        abort_unless($request->user() !== null, 401);

        if (! $browserPushService->isConfigured()) {
            return response()->json([
                'status' => 'disabled',
                'message' => 'Browser Push is disabled because VAPID keys are not configured.',
            ], 503);
        }

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys' => ['nullable', 'array'],
            'keys.p256dh' => ['nullable', 'string'],
            'keys.auth' => ['nullable', 'string'],
            'public_key' => ['nullable', 'string'],
            'auth_token' => ['nullable', 'string'],
            'content_encoding' => ['nullable', 'string', 'max:255'],
            'user_agent' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'browser' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $subscription = $preparationService->registerSubscription($request->user(), $data);

        return response()->json([
            'id' => $subscription->id,
            'status' => 'registered',
        ], 201);
    }

    public function destroy(
        Request $request,
        BrowserPushSubscription $subscription,
        BrowserPushPreparationService $preparationService,
    ): JsonResponse {
        abort_unless($request->user() !== null, 401);
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $preparationService->revokeSubscription($subscription);

        return response()->json(['status' => 'revoked']);
    }

    public function destroyCurrent(Request $request, BrowserPushPreparationService $preparationService): JsonResponse
    {
        abort_unless($request->user() !== null, 401);

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        $preparationService->revokeCurrentSubscription($request->user(), $data['endpoint']);

        return response()->json(['status' => 'revoked']);
    }
}
