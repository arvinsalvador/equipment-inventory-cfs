<?php

use App\Models\Equipment;
use App\Models\MaintenanceRecommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
            fn ($query) => $query->with('openMaintenanceRecommendations')
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
