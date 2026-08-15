import { useEffect, useMemo, useRef, useState } from "react";

export default function useInfiniteReveal(items, {
    batchSize = 12,
    resetKey = "",
    root = null,
    rootMargin = "320px 0px",
} = {}) {
    const [visibleCount, setVisibleCount] = useState(batchSize);
    const sentinelRef = useRef(null);

    useEffect(() => {
        setVisibleCount(batchSize);
    }, [batchSize, resetKey]);

    useEffect(() => {
        const sentinel = sentinelRef.current;
        if (!sentinel || visibleCount >= items.length) return undefined;

        const observer = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return;
            setVisibleCount((current) => Math.min(items.length, current + batchSize));
        }, {
            root,
            rootMargin,
            threshold: 0.01,
        });

        observer.observe(sentinel);
        return () => observer.disconnect();
    }, [batchSize, items.length, root, rootMargin, visibleCount]);

    const visibleItems = useMemo(() => items.slice(0, visibleCount), [items, visibleCount]);

    return {
        visibleItems,
        visibleCount,
        hasMore: visibleCount < items.length,
        sentinelRef,
    };
}
