const currentRelativeUrl = () => `${window.location.pathname}${window.location.search}${window.location.hash}`;

export function normalizeBuilderUrl(rawUrl) {
    const value = String(rawUrl || '').trim();
    if (!value) return '';

    try {
        const parsed = new URL(value, window.location.origin);
        return `${parsed.pathname}${parsed.search}${parsed.hash}`;
    } catch {
        return value.startsWith('/') ? value : `/${value}`;
    }
}

/**
 * Move a completed public trial into the Builder without depending on a
 * single navigation mechanism. Inertia gives the smooth hand-off; native
 * navigation is the watchdog when an interrupted visit, stale asset, or
 * browser extension leaves the public page mounted.
 */
export function handoffToBuilder(rawUrl, { router = null, delay = 0, fallbackDelay = 1600 } = {}) {
    const target = normalizeBuilderUrl(rawUrl);
    if (!target) return '';

    const nativeNavigate = () => {
        if (currentRelativeUrl() === target) return;

        try {
            window.location.replace(target);
        } catch {
            window.location.href = target;
        }
    };

    const begin = () => {
        if (!router?.visit) {
            nativeNavigate();
            return;
        }

        try {
            router.visit(target, {
                preserveScroll: false,
                preserveState: false,
                onCancel: nativeNavigate,
                onError: nativeNavigate,
                onException: nativeNavigate,
            });
        } catch {
            nativeNavigate();
            return;
        }

        // If the Inertia request never commits, force a full same-origin load.
        window.setTimeout(nativeNavigate, fallbackDelay);
    };

    window.setTimeout(begin, Math.max(0, delay));
    return target;
}
