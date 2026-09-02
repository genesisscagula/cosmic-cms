const SPARK_PLAN_RANK = Object.freeze({
    free: 0,
    starter: 10,
    agency_starter: 10,
    growth: 20,
    agency_growth: 20,
    pro: 30,
    agency_pro: 30,
});

const normalizedPlanLevel = (spark) => String(spark?.access_level || 'starter').trim().toLowerCase();

const isOwnedSpark = (spark) => Boolean(
    spark?.owned
    || spark?.shared
    || spark?.owned_permanently
    || spark?.acquisition?.action === 'installed'
    || spark?.acquisition?.action === 'restore'
);

const isPlanLockedSpark = (spark) => Boolean(
    spark?.usage_state?.key === 'plan_locked'
    || spark?.access?.allowed === false
    || spark?.locked === true
);

const requiredPlanRank = (spark) => SPARK_PLAN_RANK[normalizedPlanLevel(spark)] ?? 99;

const originalCatalogIndex = (spark) => {
    for (const value of [spark?.catalog_index, spark?.sort_order, spark?.id]) {
        if (value === null || value === undefined || value === '') continue;
        const numeric = Number(value);
        if (Number.isFinite(numeric)) return numeric;
    }
    return Number.MAX_SAFE_INTEGER;
};

/**
 * Marketplace browse priority:
 *  1. owned/permanently-owned Sparks
 *  2. Sparks available on the current plan
 *  3. Growth-locked Sparks
 *  4. Pro-locked Sparks
 *
 * Within every bucket, lower catalog_index wins. SparkCatalog assigns that
 * index from the historical registry order, so original/older Sparks remain
 * ahead of newer additions without relying on mutable prices or names.
 */
export const sparkMarketplacePriorityBucket = (spark) => {
    if (isOwnedSpark(spark)) return 0;
    if (!isPlanLockedSpark(spark)) return 1;

    const rank = requiredPlanRank(spark);
    if (rank <= SPARK_PLAN_RANK.growth) return 2;
    if (rank <= SPARK_PLAN_RANK.pro) return 3;
    return 4;
};

export const compareSparkMarketplacePriority = (left, right) => {
    const bucketDiff = sparkMarketplacePriorityBucket(left) - sparkMarketplacePriorityBucket(right);
    if (bucketDiff !== 0) return bucketDiff;

    const historyDiff = originalCatalogIndex(left) - originalCatalogIndex(right);
    if (historyDiff !== 0) return historyDiff;

    return String(left?.name || left?.key || '').localeCompare(String(right?.name || right?.key || ''));
};

export const sortSparkMarketplaceItems = (items = []) => [...items].sort(compareSparkMarketplacePriority);
