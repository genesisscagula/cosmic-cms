import { getEffectiveTheme } from './Theme';

export function normalizeGlobalTheme(globalTheme) {
    return typeof globalTheme === 'string' ? { primary: globalTheme } : (globalTheme || {});
}

export function isCleanPageStyle(globalTheme) {
    const normalized = normalizeGlobalTheme(globalTheme);
    return String(normalized.pageStyle || normalized.page_style || '').toLowerCase() === 'clean';
}

export function resolveHeroThemeRequest(block, globalTheme) {
    if (isCleanPageStyle(globalTheme)) return 'white';
    return block?.theme && block.theme !== 'auto' ? block.theme : (block?.resolvedTheme || 'primary');
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
