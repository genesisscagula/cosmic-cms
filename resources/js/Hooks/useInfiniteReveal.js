import { useCallback, useEffect, useMemo, useRef, useState } from "react";

function findScrollParent(node) {
    if (!node || typeof window === "undefined") return null;

    let parent = node.parentElement;
    while (parent) {
        const style = window.getComputedStyle(parent);
        const overflowY = style.overflowY;
        if ((overflowY === "auto" || overflowY === "scroll" || overflowY === "overlay") && parent.scrollHeight > parent.clientHeight) {
            return parent;
        }
        parent = parent.parentElement;
    }

    return null;
}

export default function useInfiniteReveal(items, {
    batchSize = 12,
    resetKey = "",
    root = null,
    rootMargin = "320px 0px",
    disabled = false,
} = {}) {
    const [visibleCount, setVisibleCount] = useState(batchSize);
    const [isRevealing, setIsRevealing] = useState(false);
    const sentinelRef = useRef(null);
    const revealLockRef = useRef(false);

    useEffect(() => {
        setVisibleCount(Math.min(batchSize, items.length));
        setIsRevealing(false);
        revealLockRef.current = false;
    }, [batchSize, resetKey, items.length]);

    const revealNext = useCallback(() => {
        if (revealLockRef.current) return;

        let changed = false;
        setVisibleCount((current) => {
            if (current >= items.length) return current;
            changed = true;
            return Math.min(items.length, current + batchSize);
        });

        if (!changed) return;
        revealLockRef.current = true;
        setIsRevealing(true);

        // Release after layout has committed so a sentinel that remains near the
        // bottom can trigger the following batch on the next scroll/observer pass.
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                revealLockRef.current = false;
                setIsRevealing(false);
            });
        });
    }, [batchSize, items.length]);

    useEffect(() => {
        const sentinel = sentinelRef.current;
        if (disabled || !sentinel || visibleCount >= items.length || typeof window === "undefined") return undefined;

        // Modal libraries scroll inside their own overflow container. When callers
        // do not explicitly provide an IntersectionObserver root, resolve the
        // nearest scrollable ancestor instead of observing against the browser
        // viewport. This keeps Trial and registered Builder libraries identical.
        const explicitRoot = root && typeof root === "object" && "current" in root ? root.current : root;
        const resolvedRoot = explicitRoot || findScrollParent(sentinel);

        const observer = typeof IntersectionObserver !== "undefined"
            ? new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) revealNext();
            }, {
                root: resolvedRoot,
                rootMargin,
                threshold: 0.01,
            })
            : null;

        observer?.observe(sentinel);

        // IntersectionObserver can miss a transition when a fixed modal scroll
        // container is already positioned near its bottom while the sentinel is
        // replaced after a batch. The scroll fallback makes the reveal deterministic.
        const scrollTarget = resolvedRoot || window;
        const checkNearBottom = () => {
            if (visibleCount >= items.length) return;

            if (resolvedRoot) {
                const remaining = resolvedRoot.scrollHeight - resolvedRoot.scrollTop - resolvedRoot.clientHeight;
                if (remaining <= 520) revealNext();
                return;
            }

            const rect = sentinel.getBoundingClientRect();
            if (rect.top <= window.innerHeight + 420) revealNext();
        };

        scrollTarget.addEventListener("scroll", checkNearBottom, { passive: true });
        window.requestAnimationFrame(checkNearBottom);

        return () => {
            observer?.disconnect();
            scrollTarget.removeEventListener("scroll", checkNearBottom);
        };
    }, [disabled, items.length, revealNext, root, rootMargin, visibleCount]);

    const visibleItems = useMemo(() => items.slice(0, visibleCount), [items, visibleCount]);

    return {
        visibleItems,
        visibleCount,
        hasMore: visibleCount < items.length,
        isRevealing,
        sentinelRef,
    };
}
