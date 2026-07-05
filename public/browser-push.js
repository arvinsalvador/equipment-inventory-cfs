(() => {
    const panels = () => Array.from(document.querySelectorAll('[data-browser-push-panel]'));

    if (panels().length === 0) {
        return;
    }

    const secureHostnames = ['localhost', '127.0.0.1', '::1'];
    const isSecureContextForPush = () => window.location.protocol === 'https:' || secureHostnames.includes(window.location.hostname);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const setStatus = (panel, message) => {
        const status = panel.querySelector('[data-browser-push-status]');

        if (status) {
            status.textContent = message;
        }
    };

    const setButtons = (panel, enabled, disabled) => {
        const enableButton = panel.querySelector('[data-browser-push-enable]');
        const disableButton = panel.querySelector('[data-browser-push-disable]');

        if (enableButton) {
            enableButton.disabled = disabled || enabled;
        }

        if (disableButton) {
            disableButton.disabled = disabled || !enabled;
        }
    };

    const browserName = () => {
        const agent = navigator.userAgent;

        if (agent.includes('Edg/')) return 'Microsoft Edge';
        if (agent.includes('Chrome/')) return 'Chrome';
        if (agent.includes('Firefox/')) return 'Firefox';
        if (agent.includes('Safari/') && !agent.includes('Chrome/')) return 'Safari';

        return 'Current browser';
    };

    const urlBase64ToUint8Array = (base64String) => {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; i += 1) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    };

    const unsupportedReason = (panel) => {
        if (panel.dataset.browserPushConfigured !== '1') {
            return 'Browser Push is disabled until VAPID keys are configured.';
        }

        if (!isSecureContextForPush()) {
            return 'Browser Push requires HTTPS or localhost.';
        }

        if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            return 'Browser Push Notifications are not supported on this device.';
        }

        return null;
    };

    const readyRegistration = async () => {
        await navigator.serviceWorker.register('/service-worker.js');

        return navigator.serviceWorker.ready;
    };

    const postSubscription = async (subscription, panel) => {
        const response = await fetch('/push-subscriptions', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                ...subscription.toJSON(),
                content_encoding: 'aes128gcm',
                user_agent: navigator.userAgent,
                device_name: `${browserName()} on ${navigator.platform || 'this device'}`,
                browser: browserName(),
                platform: navigator.platform || null,
                metadata: {
                    source: 'browser-push-controls',
                    display_mode: window.matchMedia('(display-mode: standalone)').matches ? 'standalone' : 'browser',
                },
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));

            throw new Error(payload.message || 'Browser Push registration failed.');
        }

        setStatus(panel, 'Browser Notifications Enabled');
        setButtons(panel, true, false);
    };

    const refreshPanel = async (panel) => {
        const reason = unsupportedReason(panel);

        if (reason) {
            setStatus(panel, reason);
            setButtons(panel, false, true);

            return;
        }

        if (Notification.permission === 'denied') {
            setStatus(panel, 'Permission Denied. Please enable notifications in your browser settings.');
            setButtons(panel, false, true);

            return;
        }

        const registration = await readyRegistration().catch(() => null);
        const subscription = await registration?.pushManager.getSubscription().catch(() => null);

        if (subscription) {
            setStatus(panel, 'Already Registered');
            setButtons(panel, true, false);

            return;
        }

        setStatus(panel, Notification.permission === 'granted' ? 'Disabled' : 'Not Enabled');
        setButtons(panel, false, false);
    };

    const enablePush = async (panel) => {
        const reason = unsupportedReason(panel);

        if (reason) {
            setStatus(panel, reason);
            setButtons(panel, false, true);

            return;
        }

        if (Notification.permission === 'denied') {
            setStatus(panel, 'Please enable notifications in your browser settings.');
            setButtons(panel, false, true);

            return;
        }

        setStatus(panel, 'Requesting notification permission...');
        setButtons(panel, false, true);

        const permission = Notification.permission === 'granted'
            ? 'granted'
            : await Notification.requestPermission();

        if (permission !== 'granted') {
            setStatus(panel, 'Permission Denied. Please enable notifications in your browser settings.');
            setButtons(panel, false, true);

            return;
        }

        const registration = await readyRegistration();
        let subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(panel.dataset.browserPushPublicKey || ''),
            });
        }

        await postSubscription(subscription, panel);
    };

    const disablePush = async (panel) => {
        setStatus(panel, 'Disabling Browser Notifications...');
        setButtons(panel, false, true);

        const registration = await readyRegistration().catch(() => null);
        const subscription = await registration?.pushManager.getSubscription().catch(() => null);

        if (subscription) {
            await fetch('/push-subscriptions/current', {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            }).catch(() => null);

            await subscription.unsubscribe().catch(() => null);
        }

        setStatus(panel, 'Disabled');
        setButtons(panel, false, false);
    };

    document.addEventListener('click', (event) => {
        const enableButton = event.target.closest('[data-browser-push-enable]');
        const disableButton = event.target.closest('[data-browser-push-disable]');

        if (enableButton) {
            enablePush(enableButton.closest('[data-browser-push-panel]')).catch((error) => {
                setStatus(enableButton.closest('[data-browser-push-panel]'), error.message);
                setButtons(enableButton.closest('[data-browser-push-panel]'), false, false);
            });
        }

        if (disableButton) {
            disablePush(disableButton.closest('[data-browser-push-panel]')).catch((error) => {
                setStatus(disableButton.closest('[data-browser-push-panel]'), error.message);
                setButtons(disableButton.closest('[data-browser-push-panel]'), false, false);
            });
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        panels().forEach((panel) => refreshPanel(panel).catch(() => {
            setStatus(panel, 'Browser Push status could not be checked.');
            setButtons(panel, false, false);
        }));
    });
})();
