import { router } from '@inertiajs/react';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

const STORAGE_KEY = 'cosmic.appearance';
const MODES = ['light', 'dark', 'system'];
const AppearanceContext = createContext(null);

function normalize(value) {
    return MODES.includes(value) ? value : 'light';
}

function resolveTheme(mode) {
    if (mode !== 'system') return mode;
    if (typeof window === 'undefined') return 'light';
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(mode, enabled = true) {
    const activeMode = enabled ? normalize(mode) : 'light';
    const resolved = resolveTheme(activeMode);

    if (typeof document !== 'undefined') {
        document.documentElement.dataset.appearance = activeMode;
        document.documentElement.dataset.theme = resolved;
        document.documentElement.classList.toggle('dark', resolved === 'dark');
        document.documentElement.style.colorScheme = resolved;
    }

    return resolved;
}

function authStateFromPage(page) {
    return {
        authenticated: Boolean(page?.props?.auth?.user),
        databasePreference: normalize(page?.props?.auth?.appearance),
    };
}

// Appearance preferences belong to the authenticated application only.
// Public, marketing, legal, trial, and guest/auth pages always use the
// branded light-green presentation regardless of a saved dashboard mode.
function isPublicSurface(url = '') {
    const path = String(url || (typeof window !== 'undefined' ? window.location.pathname : '/')).split('?')[0];
    const publicPaths = [
        '/', '/login', '/register', '/forgot-password', '/reset-password',
        '/verify-email', '/confirm-password', '/pricing', '/start',
        '/privacy', '/terms', '/cookies', '/legal', '/client', '/preview',
    ];

    return publicPaths.some((prefix) =>
        prefix === '/' ? path === '/' : (path === prefix || path.startsWith(`${prefix}/`))
    );
}

function appearanceEnabledForPage(page) {
    // Some authenticated Builder and Marketplace responses intentionally omit
    // the shared auth payload. Route scope is therefore the reliable source
    // of truth for whether the appearance engine should be active.
    return !isPublicSurface(page?.url);
}


export function AppearanceProvider({ children, initialPage = null }) {
    const initialAuth = authStateFromPage(initialPage);
    const [authenticated, setAuthenticated] = useState(initialAuth.authenticated);
    const [appearanceEnabled, setAppearanceEnabled] = useState(() => appearanceEnabledForPage(initialPage));
    const [databasePreference, setDatabasePreference] = useState(initialAuth.databasePreference);
    const [mode, setModeState] = useState(() => {
        if (typeof window === 'undefined') return initialAuth.databasePreference;
        return normalize(window.localStorage.getItem(STORAGE_KEY) || initialAuth.databasePreference);
    });
    const [resolvedTheme, setResolvedTheme] = useState(() => appearanceEnabled ? resolveTheme(mode) : 'light');

    useEffect(() => {
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_KEY, mode);
        }
        setResolvedTheme(applyTheme(mode, appearanceEnabled));
    }, [mode, appearanceEnabled]);

    useEffect(() => {
        if (typeof window === 'undefined' || mode !== 'system' || !appearanceEnabled) return undefined;

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => setResolvedTheme(applyTheme('system', true));
        media.addEventListener?.('change', onChange);

        return () => media.removeEventListener?.('change', onChange);
    }, [mode, appearanceEnabled]);

    useEffect(() => {
        const removeListener = router.on('success', (event) => {
            const next = authStateFromPage(event.detail?.page);
            setAuthenticated(next.authenticated);
            setAppearanceEnabled(appearanceEnabledForPage(event.detail?.page));
            setDatabasePreference(next.databasePreference);

            // On a new authenticated session, use the saved account preference only
            // when this browser has not already selected an appearance.
            if (typeof window !== 'undefined' && !window.localStorage.getItem(STORAGE_KEY)) {
                setModeState(next.databasePreference);
            }
        });

        return removeListener;
    }, []);

    useEffect(() => {
        if (typeof window !== 'undefined' && !window.localStorage.getItem(STORAGE_KEY)) {
            setModeState(databasePreference);
        }
    }, [databasePreference]);

    const setMode = useCallback((nextMode, { persist = true } = {}) => {
        const normalized = normalize(nextMode);
        setModeState(normalized);

        if (authenticated && persist) {
            router.patch('/appearance', { appearance: normalized }, {
                preserveScroll: true,
                preserveState: true,
                only: ['auth'],
            });
        }
    }, [authenticated]);

    const value = useMemo(
        () => ({ mode, resolvedTheme, setMode, modes: MODES }),
        [mode, resolvedTheme, setMode],
    );

    return <AppearanceContext.Provider value={value}>{children}</AppearanceContext.Provider>;
}

export function useAppearance() {
    const context = useContext(AppearanceContext);
    if (!context) throw new Error('useAppearance must be used inside AppearanceProvider.');
    return context;
}
