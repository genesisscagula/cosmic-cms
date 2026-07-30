import axios from 'axios';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';

const CreditBalanceContext = createContext(null);

function normalizeBalance(value, fallback = 0) {
    const numericValue = Number(value);
    return Number.isFinite(numericValue) ? numericValue : fallback;
}

export function CreditBalanceProvider({ children, initialBalance = 0 }) {
    const [balance, setBalanceState] = useState(() => normalizeBalance(initialBalance));

    const setBalance = useCallback((nextBalance) => {
        setBalanceState((currentBalance) => normalizeBalance(nextBalance, currentBalance));
    }, []);

    const refreshBalance = useCallback(async () => {
        try {
            const response = await axios.get(route('credits.balance'), {
                headers: { Accept: 'application/json' },
            });

            if (response.data?.credit_balance !== undefined) {
                setBalance(response.data.credit_balance);
            }
        } catch (error) {
            // Keep the last known balance if the wallet request temporarily fails.
            console.warn('Unable to refresh Cosmic Credits balance.', error);
        }
    }, [setBalance]);

    useEffect(() => {
        refreshBalance();

        const removeSuccessListener = router.on('success', () => {
            refreshBalance();
        });

        return removeSuccessListener;
    }, [refreshBalance]);

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
