const CONSENT_PREFIX = 'cosmic-cookie-consent:';

export const readCosmicConsent = () => {
    if (typeof window === 'undefined') return { necessary: true, analytics: false, marketing: false };

    try {
        const keys = [];
        for (let index = 0; index < window.localStorage.length; index += 1) {
            const key = window.localStorage.key(index);
            if (key?.startsWith(CONSENT_PREFIX)) keys.push(key);
        }
        keys.sort().reverse();

        for (const key of keys) {
            const parsed = JSON.parse(window.localStorage.getItem(key) || '{}');
            if (parsed && typeof parsed === 'object') {
                return {
                    necessary: true,
                    analytics: Boolean(parsed.analytics),
                    marketing: Boolean(parsed.marketing),
                };
            }
        }
    } catch (_) {}

    return { necessary: true, analytics: false, marketing: false };
};

export const trackCosmicEvent = (name, params = {}) => {
    if (typeof window === 'undefined' || !name) return;

    window.dispatchEvent(new CustomEvent('cosmic:track', {
        detail: { name, params },
    }));
};
