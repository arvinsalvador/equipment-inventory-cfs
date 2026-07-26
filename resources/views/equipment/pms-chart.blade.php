@php
    $field = fn (mixed $value): string => filled($value) ? (string) $value : 'Not Recorded';
    $title = 'Maintenance PMS Chart';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - {{ $equipment->property_number ?: $equipment->equipment_code }}</title>
    <style>
        @page { margin: 12mm 13mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        body { background: #eef1f4; color: #111; font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 24px; }
        .toolbar { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin: 0 auto 16px; max-width: 760px; }
        .toolbar a, .toolbar button { background: #1f2937; border: 1px solid #1f2937; border-radius: 6px; color: #fff; cursor: pointer; font-size: 13px; font-weight: 700; padding: 9px 12px; text-decoration: none; }
        .toolbar a.secondary { background: #fff; color: #1f2937; }
        .sheet { background: #fff; margin: 0 auto; max-width: 760px; min-height: 1040px; padding: 30px 34px 42px; }
        .chart-header { min-height: 105px; position: relative; text-align: center; }
        .snsu-logo { height: 62px; left: 0; object-fit: contain; position: absolute; top: 0; width: 62px; }
        .qr-code { height: 70px; object-fit: contain; position: absolute; right: 0; top: 0; width: 70px; }
        .university { font-size: 12px; font-weight: 700; line-height: 1.35; margin: 8px 78px 0; text-transform: uppercase; }
        .campus { display: block; }
        .chart-title { color: #9f1d20; font-size: 17px; font-weight: 700; margin: 17px 78px 0; text-transform: uppercase; }
        .equipment-info { border-collapse: collapse; margin: 9px 0 16px; width: 100%; }
        .equipment-info th, .equipment-info td { font-size: 11px; padding: 3px 5px; text-align: left; vertical-align: top; }
        .equipment-info th { font-weight: 700; width: 92px; }
        .equipment-info td { overflow-wrap: anywhere; }
        .history { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .history thead { display: table-header-group; }
        .history tr { page-break-inside: avoid; }
        .history th, .history td { border: .7px solid #333; font-size: 10px; line-height: 1.35; padding: 7px 8px; text-align: left; vertical-align: top; }
        .history th { background: #f2f2f2; font-size: 10px; font-weight: 700; text-align: center; text-transform: uppercase; }
        .history .date { text-align: center; width: 22%; }
        .history .detail { width: 50%; }
        .history .performer { width: 28%; }
        .empty-row td { color: #555; height: 92px; padding-top: 18px; text-align: center; }
        .page-note { color: #555; font-size: 9px; margin-top: 9px; text-align: right; }
        .pdf-mode { background: #fff; padding: 0; }
        .pdf-mode .sheet { max-width: none; min-height: 0; padding: 0; }
        .pdf-mode .toolbar { display: none; }
        @media print {
            body { background: #fff; padding: 0; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .toolbar { display: none; }
            .sheet { max-width: none; min-height: 0; padding: 0; }
        }
    </style>
</head>
<body class="{{ $mode === 'pdf' ? 'pdf-mode' : '' }}">
    @if ($mode !== 'pdf')
        <nav class="toolbar" aria-label="PMS chart actions">
            <button type="button" onclick="window.print()">Print PMS Chart</button>
            <a class="secondary" href="{{ route('equipment.pms-chart.pdf', $equipment) }}">Download PDF</a>
            <a class="secondary" href="{{ route('equipment.pms-chart.show', $equipment) }}" target="_blank" rel="noopener">Open in New Tab</a>
            <a class="secondary" href="{{ \App\Filament\Resources\Equipment\EquipmentResource::getUrl('view', ['record' => $equipment]) }}">Back to Equipment</a>
        </nav>
    @endif

    <main class="sheet">
        <header class="chart-header">
            @if ($snsuLogo)
                <img class="snsu-logo" src="{{ $snsuLogo }}" alt="SNSU logo">
            @endif
            @if ($qrCode)
                <img class="qr-code" src="{{ $qrCode }}" alt="Equipment QR code">
            @endif
            <div class="university">
                Surigao del Norte State University
                <span class="campus">Del Carmen Campus</span>
            </div>
            <div class="chart-title">Maintenance PMS Chart</div>
        </header>

        <table class="equipment-info" aria-label="Equipment information">
            <tbody>
                <tr>
                    <th>Model</th>
                    <td>{{ $field($equipment->equipment_name) }}</td>
                </tr>
                <tr>
                    <th>Serial Number</th>
                    <td>{{ $field($equipment->serial_number) }}</td>
                </tr>
                <tr>
                    <th>Office</th>
                    <td>{{ $field($equipment->currentLocation?->name) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="history" aria-label="Completed preventive maintenance history">
            <thead>
                <tr>
                    <th class="date">Date</th>
                    <th class="detail">Inspection / Maintenance Detail</th>
                    <th class="performer">Performed By</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="date">{{ $entry['date']->format('M d, Y') }}</td>
                        <td class="detail">{{ $entry['detail'] }}</td>
                        <td class="performer">{{ $entry['performed_by'] }}</td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="3">No completed preventive maintenance has been recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p class="page-note">Automatically generated from completed preventive maintenance history.</p>
    </main>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
