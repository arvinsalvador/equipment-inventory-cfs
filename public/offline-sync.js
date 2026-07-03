(() => {
    const queueKey = 'ai-equipment-offline-queue';
    const draftKey = 'ai-equipment-offline-drafts';
    const lastSyncKey = 'ai-equipment-offline-last-sync';
    const syncUrl = '/offline-sync/actions';
    let syncInProgress = false;

    const now = () => new Date().toISOString();
    const read = (key) => JSON.parse(localStorage.getItem(key) || '[]');
    const write = (key, value) => localStorage.setItem(key, JSON.stringify(value));
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const id = () => `offline-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;

    const labels = {
        'maintenance_request.create': 'Maintenance Request creation',
        'work_order.completion_update': 'Work Order completion update',
        'evidence.metadata': 'Evidence metadata submission',
        'equipment.status_update': 'Equipment status update',
        'maintenance_note.create': 'Maintenance note',
        'inspection_note.create': 'Inspection note',
        'equipment_note.create': 'Equipment note',
    };

    const getQueue = () => read(queueKey);
    const setQueue = (queue) => {
        write(queueKey, queue);
        render();
    };

    const getDrafts = () => read(draftKey);
    const setDrafts = (drafts) => {
        write(draftKey, drafts);
        render();
    };

    const serializeForm = (form) => {
        const data = {};
        new FormData(form).forEach((value, key) => {
            if (value instanceof File) {
                data[key] = value.name;
                return;
            }

            if (String(value).trim() !== '') {
                data[key] = value;
            }
        });

        return data;
    };

    const setText = (selector, value) => {
        document.querySelectorAll(selector).forEach((element) => {
            element.textContent = value;
        });
    };

    const syncStatus = (status) => {
        setText('[data-offline-sync-status]', status);
        document.documentElement.dataset.offlineSyncStatus = status.toLowerCase().replace(/\s+/g, '-');
    };

    const counts = () => {
        const queue = getQueue();
        const drafts = getDrafts();

        return {
            pending: queue.filter((item) => item.status === 'pending').length,
            failed: queue.filter((item) => item.status === 'failed').length,
            synced: queue.filter((item) => item.status === 'synced').length,
            drafts: drafts.length,
        };
    };

    const queueItem = (type, payload, options = {}) => {
        const item = {
            id: options.id || id(),
            type,
            label: labels[type] || type,
            payload,
            base_updated_at: options.baseUpdatedAt || payload.base_updated_at || null,
            status: 'pending',
            attempts: 0,
            created_at: now(),
            updated_at: now(),
            synced_at: null,
            error: null,
            result: null,
        };

        const queue = getQueue();
        if (queue.some((existing) => existing.id === item.id && existing.status === 'synced')) {
            return item;
        }

        queue.push(item);
        setQueue(queue);
        syncStatus(navigator.onLine ? 'Pending Synchronization' : 'Offline');

        if (navigator.onLine) {
            processQueue();
        }

        return item;
    };

    const saveDraft = (type, payload) => {
        const draft = {
            id: id(),
            type,
            label: labels[type] || type,
            payload,
            status: 'Saved Offline',
            created_at: now(),
            updated_at: now(),
        };

        const drafts = getDrafts();
        drafts.push(draft);
        setDrafts(drafts);
        syncStatus('Saved Offline');

        return draft;
    };

    const processQueue = async () => {
        if (syncInProgress || !navigator.onLine) {
            syncStatus(navigator.onLine ? 'Pending Synchronization' : 'Offline');
            return;
        }

        syncInProgress = true;
        syncStatus('Synchronizing');

        const queue = getQueue();

        for (const item of queue) {
            if (!['pending', 'failed'].includes(item.status)) {
                continue;
            }

            try {
                item.attempts += 1;
                item.updated_at = now();

                const response = await fetch(syncUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({
                        client_id: item.id,
                        type: item.type,
                        payload: item.payload,
                        base_updated_at: item.base_updated_at,
                    }),
                });

                const body = await response.json().catch(() => ({}));

                if (response.status === 409) {
                    item.status = 'failed';
                    item.error = body.message || 'Server version changed. Please review before resubmitting.';
                    continue;
                }

                if (!response.ok) {
                    throw new Error(body.message || 'Sync failed. Retry when connection is stable.');
                }

                item.status = 'synced';
                item.synced_at = now();
                item.error = null;
                item.result = body;
            } catch (error) {
                item.status = 'failed';
                item.error = error.message || 'Sync failed. Retry when connection is stable.';
                item.updated_at = now();
            }

            setQueue(queue);
        }

        localStorage.setItem(lastSyncKey, now());
        syncInProgress = false;
        syncStatus(getQueue().some((item) => item.status === 'failed') ? 'Sync Failed' : 'Sync Complete');
        render();
    };

    const retryItem = (itemId) => {
        setQueue(getQueue().map((item) => item.id === itemId && item.status === 'failed'
            ? { ...item, status: 'pending', error: null, updated_at: now() }
            : item));
        processQueue();
    };

    const removeItem = (itemId) => {
        setQueue(getQueue().filter((item) => !(item.id === itemId && item.status === 'failed')));
    };

    const removeDraft = (draftId) => {
        setDrafts(getDrafts().filter((draft) => draft.id !== draftId));
    };

    const detailBlock = (item) => `<details class="pwa-offline-details"><summary>View details</summary><pre>${escapeHtml(JSON.stringify(item.payload, null, 2))}</pre></details>`;

    const actionRow = (item) => {
        const controls = item.status === 'failed'
            ? `<button type="button" data-offline-retry="${item.id}">Retry</button><button type="button" data-offline-remove="${item.id}">Remove</button>`
            : '';

        return `
            <article class="pwa-offline-item pwa-offline-item-${item.status}">
                <div>
                    <strong>${escapeHtml(item.label)}</strong>
                    <span>${escapeHtml(item.status === 'pending' ? 'Pending Synchronization' : item.status)}</span>
                    ${item.error ? `<p>${escapeHtml(item.error)}</p>` : ''}
                    ${detailBlock(item)}
                </div>
                <div class="pwa-offline-item-actions">${controls}</div>
            </article>
        `;
    };

    const draftRow = (draft) => `
        <article class="pwa-offline-item">
            <div>
                <strong>${escapeHtml(draft.label)}</strong>
                <span>Saved Offline</span>
                ${detailBlock(draft)}
            </div>
            <div class="pwa-offline-item-actions">
                <button type="button" data-offline-queue-draft="${draft.id}">Queue</button>
                <button type="button" data-offline-remove-draft="${draft.id}">Remove</button>
            </div>
        </article>
    `;

    const render = () => {
        const currentCounts = counts();
        const lastSync = localStorage.getItem(lastSyncKey);

        setText('[data-offline-pending-count]', String(currentCounts.pending));
        setText('[data-offline-failed-count]', String(currentCounts.failed));
        setText('[data-offline-synced-count]', String(currentCounts.synced));
        setText('[data-offline-draft-count]', String(currentCounts.drafts));
        setText('[data-offline-last-sync]', lastSync ? new Date(lastSync).toLocaleString() : 'Never');

        document.querySelectorAll('[data-offline-banner]').forEach((element) => {
            element.hidden = navigator.onLine;
        });

        document.querySelectorAll('[data-offline-queue-list]').forEach((element) => {
            const filter = element.dataset.offlineFilter || 'pending';
            const items = getQueue().filter((item) => item.status === filter);
            element.innerHTML = items.length ? items.map(actionRow).join('') : '<p class="pwa-offline-empty">No records.</p>';
        });

        document.querySelectorAll('[data-offline-draft-list]').forEach((element) => {
            const drafts = getDrafts();
            element.innerHTML = drafts.length ? drafts.map(draftRow).join('') : '<p class="pwa-offline-empty">No saved drafts.</p>';
        });

        if (!syncInProgress) {
            syncStatus(navigator.onLine ? (currentCounts.failed ? 'Sync Failed' : 'Online') : 'Offline');
        }
    };

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-offline-form]');
        if (!form) return;

        event.preventDefault();
        queueItem(form.dataset.offlineType, serializeForm(form));
        form.reset();
    });

    document.addEventListener('click', (event) => {
        const draftButton = event.target.closest('[data-offline-save-draft]');
        const retryButton = event.target.closest('[data-offline-retry]');
        const removeButton = event.target.closest('[data-offline-remove]');
        const queueDraftButton = event.target.closest('[data-offline-queue-draft]');
        const removeDraftButton = event.target.closest('[data-offline-remove-draft]');
        const syncNow = event.target.closest('[data-offline-sync-now]');

        if (draftButton) {
            const form = draftButton.closest('[data-offline-form]');
            saveDraft(form.dataset.offlineType, serializeForm(form));
            form.reset();
        }

        if (retryButton) retryItem(retryButton.dataset.offlineRetry);
        if (removeButton) removeItem(removeButton.dataset.offlineRemove);

        if (queueDraftButton) {
            const draft = getDrafts().find((item) => item.id === queueDraftButton.dataset.offlineQueueDraft);
            if (draft) {
                queueItem(draft.type, draft.payload);
                removeDraft(draft.id);
            }
        }

        if (removeDraftButton) removeDraft(removeDraftButton.dataset.offlineRemoveDraft);
        if (syncNow) processQueue();
    });

    window.addEventListener('online', processQueue);
    window.addEventListener('offline', render);
    document.addEventListener('DOMContentLoaded', () => {
        render();
        if (navigator.onLine) processQueue();
    });

    window.AiEquipmentOffline = {
        queueItem,
        saveDraft,
        getQueue,
        getDrafts,
        processQueue,
        retryItem,
        removeItem,
    };
})();
