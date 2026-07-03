(() => {
    const secureHostnames = ['localhost', '127.0.0.1', '::1'];
    const isSecurePwaContext = window.location.protocol === 'https:' || secureHostnames.includes(window.location.hostname);
    const installDismissedKey = 'ai-equipment-pwa-install-dismissed';
    let deferredInstallPrompt = null;

    const updateText = (selector, value) => {
        document.querySelectorAll(selector).forEach((element) => {
            element.textContent = value;
        });
    };

    const browserName = () => {
        const agent = navigator.userAgent;

        if (agent.includes('Edg/')) return 'Microsoft Edge';
        if (agent.includes('Chrome/')) return 'Chrome';
        if (agent.includes('Firefox/')) return 'Firefox';
        if (agent.includes('Safari/') && !agent.includes('Chrome/')) return 'Safari';

        return 'Current browser';
    };

    const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    const refreshDeviceInfo = () => {
        updateText('[data-pwa-online-status]', navigator.onLine ? 'Online' : 'Offline');
        updateText('[data-pwa-browser]', browserName());
        updateText('[data-pwa-install-status]', isStandalone() ? 'Installed' : 'Browser tab');
    };

    const cameraUnsupportedMessage = () => {
        if (!isSecurePwaContext) {
            return 'Camera scanning requires HTTPS or localhost. Use manual entry instead.';
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return 'Camera access is not available in this browser. Use manual entry instead.';
        }

        if (!('BarcodeDetector' in window)) {
            return 'Camera QR scanning is not supported by this browser. Use manual entry instead.';
        }

        return null;
    };

    const updateQrAvailability = () => {
        const unsupportedMessage = cameraUnsupportedMessage();

        document.querySelectorAll('[data-pwa-camera-status]').forEach((element) => {
            element.textContent = unsupportedMessage || 'Camera scanner is ready when permission is granted.';
        });

        document.documentElement.classList.toggle('pwa-camera-unavailable', Boolean(unsupportedMessage));
    };

    const registerServiceWorker = () => {
        if (!('serviceWorker' in navigator) || !isSecurePwaContext) {
            return;
        }

        navigator.serviceWorker.register('/service-worker.js').catch(() => {});
    };

    const showInstallPrompt = () => {
        if (!deferredInstallPrompt || localStorage.getItem(installDismissedKey) === '1' || isStandalone()) {
            return;
        }

        document.querySelectorAll('[data-pwa-install-prompt]').forEach((element) => {
            element.hidden = false;
        });
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        showInstallPrompt();
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        updateText('[data-pwa-install-status]', 'Installed');
        document.querySelectorAll('[data-pwa-install-prompt]').forEach((element) => {
            element.hidden = true;
        });
    });

    document.addEventListener('click', async (event) => {
        const installButton = event.target.closest('[data-pwa-install-button]');
        const dismissButton = event.target.closest('[data-pwa-install-dismiss]');
        const qrLink = event.target.closest('[data-pwa-qr-link]');

        if (installButton && deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice.catch(() => null);
            deferredInstallPrompt = null;
            document.querySelectorAll('[data-pwa-install-prompt]').forEach((element) => {
                element.hidden = true;
            });
        }

        if (dismissButton) {
            localStorage.setItem(installDismissedKey, '1');
            document.querySelectorAll('[data-pwa-install-prompt]').forEach((element) => {
                element.hidden = true;
            });
        }

        if (qrLink) {
            const unsupportedMessage = cameraUnsupportedMessage();

            if (unsupportedMessage) {
                event.preventDefault();
                updateText('[data-pwa-camera-status]', unsupportedMessage);
                window.alert(unsupportedMessage);
            }
        }
    });

    window.addEventListener('online', refreshDeviceInfo);
    window.addEventListener('offline', refreshDeviceInfo);
    document.addEventListener('DOMContentLoaded', () => {
        refreshDeviceInfo();
        updateQrAvailability();
        registerServiceWorker();
    });
})();
