<?php

use App\Http\Controllers\ReportController;
use App\Models\BrowserPushSubscription;
use App\Models\Equipment;
use App\Models\MaintenanceRecommendation;
use App\Services\BrowserPushPreparationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/offline', function () {
    return view('pwa.offline');
})->name('pwa.offline');

Route::get('/equipment/lookup/{qr_identifier}', function (string $qrIdentifier) {
    if (! auth()->check()) {
        return redirect('/admin/login');
    }

    abort_unless(auth()->user()->can('equipment.view'), 403);

    $query = Equipment::query()
        ->with(['category', 'currentLocation'])
        ->where('qr_identifier', $qrIdentifier)
        ->when(
            auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false,
            fn ($query) => $query->with([
                'openMaintenanceRecommendations.linkedWorkOrder',
                'openMaintenanceRecommendations.linkedMaintenanceSchedule',
            ])
        );

    $equipment = $query->first();

    return view('equipment.lookup', [
        'equipment' => $equipment,
        'qrIdentifier' => $qrIdentifier,
    ]);
})->name('equipment.lookup');

Route::get('/equipment/scan', function () {
    if (! auth()->check()) {
        return redirect('/admin/login');
    }

    abort_unless(auth()->user()->can('equipment.view'), 403);

    return view('equipment.scan');
})->name('equipment.scan');

Route::post('/equipment/scan/manual', function (Request $request) {
    if (! auth()->check()) {
        return redirect('/admin/login');
    }

    abort_unless(auth()->user()->can('equipment.view'), 403);

    $data = $request->validate([
        'qr_identifier' => ['required', 'string', 'max:255'],
    ]);

    $value = trim($data['qr_identifier']);
    $path = parse_url($value, PHP_URL_PATH);

    if (is_string($path) && str_starts_with($path, '/equipment/lookup/')) {
        $value = basename($path);
    }

    return redirect()->route('equipment.lookup', ['qr_identifier' => $value]);
})->name('equipment.scan.manual');

Route::middleware('auth')->prefix('reports')->name('reports.')->group(function (): void {
    Route::get('/{report}', [ReportController::class, 'show'])->name('show');
    Route::get('/{report}/print', [ReportController::class, 'print'])->name('print');
    Route::get('/{report}/pdf', [ReportController::class, 'pdf'])->name('pdf');
    Route::get('/{report}/csv', [ReportController::class, 'csv'])->name('csv');
    Route::get('/{report}/excel', [ReportController::class, 'excel'])->name('excel');
});

Route::prefix('browser-push')->name('browser-push.')->group(function (): void {
    Route::post('/subscriptions', function (Request $request, BrowserPushPreparationService $service) {
        abort_unless($request->user() !== null, 401);

        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'public_key' => ['nullable', 'string'],
            'auth_token' => ['nullable', 'string'],
            'content_encoding' => ['nullable', 'string', 'max:255'],
            'user_agent' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $subscription = $service->registerSubscription($request->user(), $data);

        return response()->json([
            'id' => $subscription->id,
            'status' => 'registered',
        ], 201);
    })->name('subscriptions.store');

    Route::delete('/subscriptions/{subscription}', function (
        Request $request,
        BrowserPushSubscription $subscription,
        BrowserPushPreparationService $service
    ) {
        abort_unless($request->user() !== null, 401);
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $service->revokeSubscription($subscription);

        return response()->json(['status' => 'revoked']);
    })->name('subscriptions.destroy');
});
