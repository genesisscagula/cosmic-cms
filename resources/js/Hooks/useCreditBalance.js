import { useContext } from 'react';
import { CreditBalanceContext } from '@/Components/CosmicCredits/CreditBalanceContext';

export function useCreditBalance() {
    const context = useContext(CreditBalanceContext);

    if (!context) {
        throw new Error('useCreditBalance must be used inside CreditBalanceProvider.');
    }

    return context;
}
