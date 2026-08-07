export const STARTER_THEMES = ['midnight', 'emerald', 'ocean', 'coffee', 'rose'];
export const GROWTH_THEMES = [
    ...STARTER_THEMES,
    'indigo',
    'amber',
    'charcoal',
    'violet',
    'teal',
];

const normalizePlanKey = (planKey) => String(planKey || 'starter').toLowerCase().trim();

export function themeLimitForPlan(planKey) {
    const key = normalizePlanKey(planKey);

    if (['pro', 'agency_pro'].includes(key)) return null;
    if (['growth', 'agency_growth'].includes(key)) return GROWTH_THEMES.length;
    return STARTER_THEMES.length;
}

export function allowedThemesForPlan(planKey, allThemeIds = []) {
    const key = normalizePlanKey(planKey);

    if (['pro', 'agency_pro'].includes(key)) return [...allThemeIds];
    if (['growth', 'agency_growth'].includes(key)) return [...GROWTH_THEMES];
    return [...STARTER_THEMES];
}

export function upgradePlanLabel(planKey) {
    const key = normalizePlanKey(planKey);
    if (['starter', 'agency_starter'].includes(key)) return 'Growth';
    if (['growth', 'agency_growth'].includes(key)) return 'Pro';
    return null;
}
