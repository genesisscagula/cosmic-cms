import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import CosmicNotification from './Components/CosmicNotification';
import CookieConsent from './Components/CookieConsent';
import { CreditBalanceProvider } from './Components/CosmicCredits/CreditBalanceContext';
import { AppearanceProvider } from './Appearance/AppearanceContext';

const appName = 'Cosmic CMS';

createInertiaApp({
    title: () => appName,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        const authenticated = Boolean(props.initialPage?.props?.auth?.user);

        const initialBalance =
            props.initialPage?.props?.auth?.creditBalance ??
            props.initialPage?.props?.auth?.user?.credits ??
            props.initialPage?.props?.balance ??
            null;

        root.render(
            <AppearanceProvider initialPage={props.initialPage}>
            <CreditBalanceProvider
                authenticated={authenticated}
                initialBalance={initialBalance}
            >
                <App {...props} />
                <CosmicNotification />
                <CookieConsent />
            </CreditBalanceProvider>
            </AppearanceProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
