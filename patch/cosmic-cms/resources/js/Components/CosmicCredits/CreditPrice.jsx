export default function CreditPrice({ amount, label = null, className = '' }) {
    const numericAmount = Number(amount ?? 0);

    if (numericAmount <= 0) {
        return label ? <span className={className}>{label}</span> : null;
    }

    return (
        <span
            className={`inline-flex items-center gap-1 whitespace-nowrap ${className}`}
            title={`Costs ${numericAmount} Cosmic Credit${numericAmount === 1 ? '' : 's'}`}
        >
            {label ? <span>{label}</span> : null}
            <span aria-hidden="true">⚡</span>
            <span>{numericAmount}</span>
            <span className="sr-only">Cosmic Credits</span>
        </span>
    );
}
