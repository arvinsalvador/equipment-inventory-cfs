<x-filament::section>
    <x-slot name="heading">Offline Forms</x-slot>

    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" data-offline-banner hidden>
        <strong>Working offline.</strong>
        <span class="ms-1">Changes will sync when connection returns.</span>
    </div>

    <div class="pwa-offline-sync-summary mt-4">
        <div>
            <p>Connection: <strong data-pwa-online-status>Checking</strong></p>
            <p>Pending Offline Actions: <strong data-offline-pending-count>0</strong></p>
            <p>Failed Syncs: <strong data-offline-failed-count>0</strong></p>
            <p>Last Sync: <strong data-offline-last-sync>Never</strong></p>
        </div>
        <div>
            <p>Sync status: <strong data-offline-sync-status>Checking</strong></p>
            <p data-offline-sync-message>All offline changes have been synchronized. No pending actions.</p>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <form class="pwa-offline-form" data-offline-form data-offline-type="maintenance_request.create">
            <h3>Maintenance Request</h3>
            <label>Equipment ID <input name="equipment_id" inputmode="numeric" required></label>
            <label>Problem Description <textarea name="problem_description" required></textarea></label>
            <label>Severity
                <select name="severity" required>
                    <option>Low</option>
                    <option>Moderate</option>
                    <option>High</option>
                    <option>Critical</option>
                </select>
            </label>
            <label>Remarks <textarea name="remarks"></textarea></label>
            <div class="pwa-offline-form-actions">
                <button type="submit">Queue Request</button>
                <button type="button" data-offline-save-draft>Save Draft</button>
            </div>
        </form>

        <form class="pwa-offline-form" data-offline-form data-offline-type="work_order.completion_update">
            <h3>Work Order Completion Update</h3>
            <label>Work Order ID <input name="work_order_id" inputmode="numeric" required></label>
            <label>Findings <textarea name="findings"></textarea></label>
            <label>Action Performed <textarea name="action_performed"></textarea></label>
            <label>Completion Remarks <textarea name="completion_remarks"></textarea></label>
            <label>Final Equipment Condition <input name="final_equipment_condition"></label>
            <label>Final Operational Status <input name="final_operational_status"></label>
            <div class="pwa-offline-form-actions">
                <button type="submit">Queue Update</button>
                <button type="button" data-offline-save-draft>Save Draft</button>
            </div>
        </form>

        <form class="pwa-offline-form" data-offline-form data-offline-type="evidence.metadata">
            <h3>Evidence Metadata</h3>
            <label>Work Order ID <input name="work_order_id" inputmode="numeric" required></label>
            <label>Evidence Type <input name="evidence_type" value="After maintenance" required></label>
            <label>File Name <input name="file_name"></label>
            <label>Caption <textarea name="caption"></textarea></label>
            <div class="pwa-offline-form-actions">
                <button type="submit">Queue Metadata</button>
                <button type="button" data-offline-save-draft>Save Draft</button>
            </div>
        </form>

        <form class="pwa-offline-form" data-offline-form data-offline-type="equipment.status_update">
            <h3>Equipment Status Update</h3>
            <label>Equipment ID <input name="equipment_id" inputmode="numeric" required></label>
            <label>Condition <input name="condition"></label>
            <label>Operational Status <input name="operational_status"></label>
            <label>Remarks <textarea name="remarks"></textarea></label>
            <div class="pwa-offline-form-actions">
                <button type="submit">Queue Status</button>
                <button type="button" data-offline-save-draft>Save Draft</button>
            </div>
        </form>

        <form class="pwa-offline-form" data-offline-form data-offline-type="maintenance_note.create">
            <h3>Maintenance Notes</h3>
            <label>Work Order ID <input name="work_order_id" inputmode="numeric" required></label>
            <label>Note <textarea name="note" required></textarea></label>
            <div class="pwa-offline-form-actions">
                <button type="submit">Queue Note</button>
                <button type="button" data-offline-save-draft>Save Draft</button>
            </div>
        </form>

        <form class="pwa-offline-form" data-offline-form data-offline-type="equipment_note.create">
            <h3>Equipment Notes</h3>
            <label>Equipment ID <input name="equipment_id" inputmode="numeric" required></label>
            <label>Note <textarea name="note" required></textarea></label>
            <div class="pwa-offline-form-actions">
                <button type="submit">Queue Note</button>
                <button type="button" data-offline-save-draft>Save Draft</button>
            </div>
        </form>
    </div>
</x-filament::section>
