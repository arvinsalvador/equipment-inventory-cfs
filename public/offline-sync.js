(() => {
    const queueKey = 'ai-equipment-offline-queue';
    const draftKey = 'ai-equipment-offline-drafts';
    const lastSyncKey = 'ai-equipment-offline-last-sync';
    const syncUrl = '/offline-sync/actions';
    let syncInProgress = false;
    let lastSyncOutcome = null;

    const hasOfflineSyncSurface = () => document.querySelector([
        '[data-offline-sync-surface]',
        '[data-offline-form]',
        '[data-offline-queue-list]',
        '[data-offline-draft-list]',
        '[data-offline-sync-status]',
        '[data-offline-pending-count]',
        '[data-offline-sync-now]',
    ].join(', ')) !== null;

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

    const claimOfflineSyncClick = (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }
    };

    const syncStatus = (status, message = null) => {
        setText('[data-offline-sync-status]', status);
        if (message) {
            setText('[data-offline-sync-message]', message);
        }
        document.documentElement.dataset.offlineSyncStatus = status.toLowerCase().replace(/\s+/g, '-');
    };

    const updateConnectionStatus = () => {
        setText('[data-pwa-online-status]', navigator.onLine ? 'Online' : 'Offline');
        document.documentElement.dataset.offlineConnectionStatus = navigator.onLine ? 'online' : 'offline';
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

    const queueTypeGroup = (type) => {
        if (type.startsWith('work_order.')) return 'work_orders';
        if (type.startsWith('maintenance_request.')) return 'maintenance_requests';
        if (type.startsWith('evidence.')) return 'evidence_uploads';
        if (type.startsWith('equipment.')) return 'equipment_updates';

        return 'other';
    };

    const updateTypeCounts = () => {
        const groups = {
            work_orders: 0,
            maintenance_requests: 0,
            evidence_uploads: 0,
            equipment_updates: 0,
            other: 0,
        };

        getQueue()
            .filter((item) => ['pending', 'failed'].includes(item.status))
            .forEach((item) => {
                groups[queueTypeGroup(item.type)] += 1;
            });

        Object.entries(groups).forEach(([group, count]) => {
            setText(`[data-offline-type-count="${group}"]`, String(count));
        });
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
        lastSyncOutcome = null;

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
        syncStatus('Saved Offline', 'Draft saved locally. Queue it when ready to synchronize.');

        return draft;
    };

    const processQueue = async () => {
        if (syncInProgress || !navigator.onLine) {
            lastSyncOutcome = null;
            render();
            return;
        }

        syncInProgress = true;
        lastSyncOutcome = null;
        syncStatus('Synchronizing', 'Synchronizing offline actions.');

        const queue = getQueue();
        let attempted = false;

        for (const item of queue) {
            if (!['pending', 'failed'].includes(item.status)) {
                continue;
            }

            try {
                attempted = true;
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
                    setQueue(queue);
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

        if (attempted) {
            localStorage.setItem(lastSyncKey, now());
        }

        syncInProgress = false;
        lastSyncOutcome = getQueue().some((item) => item.status === 'failed') ? 'failed' : (attempted ? 'complete' : null);
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

        updateConnectionStatus();
        setText('[data-offline-pending-count]', String(currentCounts.pending));
        setText('[data-offline-failed-count]', String(currentCounts.failed));
        setText('[data-offline-synced-count]', String(currentCounts.synced));
        setText('[data-offline-draft-count]', String(currentCounts.drafts));
        setText('[data-offline-last-sync]', lastSync ? new Date(lastSync).toLocaleString() : 'Never');
        setText('[data-offline-last-sync-short]', lastSync ? new Date(lastSync).toLocaleString() : 'Never');
        updateTypeCounts();

        document.querySelectorAll('[data-offline-banner]').forEach((element) => {
            element.hidden = navigator.onLine;
        });

        document.querySelectorAll('[data-offline-queue-list]').forEach((element) => {
            const filter = element.dataset.offlineFilter || 'pending';
            const items = getQueue().filter((item) => item.status === filter);
            const emptyText = filter === 'pending'
                ? 'All offline changes have been synchronized. No pending actions.'
                : 'No records.';
            element.innerHTML = items.length ? items.map(actionRow).join('') : `<p class="pwa-offline-empty">${emptyText}</p>`;
        });

        document.querySelectorAll('[data-offline-draft-list]').forEach((element) => {
            const drafts = getDrafts();
            element.innerHTML = drafts.length ? drafts.map(draftRow).join('') : '<p class="pwa-offline-empty">No saved drafts.</p>';
        });

        if (!syncInProgress) {
            if (!navigator.onLine) {
                syncStatus('Offline', 'Working offline. Changes will sync when connection returns.');
            } else if (currentCounts.failed > 0 || lastSyncOutcome === 'failed') {
                syncStatus('Sync Failed', `${currentCounts.failed || 1} offline action${(currentCounts.failed || 1) === 1 ? '' : 's'} failed to synchronize.`);
            } else if (lastSyncOutcome === 'complete') {
                syncStatus('Sync Complete', 'All offline actions synchronized.');
            } else if (currentCounts.pending > 0) {
                syncStatus('Pending Synchronization', 'Online — pending actions ready to sync.');
            } else {
                syncStatus('Synchronized', 'All offline changes have been synchronized. No pending actions.');
            }
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
            claimOfflineSyncClick(event);
            const form = draftButton.closest('[data-offline-form]');
            saveDraft(form.dataset.offlineType, serializeForm(form));
            form.reset();
        }

        if (retryButton) {
            claimOfflineSyncClick(event);
            retryItem(retryButton.dataset.offlineRetry);
        }

        if (removeButton) {
            claimOfflineSyncClick(event);
            removeItem(removeButton.dataset.offlineRemove);
        }

        if (queueDraftButton) {
            claimOfflineSyncClick(event);
            const draft = getDrafts().find((item) => item.id === queueDraftButton.dataset.offlineQueueDraft);
            if (draft) {
                queueItem(draft.type, draft.payload);
                removeDraft(draft.id);
            }
        }

        if (removeDraftButton) {
            claimOfflineSyncClick(event);
            removeDraft(removeDraftButton.dataset.offlineRemoveDraft);
        }

        if (syncNow) {
            claimOfflineSyncClick(event);
            processQueue();
        }
    });

    window.addEventListener('online', () => {
        render();
        processQueue();
    });
    window.addEventListener('offline', render);
    window.addEventListener('storage', (event) => {
        if ([queueKey, draftKey, lastSyncKey].includes(event.key)) {
            render();
        }
    });
    document.addEventListener('DOMContentLoaded', () => {
        if (!hasOfflineSyncSurface()) {
            return;
        }

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
