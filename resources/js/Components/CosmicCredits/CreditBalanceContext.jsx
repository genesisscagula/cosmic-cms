import axios from 'axios';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';

const CREDIT_STORAGE_KEY = 'cosmic.creditBalance';

const CreditBalanceContext = createContext(null);

function normalizeBalance(value, fallback = null) {
    const numericValue = Number(value);
    return value !== null && value !== '' && Number.isFinite(numericValue) ? numericValue : fallback;
}

export function CreditBalanceProvider({
    children,
    initialBalance = null,
    authenticated = false,
}) {
    const [balance, setBalanceState] = useState(() => {
        const serverBalance = normalizeBalance(initialBalance);
        if (typeof window === 'undefined') return serverBalance;

        const cachedBalance = normalizeBalance(window.localStorage.getItem(CREDIT_STORAGE_KEY));

        // A transient zero can arrive before the wallet summary has finished resolving.
        // Keep the last known positive value until the balance endpoint confirms the source of truth.
        if (serverBalance === 0 && cachedBalance !== null && cachedBalance > 0) {
            return cachedBalance;
        }

        return serverBalance ?? cachedBalance;
    });

    const setBalance = useCallback((nextBalance) => {
        setBalanceState((currentBalance) => {
            const normalized = normalizeBalance(nextBalance, currentBalance);
            if (normalized !== null && typeof window !== 'undefined') {
                window.localStorage.setItem(CREDIT_STORAGE_KEY, String(normalized));
            }
            return normalized;
        });
    }, []);

    const refreshBalance = useCallback(async () => {
        if (!authenticated) {
            return;
        }

        try {
            const response = await axios.get(route('credits.balance'), {
                headers: { Accept: 'application/json' },
            });

            const nextBalance = response.data?.credit_balance ?? response.data?.balance ?? response.data?.current_balance ?? response.data?.credits;
            if (nextBalance !== undefined && nextBalance !== null) {
                setBalance(nextBalance);
            }
        } catch (error) {
            // Ignore expired/guest sessions. Keep the last known balance for other failures.
            if (error.response?.status !== 401) {
                console.warn('Unable to refresh Cosmic Credits balance.', error);
            }
        }
    }, [authenticated, setBalance]);

    useEffect(() => {
        if (!authenticated) {
            return undefined;
        }

        const serverBalance = normalizeBalance(initialBalance);
        const cachedBalance = typeof window !== 'undefined'
            ? normalizeBalance(window.localStorage.getItem(CREDIT_STORAGE_KEY))
            : null;

        // Do not let a stale zero shared prop erase a confirmed positive wallet.
        // The dedicated balance endpoint below will reconcile the source of truth.
        if (serverBalance !== null && (serverBalance > 0 || cachedBalance === null || cachedBalance === 0)) {
            setBalance(serverBalance);
        }
        refreshBalance();

        const removeSuccessListener = router.on('success', (event) => {
            const pageProps = event?.detail?.page?.props || {};
            const nextBalance = pageProps?.auth?.creditBalance ?? pageProps?.auth?.user?.credits ?? pageProps?.balance ?? pageProps?.dashboard?.summary?.credits;
            const normalizedNextBalance = normalizeBalance(nextBalance);
            if (normalizedNextBalance !== null && normalizedNextBalance > 0) {
                setBalance(normalizedNextBalance);
            } else {
                refreshBalance();
            }
        });

        return removeSuccessListener;
    }, [authenticated, initialBalance, refreshBalance, setBalance]);

    const value = useMemo(
        () => ({ balance, setBalance, refreshBalance }),
        [balance, setBalance, refreshBalance],
    );

    return (
        <CreditBalanceContext.Provider value={value}>
            {children}
        </CreditBalanceContext.Provider>
    );
}

export function useCreditBalance() {
    const context = useContext(CreditBalanceContext);

    if (!context) {
        throw new Error('useCreditBalance must be used inside CreditBalanceProvider.');
    }

    return context;
}
