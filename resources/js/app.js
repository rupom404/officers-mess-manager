import './bootstrap';
import Chart from 'chart.js/auto';

// Global init helper for dashboard charts (Phase 4 Plan 4.3).
// Blade passes data via @json into a small inline script that calls this.
// Destroy-before-recreate is MANDATORY (Pitfall 2: canvas reuse leaks memory).
window.initDashboardChart = function (canvasId, config) {
    const el = document.getElementById(canvasId);
    if (!el) return null;

    if (el.__chart) {
        el.__chart.destroy();
    }

    el.__chart = new Chart(el.getContext('2d'), {
        type: config.type,
        data: config.data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: { mode: 'index', intersect: false },
            },
            // Doughnut/pie/polarArea charts reject cartesian scales — only emit
            // them for cartesian types (line/bar).
            ...(['line', 'bar'].includes(config.type) ? {
                scales: {
                    x: { ticks: { font: { size: 10 }, maxRotation: 45 } },
                    y: { ticks: { font: { size: 10 }, beginAtZero: true } },
                },
            } : {}),
        },
    });

    return el.__chart;
};

// Progressive Web App setup.
// Financial pages/data are never cached by the service worker; the app continues
// to read live server data. The install prompt is exposed through the header.
let deferredInstallPrompt = null;

const isStandaloneMode = () => (
    window.matchMedia?.('(display-mode: standalone)').matches ||
    window.navigator.standalone === true
);

const setInstallButtonState = (button, { enabled = false, visible = true } = {}) => {
    if (!button) return;

    button.classList.toggle('hidden', !visible);
    button.disabled = !enabled;
    button.setAttribute('aria-disabled', String(!enabled));
    button.title = enabled
        ? 'Install Officers\' Mess Manager as an app'
        : 'Install becomes available when your browser allows this site to be installed';

    if (enabled) {
        button.classList.remove('cursor-not-allowed', 'opacity-60');
        button.classList.add('cursor-pointer');
    } else {
        button.classList.remove('cursor-pointer');
        button.classList.add('cursor-not-allowed', 'opacity-60');
    }
};

const createInstallButton = () => {
    if (isStandaloneMode()) return null;

    const headerActions = document.querySelector('header > div:last-child');
    if (!headerActions || document.getElementById('pwa-install-button')) return document.getElementById('pwa-install-button');

    const button = document.createElement('button');
    button.type = 'button';
    button.id = 'pwa-install-button';
    button.className = 'flex h-9 items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-700 transition-all hover:bg-emerald-100 hover:text-emerald-800 dark:border-emerald-800/70 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-900/50 sm:px-3';
    button.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14a2 2 0 0 0 2-2v-2.5a1.5 1.5 0 0 0-1.5-1.5H4.5A1.5 1.5 0 0 0 3 16.5V19a2 2 0 0 0 2 2Z"/>
        </svg>
        <span>Install App</span>
    `;
    button.setAttribute('aria-label', 'Install Officers\' Mess Manager as an app');

    const themeButton = headerActions.querySelector('button[onclick="toggleTheme()"]');
    if (themeButton) {
        themeButton.insertAdjacentElement('beforebegin', button);
    } else {
        headerActions.prepend(button);
    }

    button.addEventListener('click', async () => {
        if (!deferredInstallPrompt) return;

        const promptEvent = deferredInstallPrompt;
        deferredInstallPrompt = null;
        setInstallButtonState(button, { enabled: false, visible: true });

        try {
            await promptEvent.prompt();
            await promptEvent.userChoice;
        } catch {
            // The browser controls the final installation decision.
        }
    });

    setInstallButtonState(button, { enabled: Boolean(deferredInstallPrompt), visible: true });
    return button;
};

if (typeof window !== 'undefined' && typeof document !== 'undefined') {
    // Keep the manifest discoverable as early as possible without changing the
    // existing Blade layout structure.
    if (!document.querySelector('link[rel="manifest"]')) {
        const manifest = document.createElement('link');
        manifest.rel = 'manifest';
        manifest.href = '/manifest.webmanifest';
        document.head.appendChild(manifest);
    }

    document.querySelectorAll('link[rel="icon"]').forEach((icon) => {
        icon.href = '/images/app-icon.svg';
        icon.type = 'image/svg+xml';
    });

    if (!document.querySelector('meta[name="theme-color"]')) {
        const themeColor = document.createElement('meta');
        themeColor.name = 'theme-color';
        themeColor.content = '#0B2038';
        document.head.appendChild(themeColor);
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;

        const button = document.getElementById('pwa-install-button') || createInstallButton();
        setInstallButtonState(button, { enabled: true, visible: true });
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        const button = document.getElementById('pwa-install-button');
        if (button) button.remove();
    });

    window.addEventListener('load', () => {
        if (!isStandaloneMode()) createInstallButton();

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
                // PWA support is optional; app functionality must remain unaffected.
            });
        }
    });
}
