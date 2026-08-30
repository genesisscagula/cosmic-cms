import { getEffectiveTheme } from './Theme';

export function normalizeGlobalTheme(globalTheme) {
    return typeof globalTheme === 'string' ? { primary: globalTheme } : (globalTheme || {});
}

export function isCleanPageStyle(globalTheme) {
    const normalized = normalizeGlobalTheme(globalTheme);
    return String(normalized.pageStyle || normalized.page_style || '').toLowerCase() === 'clean';
}

export function resolveHeroThemeRequest(block, globalTheme) {
    const type = String(block?.type || '').toLowerCase();
    const explicitTheme = block?.theme && block.theme !== 'auto' ? block.theme : (block?.resolvedTheme || null);

    // Clean is deliberately a light visual system, including media heroes:
    // strong white overlay plus dark/slate copy. The render contract enforces
    // that foreground even when an older Spark saved authored white classes.
    if (isCleanPageStyle(globalTheme)) {
        return 'white';
    }

    return explicitTheme || 'primary';
}

export function getHeroThemeState(block, globalTheme) {
    const requestedTheme = resolveHeroThemeRequest(block, globalTheme);
    return {
        requestedTheme,
        theme: getEffectiveTheme(requestedTheme, globalTheme),
        isPrimary: requestedTheme === 'primary',
        isLight: requestedTheme === 'white' || requestedTheme === 'surface' || requestedTheme === 'stone',
        isClean: isCleanPageStyle(globalTheme),
    };
}
