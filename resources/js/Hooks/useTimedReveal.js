import { useEffect, useState } from "react";

export default function useTimedReveal(total, resetKey = "", options = {}) {
    const initial = Math.max(1, Number(options.initial || 100));
    const step = Math.max(1, Number(options.step || 50));
    const intervalMs = Math.max(250, Number(options.intervalMs || 5000));
    const enabled = options.enabled !== false;
    const [visibleCount, setVisibleCount] = useState(() => Math.min(initial, Math.max(0, Number(total || 0))));

    useEffect(() => {
        setVisibleCount(Math.min(initial, Math.max(0, Number(total || 0))));
    }, [resetKey, initial]);

    useEffect(() => {
        const safeTotal = Math.max(0, Number(total || 0));
        if (!enabled || visibleCount >= safeTotal) return undefined;

        const timer = window.setInterval(() => {
            if (typeof document !== "undefined" && document.hidden) return;
            setVisibleCount((current) => Math.min(safeTotal, current + step));
        }, intervalMs);

        return () => window.clearInterval(timer);
    }, [enabled, total, visibleCount, step, intervalMs]);

    return Math.min(visibleCount, Math.max(0, Number(total || 0)));
}
