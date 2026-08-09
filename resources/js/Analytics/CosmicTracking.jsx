import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { readCosmicConsent } from './tracking';

const loadScript = (id, src) => {
    if (!id || document.getElementById(id)) return;
    const script = document.createElement('script');
    script.id = id;
    script.async = true;
    script.src = src;
    document.head.appendChild(script);
};

const ensureDataLayer = () => {
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function gtag(){ window.dataLayer.push(arguments); };
};

export default function CosmicTracking({ tracking = {} }) {
    const consentRef = useRef(readCosmicConsent());
    const initializedRef = useRef(false);

    useEffect(() => {
        ensureDataLayer();

        // Google Consent Mode defaults: optional storage is denied until the
        // visitor explicitly opts in through Cosmic CMS's consent banner.
        window.gtag('consent', 'default', {
            analytics_storage: 'denied',
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            wait_for_update: 500,
        });

        const applyConsent = (choice) => {
            consentRef.current = {
                necessary: true,
                analytics: Boolean(choice?.analytics),
                marketing: Boolean(choice?.marketing),
            };

            window.gtag('consent', 'update', {
                analytics_storage: consentRef.current.analytics ? 'granted' : 'denied',
                ad_storage: consentRef.current.marketing ? 'granted' : 'denied',
                ad_user_data: consentRef.current.marketing ? 'granted' : 'denied',
                ad_personalization: consentRef.current.marketing ? 'granted' : 'denied',
            });

            if (tracking.googleTagManagerId && (consentRef.current.analytics || consentRef.current.marketing)) {
                if (!initializedRef.current) {
                    window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
                    loadScript('cosmic-google-tag-manager', `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(tracking.googleTagManagerId)}`);
                    initializedRef.current = true;
                }
                return;
            }

            // Direct GA4 is a fallback when no GTM container is configured.
            if (!tracking.googleTagManagerId && tracking.googleAnalyticsId && consentRef.current.analytics) {
                loadScript('cosmic-google-analytics', `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(tracking.googleAnalyticsId)}`);
                if (!initializedRef.current) {
                    window.gtag('js', new Date());
                    window.gtag('config', tracking.googleAnalyticsId, { send_page_view: false });
                    initializedRef.current = true;
                }
            }
        };

        applyConsent(consentRef.current);

        const consentChanged = (event) => applyConsent(event.detail || {});
        window.addEventListener('cosmic:consent-changed', consentChanged);
        return () => window.removeEventListener('cosmic:consent-changed', consentChanged);
    }, [tracking.googleAnalyticsId, tracking.googleTagManagerId]);

    useEffect(() => {
        const sendEvent = (name, params = {}) => {
            const consent = consentRef.current;
            if (!consent.analytics && !consent.marketing) return;

            ensureDataLayer();
            window.dataLayer.push({ event: name, ...params });

            if (!tracking.googleTagManagerId && tracking.googleAnalyticsId && consent.analytics) {
                window.gtag('event', name, params);
            }
        };

        const trackPageView = () => {
            if (!consentRef.current.analytics) return;
            sendEvent('page_view', {
                page_location: window.location.href,
                page_path: `${window.location.pathname}${window.location.search}`,
                page_title: document.title,
            });
        };

        const customEvent = (event) => {
            const detail = event.detail || {};
            sendEvent(detail.name, detail.params || {});
        };

        const consentChanged = () => window.setTimeout(trackPageView, 0);

        trackPageView();
        const removeNavigate = router.on('navigate', () => window.setTimeout(trackPageView, 0));
        window.addEventListener('cosmic:track', customEvent);
        window.addEventListener('cosmic:consent-changed', consentChanged);

        return () => {
            removeNavigate();
            window.removeEventListener('cosmic:track', customEvent);
            window.removeEventListener('cosmic:consent-changed', consentChanged);
        };
    }, [tracking.googleAnalyticsId, tracking.googleTagManagerId]);

    return null;
}
