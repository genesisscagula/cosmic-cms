import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';

const CreditBalanceContext = createContext(null);

export function CreditBalanceProvider({ children }) {
    const pageBalance = Number(usePage().props?.auth?.creditBalance ?? 0);
    const [balance, setBalanceState] = useState(pageBalance);

    useEffect(() => {
        setBalanceState(pageBalance);
    }, [pageBalance]);

    const setBalance = useCallback((nextBalance) => {
        if (nextBalance === null || nextBalance === undefined || Number.isNaN(Number(nextBalance))) return;
        setBalanceState(Number(nextBalance));
    }, []);

    const value = useMemo(() => ({ balance, setBalance }), [balance, setBalance]);

    return <CreditBalanceContext.Provider value={value}>{children}</CreditBalanceContext.Provider>;
}

export function useCreditBalance() {
    const context = useContext(CreditBalanceContext);
    if (!context) throw new Error('useCreditBalance must be used inside CreditBalanceProvider.');
    return context;
}
