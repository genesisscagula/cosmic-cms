import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import CosmicNotification from './Components/CosmicNotification';
import { CreditBalanceProvider } from './Components/CosmicCredits/CreditBalanceContext';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
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
            <CreditBalanceProvider
                authenticated={authenticated}
                initialBalance={initialBalance}
            >
                <App {...props} />
                <CosmicNotification />
            </CreditBalanceProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
