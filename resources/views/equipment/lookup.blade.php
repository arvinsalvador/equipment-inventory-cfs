<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipment Lookup</title>
    @include('pwa.meta')
    <style>
        :root { --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --soft: #f8fafc; --brand: #14532d; --brand-soft: #dcfce7; }
        body { margin: 0; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #eef2f7; color: var(--ink); }
        main { max-width: 1180px; margin: 0 auto; padding: 24px; }
        .topbar { display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 18px; flex-wrap: wrap; }
        .button { background: var(--brand); color: #fff; padding: 10px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; font-weight: 700; }
        .shell { background: #fff; border: 1px solid var(--line); border-radius: 8px; box-shadow: 0 14px 35px rgba(15, 23, 42, .08); overflow: hidden; }
        .hero { display: grid; grid-template-columns: minmax(240px, 320px) minmax(0, 1fr); gap: 24px; padding: 24px; border-bottom: 1px solid var(--line); background: linear-gradient(180deg, #fff, #f8fafc); }
        .media { display: grid; gap: 14px; }
        .media-card { border: 1px solid var(--line); border-radius: 8px; padding: 12px; background: #fff; }
        .media-card strong, .label { display: block; color: var(--muted); font-size: 12px; text-transform: uppercase; font-weight: 800; }
        .photo, .qr-code { width: 100%; aspect-ratio: 4 / 3; border-radius: 6px; object-fit: contain; background: #fff; border: 1px solid #f1f5f9; margin-top: 8px; }
        .photo { object-fit: cover; }
        .qr-code { padding: 12px; box-sizing: border-box; }
        .placeholder { display: grid; place-items: center; aspect-ratio: 4 / 3; border: 1px dashed #cbd5e1; border-radius: 6px; color: var(--muted); background: var(--soft); text-align: center; padding: 12px; margin-top: 8px; }
        h1 { margin: 0 0 10px; font-size: clamp(26px, 4vw, 44px); line-height: 1.05; letter-spacing: 0; }
        .sub { display: flex; flex-wrap: wrap; gap: 8px; margin: 14px 0 0; }
        .badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 5px 10px; font-size: 12px; font-weight: 800; background: var(--brand-soft); color: var(--brand); }
        .badge-muted { background: #f1f5f9; color: #334155; }
        .content { padding: 24px; display: grid; gap: 22px; }
        .section-title { margin: 0 0 12px; font-size: 15px; text-transform: uppercase; color: #334155; letter-spacing: .04em; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .item { border: 1px solid var(--line); border-radius: 8px; padding: 13px; background: #fff; min-width: 0; }
        .value { margin-top: 5px; font-weight: 700; overflow-wrap: anywhere; }
        .description { white-space: pre-line; line-height: 1.6; color: #334155; }
        .recommendations { display: grid; gap: 12px; }
        .recommendation { border: 1px solid var(--line); border-radius: 8px; padding: 14px; background: #fff; }
        .recommendation-head { display: flex; justify-content: space-between; gap: 10px; align-items: start; flex-wrap: wrap; }
        .risk-critical { background: #fee2e2; color: #991b1b; }
        .risk-high { background: #fef3c7; color: #92400e; }
        .risk-moderate { background: #dbeafe; color: #1e40af; }
        .risk-low { background: #f3f4f6; color: #374151; }
        @media (max-width: 760px) { main { padding: 14px; } .hero { grid-template-columns: 1fr; padding: 16px; } .content { padding: 16px; } }
    </style>
</head>
<body>
<main>
    <div class="topbar">
        <div><span class="label">Climate Field School Asset Verification</span><strong>Equipment Lookup</strong></div>
        <a class="button" href="{{ route('equipment.scan') }}">Scan another code</a>
    </div>

    @if (! $equipment)
        <section class="shell" style="padding: 24px;"><h1>Equipment not found</h1><p>No equipment record matches this QR code.</p></section>
    @else
        @php
            $openWorkOrders = $equipment->workOrders->whereNotIn('status', ['Completed', 'Verified', 'Cancelled'])->count();
            $maintenanceHistoryCount = $equipment->maintenanceSchedules->count() + $equipment->maintenanceRequests->count() + $equipment->workOrders->count();
            $recommendations = $equipment->openMaintenanceRecommendations->sortBy(fn ($recommendation) => match ($recommendation->risk_level) { 'Critical' => 1, 'High' => 2, 'Moderate' => 3, 'Low' => 4, default => 5 })->values();
            $topRecommendation = $recommendations->first();
        @endphp
        <section class="shell">
            <div class="hero">
                <div class="media">
                    <div class="media-card"><strong>Equipment Photo</strong>@if ($equipment->equipment_photo_url)<img class="photo" src="{{ $equipment->equipment_photo_url }}" alt="Equipment photo">@else<div class="placeholder">No photo available</div>@endif</div>
                    <div class="media-card"><strong>QR Code</strong>@if ($equipment->qr_code_url)<img class="qr-code" src="{{ $equipment->qr_code_url }}" alt="Equipment QR code">@else<div class="placeholder">QR code not generated</div>@endif</div>
                </div>
                <div>
                    <h1>{{ $equipment->equipment_name }}</h1>
                    <div class="sub">
                        <span class="badge">{{ $equipment->category?->account_code ?: 'No Account Code' }}</span>
                        <span class="badge badge-muted">{{ $equipment->condition }}</span>
                        <span class="badge badge-muted">{{ $equipment->operational_status }}</span>
                        <span class="badge badge-muted">{{ $equipment->lifecycleProfile?->lifecycle_status ?: 'Lifecycle not calculated' }}</span>
                    </div>
                    <div class="grid" style="margin-top: 18px;">
                        <div class="item"><div class="label">Equipment Code</div><div class="value">{{ $equipment->equipment_code }}</div></div>
                        <div class="item"><div class="label">Property Number</div><div class="value">{{ $equipment->property_number ?: 'None' }}</div></div>
                        <div class="item"><div class="label">Category</div><div class="value">{{ $equipment->category?->name ?: 'None' }}</div></div>
                        <div class="item"><div class="label">Current Location</div><div class="value">{{ $equipment->currentLocation?->name ?: 'None' }}</div></div>
                        <div class="item"><div class="label">Assigned Office</div><div class="value">{{ $equipment->custodian ?: 'None' }}</div></div>
                    </div>
                </div>
            </div>
            <div class="content">
                <section><h2 class="section-title">Asset Details</h2><div class="grid">
                    <div class="item"><div class="label">Equipment Name / Article</div><div class="value">{{ $equipment->equipment_name }}</div></div>
                    <div class="item"><div class="label">Account Code</div><div class="value">{{ $equipment->category?->account_code ?: 'None' }}</div></div>
                    <div class="item"><div class="label">Acquisition Date</div><div class="value">{{ $equipment->acquisition_date?->toFormattedDateString() ?: 'None' }}</div></div>
                    <div class="item"><div class="label">Acquisition Cost</div><div class="value">{{ $equipment->acquisition_cost ? 'PHP '.number_format((float) $equipment->acquisition_cost, 2) : 'None' }}</div></div>
                    <div class="item"><div class="label">Warranty Expiration</div><div class="value">{{ $equipment->warranty_expiration_date?->toFormattedDateString() ?: 'None' }}</div></div>
                    <div class="item"><div class="label">Operational Status</div><div class="value">{{ $equipment->operational_status }}</div></div>
                    <div class="item"><div class="label">QR generated date/time</div><div class="value">{{ $equipment->qr_code_generated_at?->toDayDateTimeString() ?: 'Not generated' }}</div></div>
                </div></section>
                <section><h2 class="section-title">Maintenance & Lifecycle</h2><div class="grid">
                    <div class="item"><div class="label">Last Maintenance</div><div class="value">{{ $equipment->last_maintenance_date?->toFormattedDateString() ?: 'None' }}</div></div>
                    <div class="item"><div class="label">Next Maintenance</div><div class="value">{{ $equipment->next_maintenance_date?->toFormattedDateString() ?: 'None' }}</div></div>
                    <div class="item"><div class="label">Open Work Orders</div><div class="value">{{ $openWorkOrders }}</div></div>
                    <div class="item"><div class="label">Maintenance History Summary</div><div class="value">{{ $maintenanceHistoryCount }} recorded activity item{{ $maintenanceHistoryCount === 1 ? '' : 's' }}</div></div>
                    <div class="item"><div class="label">Health Score</div><div class="value">{{ $equipment->lifecycleProfile?->health_score ?? 'Not calculated' }}</div></div>
                    <div class="item"><div class="label">Recommendation Badge</div><div class="value">{{ $topRecommendation?->risk_level ?: 'No open recommendation' }}</div></div>
                </div></section>
                <section><h2 class="section-title">Description</h2><div class="item description">{{ $equipment->description ?: 'No description recorded.' }}</div></section>
                <section><h2 class="section-title">AI Recommendation Summary</h2><div class="label" style="margin-bottom: 8px;">Open Recommendations</div><div class="recommendations">
                    @forelse ($recommendations as $recommendation)
                        <article class="recommendation"><div class="recommendation-head"><div><div class="label">{{ $recommendation->rule_key }}</div><div class="value">{{ $recommendation->title }}</div></div><div class="sub" style="margin: 0;"><span class="badge risk-{{ strtolower($recommendation->risk_level) }}">{{ $recommendation->risk_level }}</span><span class="badge badge-muted">{{ $recommendation->action_status ?: 'Pending' }}</span></div></div><div class="item" style="margin-top: 10px;"><div class="label">Suggested Action</div><div class="value">{{ $recommendation->getSuggestedActionLabel() }}</div></div><div class="description" style="margin-top: 10px;">{{ $recommendation->explanation ?: $recommendation->suggestedAction() }}</div></article>
                    @empty
                        <div class="item">No open recommendations.</div>
                    @endforelse
                </div></section>
                <section><h2 class="section-title">Remarks</h2><div class="item description">{{ $equipment->remarks ?: 'None' }}</div></section>
            </div>
        </section>
    @endif
</main>
@include('pwa.mobile-shell')
</body>
</html>
