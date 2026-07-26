<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Services\EquipmentPmsChartService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class EquipmentPmsChartController extends Controller
{
    public function __construct(private readonly EquipmentPmsChartService $chartService) {}

    public function show(Equipment $equipment): View
    {
        return $this->view($equipment, 'preview');
    }

    public function print(Equipment $equipment): View
    {
        return $this->view($equipment, 'print', true);
    }

    public function pdf(Equipment $equipment): Response
    {
        $data = $this->data($equipment, 'pdf');
        $filename = 'pms-chart-'.$this->safeFilename($equipment).'.pdf';

        return Pdf::loadView('equipment.pms-chart', $data)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    private function view(Equipment $equipment, string $mode, bool $autoPrint = false): View
    {
        return view('equipment.pms-chart', array_merge($this->data($equipment, $mode), [
            'autoPrint' => $autoPrint,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Equipment $equipment, string $mode): array
    {
        $this->authorizeEquipment($equipment);
        $equipment->loadMissing('currentLocation');

        return [
            'equipment' => $equipment,
            'entries' => $this->chartService->entriesFor($equipment),
            'mode' => $mode,
            'autoPrint' => false,
            'snsuLogo' => $mode === 'pdf'
                ? $this->publicImageDataUri(public_path('snsu-logo.png'))
                : asset('snsu-logo.png'),
            'qrCode' => $mode === 'pdf'
                ? $this->publicDiskImageDataUri($equipment->normalized_qr_code_path)
                : $equipment->qr_code_url,
        ];
    }

    private function authorizeEquipment(Equipment $equipment): void
    {
        abort_unless(auth()->user()?->can('view', $equipment), 403);
    }

    private function safeFilename(Equipment $equipment): string
    {
        $identifier = $equipment->property_number ?: $equipment->equipment_code;

        return preg_replace('/[^A-Za-z0-9_-]+/', '-', $identifier) ?: (string) $equipment->getKey();
    }

    private function publicDiskImageDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $this->dataUri(
            Storage::disk('public')->mimeType($path) ?: 'image/png',
            Storage::disk('public')->get($path)
        );
    }

    private function publicImageDataUri(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        return $this->dataUri(mime_content_type($path) ?: 'image/png', file_get_contents($path));
    }

    private function dataUri(string $mimeType, string $contents): string
    {
        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }
}
