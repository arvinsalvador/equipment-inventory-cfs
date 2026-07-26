<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EquipmentPropertyCardController extends Controller
{
    public function show(Request $request, Equipment $equipment): View
    {
        $this->authorizeEquipment($equipment);

        return view('equipment.property-card', [
            'equipment' => $equipment->loadMissing(['category', 'currentLocation']),
            'mode' => 'preview',
            'autoPrint' => false,
        ]);
    }

    public function print(Request $request, Equipment $equipment): View
    {
        $this->authorizeEquipment($equipment);

        return view('equipment.property-card', [
            'equipment' => $equipment->loadMissing(['category', 'currentLocation']),
            'mode' => 'print',
            'autoPrint' => true,
        ]);
    }

    public function pdf(Request $request, Equipment $equipment): View
    {
        $this->authorizeEquipment($equipment);

        return view('equipment.property-card', [
            'equipment' => $equipment->loadMissing(['category', 'currentLocation']),
            'mode' => 'pdf',
            'autoPrint' => false,
        ]);
    }

    private function authorizeEquipment(Equipment $equipment): void
    {
        abort_unless(auth()->user()?->can('view', $equipment), 403);
    }
}
