<?php

namespace App\Services;

use App\Models\Equipment;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;

class EquipmentQrCodeGenerator
{
    public function generate(Equipment $equipment): Equipment
    {
        if (! $equipment->qr_identifier) {
            $equipment->forceFill([
                'qr_identifier' => Equipment::makeQrIdentifier(),
            ])->save();
        }

        $path = "equipment/qr-codes/{$equipment->qr_identifier}.svg";
        $qrCode = new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
        ]));

        Storage::disk('public')->put($path, $qrCode->render($equipment->getQrLookupUrl()), [
            'visibility' => 'public',
        ]);

        $equipment->forceFill([
            'qr_code_path' => $path,
            'qr_code_generated_at' => now(),
        ])->save();

        app(AuditLogService::class)->log('qr_generated', 'Equipment', "QR code generated for equipment {$equipment->equipment_code}.", auth()->user(), $equipment, null, [
            'qr_code_path' => $path,
            'qr_code_generated_at' => $equipment->qr_code_generated_at,
        ]);

        return $equipment->refresh();
    }
}
