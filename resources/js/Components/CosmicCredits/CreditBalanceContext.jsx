import axios from 'axios';
import { createContext, useCallback, useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';

export const CreditBalanceContext = createContext(null);

function normalizeBalance(value, fallback = null) {
    const numericValue = Number(value);
    return value !== null && value !== '' && Number.isFinite(numericValue) ? numericValue : fallback;
}

export function CreditBalanceProvider({
    children,
    initialBalance = null,
    authenticated = false,
}) {
    const [balance, setBalanceState] = useState(() => normalizeBalance(initialBalance));

    const setBalance = useCallback((nextBalance) => {
        setBalanceState((currentBalance) => normalizeBalance(nextBalance, currentBalance));
    }, []);

    const refreshBalance = useCallback(async () => {
        if (!authenticated) {
            setBalanceState(null);
            return;
        }

        try {
            const response = await axios.get(route('credits.balance'), {
                headers: { Accept: 'application/json' },
            });

            const nextBalance = response.data?.credit_balance
                ?? response.data?.balance
                ?? response.data?.current_balance
                ?? response.data?.credits;

            if (nextBalance !== undefined && nextBalance !== null) {
                setBalance(nextBalance);
            }
        } catch (error) {
            if (error.response?.status === 401) {
                setBalanceState(null);
                return;
            }
            console.warn('Unable to refresh Cosmic Credits balance.', error);
        }
    }, [authenticated, setBalance]);

    useEffect(() => {
        if (!authenticated) {
            setBalanceState(null);
            return undefined;
        }

        setBalance(normalizeBalance(initialBalance, 0));
        refreshBalance();

        const removeSuccessListener = router.on('success', (event) => {
            const pageProps = event?.detail?.page?.props || {};
            const nextBalance = pageProps?.auth?.creditBalance
                ?? pageProps?.auth?.user?.credits
                ?? pageProps?.balance
                ?? pageProps?.dashboard?.summary?.credits;

            if (nextBalance !== undefined && nextBalance !== null) {
                setBalance(nextBalance);
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
