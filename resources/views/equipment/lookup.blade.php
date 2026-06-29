<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipment Lookup</title>
    <style>
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f8fafc; color: #111827; }
        main { max-width: 960px; margin: 0 auto; padding: 24px; }
        .actions { margin-bottom: 16px; display: flex; gap: 10px; flex-wrap: wrap; }
        .button { background: #111827; color: #fff; padding: 10px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; }
        .panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        .summary { display: grid; grid-template-columns: minmax(0, 240px) minmax(0, 1fr); gap: 20px; align-items: start; }
        .photo { width: 100%; border-radius: 8px; border: 1px solid #e5e7eb; object-fit: cover; }
        .placeholder { display: grid; place-items: center; min-height: 180px; border: 1px dashed #cbd5e1; border-radius: 8px; color: #64748b; background: #f8fafc; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-top: 18px; }
        .item { border-top: 1px solid #f1f5f9; padding-top: 10px; }
        .label { color: #64748b; font-size: 13px; }
        .value { margin-top: 3px; font-weight: 600; overflow-wrap: anywhere; }
        h1 { margin-top: 0; line-height: 1.15; }
        @media (max-width: 700px) { main { padding: 16px; } .summary { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <div class="actions">
        <a class="button" href="{{ route('equipment.scan') }}">Scan another code</a>
    </div>

    @if (! $equipment)
        <section class="panel">
            <h1>Equipment not found</h1>
            <p>No equipment record matches this QR code. Please check the code and try again.</p>
        </section>
    @else
        <section class="panel">
            <div class="summary">
                <div>
                    @if ($equipment->photo_path)
                        <img class="photo" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($equipment->photo_path) }}" alt="Equipment photo">
                    @else
                        <div class="placeholder">No photo available</div>
                    @endif
                </div>
                <div>
                    <h1>{{ $equipment->equipment_name }}</h1>
                    <div class="grid">
                        <div class="item"><div class="label">Equipment code</div><div class="value">{{ $equipment->equipment_code }}</div></div>
                        <div class="item"><div class="label">Property number</div><div class="value">{{ $equipment->property_number ?: 'None' }}</div></div>
                        <div class="item"><div class="label">Category</div><div class="value">{{ $equipment->category?->name ?: 'None' }}</div></div>
                        <div class="item"><div class="label">Current location</div><div class="value">{{ $equipment->currentLocation?->name ?: 'None' }}</div></div>
                    </div>
                </div>
            </div>

            <div class="grid">
                <div class="item"><div class="label">Condition</div><div class="value">{{ $equipment->condition }}</div></div>
                <div class="item"><div class="label">Operational status</div><div class="value">{{ $equipment->operational_status }}</div></div>
                <div class="item"><div class="label">Last maintenance date</div><div class="value">{{ $equipment->last_maintenance_date?->toFormattedDateString() ?: 'None' }}</div></div>
                <div class="item"><div class="label">Next maintenance date</div><div class="value">{{ $equipment->next_maintenance_date?->toFormattedDateString() ?: 'None' }}</div></div>
                <div class="item"><div class="label">Warranty expiration date</div><div class="value">{{ $equipment->warranty_expiration_date?->toFormattedDateString() ?: 'None' }}</div></div>
                <div class="item"><div class="label">QR generated date/time</div><div class="value">{{ $equipment->qr_code_generated_at?->toDayDateTimeString() ?: 'Not generated' }}</div></div>
            </div>

            <div class="item" style="margin-top: 16px;"><div class="label">Remarks</div><div class="value">{{ $equipment->remarks ?: 'None' }}</div></div>
        </section>
    @endif
</main>
</body>
</html>
