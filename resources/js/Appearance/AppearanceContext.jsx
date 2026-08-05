import { router, usePage } from '@inertiajs/react';
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

function applyTheme(mode) {
    const resolved = resolveTheme(mode);
    document.documentElement.dataset.appearance = mode;
    document.documentElement.dataset.theme = resolved;
    document.documentElement.classList.toggle('dark', resolved === 'dark');
    document.documentElement.style.colorScheme = resolved;
    return resolved;
}

export function AppearanceProvider({ children }) {
    const page = usePage();
    const databasePreference = normalize(page.props?.auth?.appearance);
    const authenticated = Boolean(page.props?.auth?.user);
    const [mode, setModeState] = useState(() => {
        if (typeof window === 'undefined') return databasePreference;
        return normalize(window.localStorage.getItem(STORAGE_KEY) || databasePreference);
    });
    const [resolvedTheme, setResolvedTheme] = useState(() => resolveTheme(mode));

    useEffect(() => {
        window.localStorage.setItem(STORAGE_KEY, mode);
        setResolvedTheme(applyTheme(mode));
    }, [mode]);

    useEffect(() => {
        if (typeof window === 'undefined' || mode !== 'system') return undefined;
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => setResolvedTheme(applyTheme('system'));
        media.addEventListener?.('change', onChange);
        return () => media.removeEventListener?.('change', onChange);
    }, [mode]);

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

    const value = useMemo(() => ({ mode, resolvedTheme, setMode, modes: MODES }), [mode, resolvedTheme, setMode]);
    return <AppearanceContext.Provider value={value}>{children}</AppearanceContext.Provider>;
}

export function useAppearance() {
    const context = useContext(AppearanceContext);
    if (!context) throw new Error('useAppearance must be used inside AppearanceProvider.');
    return context;
}
