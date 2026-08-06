import { useEffect, useRef, useState } from "react";

const notificationEvent = "cosmic:notification";
const confirmationEvent = "cosmic:confirmation";

export function showCosmicNotification({ title, message, tone = "info" }) {
    window.dispatchEvent(new CustomEvent(notificationEvent, {
        detail: { title, message, tone },
    }));
}

export function confirmCosmicAction({ title, message, confirmLabel = "Continue", tone = "info" }) {
    return new Promise((resolve) => {
        window.dispatchEvent(new CustomEvent(confirmationEvent, {
            detail: { title, message, confirmLabel, tone, resolve },
        }));
    });
}

const toneStyles = {
    success: {
        badge: "bg-emerald-400/10 text-emerald-200 ring-emerald-400/25",
        dot: "bg-emerald-300",
        action: "bg-white text-slate-950 hover:bg-slate-200 focus:ring-emerald-400",
    },
    error: {
        badge: "bg-red-400/10 text-red-200 ring-red-400/25",
        dot: "bg-red-300",
        action: "bg-white text-slate-950 hover:bg-slate-200 focus:ring-red-400",
    },
    info: {
        badge: "bg-violet-400/10 text-violet-200 ring-violet-400/25",
        dot: "bg-violet-300",
        action: "bg-white text-slate-950 hover:bg-slate-200 focus:ring-violet-400",
    },
};

export default function CosmicNotification() {
    const [notification, setNotification] = useState(null);
    const closeButtonRef = useRef(null);

    const dismiss = () => {
        notification?.resolve?.(false);
        setNotification(null);
    };

    const confirm = () => {
        notification?.resolve?.(true);
        setNotification(null);
    };

    useEffect(() => {
        const handleNotification = (event) => setNotification(event.detail);
        const handleConfirmation = (event) => setNotification({ ...event.detail, confirmation: true });

        window.addEventListener(notificationEvent, handleNotification);
        window.addEventListener(confirmationEvent, handleConfirmation);
        return () => {
            window.removeEventListener(notificationEvent, handleNotification);
            window.removeEventListener(confirmationEvent, handleConfirmation);
        };
    }, []);

    useEffect(() => {
        if (!notification) return undefined;

        closeButtonRef.current?.focus();
        const handleKeyDown = (event) => {
            if (event.key === "Escape") dismiss();
        };

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [notification]);

    if (!notification) return null;

    const styles = toneStyles[notification.tone] || toneStyles.info;

    return (
        <div className="cosmic-alert-overlay fixed inset-0 z-[10050] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
            <button type="button" className="absolute inset-0 cursor-default" aria-label="Close notification" onClick={dismiss} />
            <section role="dialog" aria-modal="true" aria-labelledby="cosmic-notification-title" className="cosmic-notification cosmic-dialog-panel relative w-full max-w-sm rounded-2xl border border-white/10 bg-[#17171b] p-5 shadow-2xl shadow-black/60">
                <div className="flex items-start gap-3">
                    <span className={`mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full ring-1 ${styles.badge}`} aria-hidden="true">
                        <span className={`h-2 w-2 rounded-full ${styles.dot}`} />
                    </span>
                    <div className="min-w-0 flex-1">
                        <h2 id="cosmic-notification-title" className="text-base font-semibold text-white">{notification.title}</h2>
                        <p className="mt-1.5 text-sm leading-6 text-slate-400">{notification.message}</p>
                    </div>
                    <button type="button" onClick={dismiss} className="-mt-1 rounded-lg p-1 text-slate-500 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400" aria-label="Close">×</button>
                </div>
                <div className="mt-5 flex justify-end">
                    {notification.confirmation ? (
                        <div className="flex items-center gap-2">
                            <button type="button" onClick={dismiss} className="rounded-lg px-3 py-2 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">Cancel</button>
                            <button ref={closeButtonRef} type="button" onClick={confirm} className={`rounded-lg px-4 py-2 text-xs font-semibold transition focus:outline-none focus:ring-2 ${styles.action}`}>{notification.confirmLabel}</button>
                        </div>
                    ) : (
                        <button ref={closeButtonRef} type="button" onClick={dismiss} className={`rounded-lg px-4 py-2 text-xs font-semibold transition focus:outline-none focus:ring-2 ${styles.action}`}>Done</button>
                    )}
                </div>
            </section>
        </div>
    );
}
