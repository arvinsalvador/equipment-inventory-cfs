@php
    $field = fn (mixed $value): string => filled($value) ? (string) $value : 'Not recorded';
    $date = $equipment->acquisition_date ? $equipment->acquisition_date->format('F j, Y') : 'Not recorded';
    $cost = $equipment->acquisition_cost !== null ? 'PHP '.number_format((float) $equipment->acquisition_cost, 2) : 'Not recorded';
    $photoUrl = $equipment->equipment_photo_url;
    $qrUrl = $equipment->qr_code_url;
    $cardTitle = 'Equipment Property Card';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $cardTitle }} - {{ $equipment->property_number ?: $equipment->equipment_code }}</title>
    <style>
        @page { margin: 12mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body { background: #f3f4f6; color: #000; font-family: Arial, sans-serif; margin: 0; padding: 24px; }
        .toolbar { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin: 0 auto 16px; max-width: 980px; }
        .toolbar a, .toolbar button { background: #111827; border: 1px solid #111827; border-radius: 6px; color: #fff; cursor: pointer; font-size: 13px; font-weight: 700; padding: 9px 12px; text-decoration: none; }
        .toolbar a.secondary { background: #fff; color: #111827; }
        .sheet { background: #fff; margin: 0 auto; max-width: 980px; padding: 18px; }
        .property-card { border: 2px solid #000; display: grid; grid-template-rows: auto auto 1fr; min-height: 335px; width: 100%; }
        .card-header { align-items: start; border-bottom: 2px solid #000; display: grid; gap: 14px; grid-template-columns: 106px 1fr 118px; padding: 10px 12px; }
        .logos { align-items: center; display: flex; gap: 8px; }
        .logo { height: 46px; object-fit: contain; width: 46px; }
        .brand { text-align: center; }
        .brand h1 { font-size: clamp(12px, 1.8vw, 15px); letter-spacing: .2px; line-height: 1.25; margin: 8px 0 8px; text-transform: uppercase; }
        .brand p { font-size: 10px; line-height: 1.35; margin: 4px 0; }
        .image-box { align-items: center; border: 3px solid #1f3b5f; display: flex; height: 74px; justify-content: center; overflow: hidden; padding: 4px; text-align: center; width: 74px; }
        .image-box img { display: block; height: 100%; max-width: 100%; object-fit: contain; width: 100%; }
        .image-placeholder { color: #111827; font-size: 10px; line-height: 1.25; overflow-wrap: anywhere; }
        .details { border-bottom: 2px solid #000; display: grid; grid-template-columns: 150px 1fr; }
        .label, .value { border-bottom: 1px solid #000; min-height: 24px; padding: 4px 8px; }
        .label { border-right: 1px solid #000; font-size: clamp(12px, 1.6vw, 16px); font-weight: 700; text-align: right; }
        .value { font-size: clamp(11px, 1.4vw, 14px); overflow-wrap: anywhere; }
        .label:last-of-type, .value:last-of-type { border-bottom: 0; }
        .optional { align-items: stretch; border-bottom: 2px solid #000; display: grid; grid-template-columns: repeat(4, 1fr); }
        .optional div { border-right: 1px solid #000; min-height: 42px; padding: 5px 8px; }
        .optional div:last-child { border-right: 0; }
        .optional strong { display: block; font-size: 9px; text-transform: uppercase; }
        .optional span { display: block; font-size: 11px; line-height: 1.25; overflow-wrap: anywhere; }
        .qr-thumb { height: 38px; object-fit: contain; width: 38px; }
        .signature { align-items: end; display: flex; justify-content: center; min-height: 68px; padding: 16px; text-align: center; }
        .signature-line { border-top: 1px solid #000; min-width: 260px; padding-top: 6px; }
        .signature-title { font-size: 14px; }
        @media print {
            body { background: #fff; padding: 0; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .toolbar { display: none; }
            .sheet { max-width: none; padding: 0; }
            .property-card { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="Property card actions">
        <button type="button" onclick="window.print()">Print Property Card</button>
        <a class="secondary" href="{{ route('equipment.property-card.pdf', $equipment) }}">Download PDF</a>
        <a class="secondary" href="{{ route('equipment.property-card.show', $equipment) }}" target="_blank" rel="noopener">Open in New Tab</a>
        <a class="secondary" href="{{ \App\Filament\Resources\Equipment\EquipmentResource::getUrl('view', ['record' => $equipment]) }}">Back to Equipment</a>
    </nav>

    <main class="sheet">
        <section class="property-card" aria-label="Printable equipment property card">
            <header class="card-header">
                <div class="logos">
                    <img class="logo" src="{{ asset('snsu-logo.png') }}" alt="SNSU logo">
                    <img class="logo" src="{{ asset('bagong-pilipinas-logo.png') }}" alt="Bagong Pilipinas logo">
                </div>
                <div class="brand">
                    <h1>Surigao del Norte State University-Del Carmen Campus</h1>
                    <p>P-6, Brgy. San Jose, Del Carmen, Siargao Island, Surigao del Norte</p>
                    <p>Del Carmen Campus</p>
                </div>
                <div class="image-box">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="Equipment image for {{ $equipment->equipment_name }}">
                    @else
                        <span class="image-placeholder">Equipment<br>Image<br>Here</span>
                    @endif
                </div>
            </header>

            <section class="details">
                <div class="label">Property Number :</div>
                <div class="value">{{ $field($equipment->property_number) }}</div>
                <div class="label">Serial Number :</div>
                <div class="value">{{ $field($equipment->serial_number) }}</div>
                <div class="label">Unit Cost :</div>
                <div class="value">{{ $cost }}</div>
                <div class="label">Date Acquired :</div>
                <div class="value">{{ $date }}</div>
                <div class="label">Office Issued :</div>
                <div class="value">{{ $field($equipment->currentLocation?->name) }}</div>
            </section>

            <section class="optional" aria-label="Additional property details">
                <div>
                    <strong>Equipment Name / Article</strong>
                    <span>{{ $field($equipment->equipment_name) }}</span>
                </div>
                <div>
                    <strong>Account Code</strong>
                    <span>{{ $field($equipment->category?->account_code) }}</span>
                </div>
                <div>
                    <strong>Category</strong>
                    <span>{{ $field($equipment->category?->name) }}</span>
                </div>
                <div>
                    <strong>QR Code</strong>
                    @if ($qrUrl)
                        <img class="qr-thumb" src="{{ $qrUrl }}" alt="Equipment QR code">
                    @else
                        <span>Not recorded</span>
                    @endif
                </div>
            </section>

            <footer class="signature">
                <div class="signature-line">
                    <div class="signature-title">Supply Officer II</div>
                </div>
            </footer>
        </section>
    </main>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
