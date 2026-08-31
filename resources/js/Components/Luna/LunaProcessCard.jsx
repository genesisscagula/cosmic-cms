const normalizeStep = (step, index) => {
    if (typeof step === 'string') return { label: step, threshold: null, index };
    return {
        label: String(step?.label || step?.message || `Step ${index + 1}`),
        threshold: Number.isFinite(Number(step?.threshold)) ? Number(step.threshold) : null,
        index,
    };
};

export const inferLunaProcessSteps = (status = '') => {
    const normalized = String(status || '').toLowerCase();
    const actionLabel = /logo/.test(normalized)
        ? 'Generating the logo'
        : /image|photo|visual/.test(normalized)
            ? 'Generating the visual'
            : /theme|palette|color/.test(normalized)
                ? 'Applying the theme'
                : /publish|live/.test(normalized)
                    ? 'Publishing the page'
                    : /section/.test(normalized)
                        ? 'Building the section'
                        : /page|website|site/.test(normalized)
                            ? 'Building the website'
                            : /design/.test(normalized)
                                ? 'Applying the design'
                                : 'Applying the update';

    return [
        'Understanding your request',
        'Planning the changes',
        actionLabel,
        'Verifying the result',
    ];
};

export const inferLunaProcessActiveIndex = (status = '', steps = []) => {
    const normalized = String(status || '').toLowerCase();
    if (/ready|complete|completed|done|finished|opening builder|100%/.test(normalized)) return steps.length;
    if (/verif|check|finaliz|qa|review/.test(normalized)) return Math.max(0, steps.length - 1);
    if (/apply|build|generat|upload|publish|updat|working|creating|adding|writing|prepar|match/.test(normalized)) return Math.min(2, Math.max(0, steps.length - 1));
    if (/plan|designing|structure|select|rank/.test(normalized)) return Math.min(1, Math.max(0, steps.length - 1));
    return 0;
};

export default function LunaProcessCard({
    status = 'Working…',
    dark = false,
    intro = 'Luna is working on your request.',
    steps = null,
    activeIndex = null,
    progress = null,
    className = '',
}) {
    const sourceSteps = Array.isArray(steps) && steps.length ? steps : inferLunaProcessSteps(status);
    const normalizedSteps = sourceSteps.map(normalizeStep);
    const numericProgress = Number.isFinite(Number(progress)) ? Math.max(0, Math.min(100, Number(progress))) : null;

    let resolvedActiveIndex = Number.isInteger(activeIndex) ? activeIndex : null;
    if (resolvedActiveIndex === null && numericProgress !== null) {
        if (numericProgress >= 100) {
            resolvedActiveIndex = normalizedSteps.length;
        } else {
            const completed = normalizedSteps.filter((step) => step.threshold !== null && numericProgress >= step.threshold).length;
            resolvedActiveIndex = normalizedSteps.some((step) => step.threshold !== null)
                ? Math.min(completed, Math.max(0, normalizedSteps.length - 1))
                : Math.min(Math.floor((numericProgress / 100) * normalizedSteps.length), Math.max(0, normalizedSteps.length - 1));
        }
    }
    if (resolvedActiveIndex === null && Array.isArray(steps) && steps.length) {
        const clean = (value) => String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
        const cleanStatus = clean(status);
        const matchedIndex = normalizedSteps.findIndex((step) => {
            const cleanLabel = clean(step.label);
            return cleanStatus && cleanLabel && (cleanStatus.includes(cleanLabel) || cleanLabel.includes(cleanStatus));
        });
        if (matchedIndex >= 0) resolvedActiveIndex = matchedIndex;
    }
    if (resolvedActiveIndex === null) resolvedActiveIndex = inferLunaProcessActiveIndex(status, normalizedSteps);
    resolvedActiveIndex = Math.max(0, Math.min(normalizedSteps.length, resolvedActiveIndex));

    return <div className={`cosmic-luna-process-card ${dark ? 'cosmic-luna-process-card--dark' : ''} ${className}`.trim()} role="status" aria-live="polite">
        {intro ? <p className="cosmic-luna-process-card__intro">{intro}</p> : null}
        <div className="cosmic-luna-process-card__steps">
            {normalizedSteps.map((step, index) => {
                const state = index < resolvedActiveIndex || resolvedActiveIndex >= normalizedSteps.length
                    ? 'complete'
                    : index === resolvedActiveIndex ? 'active' : 'pending';
                return <div key={`${step.label}-${index}`} className={`cosmic-luna-process-card__step cosmic-luna-process-card__step--${state}`}>
                    <span className="cosmic-luna-process-card__marker" aria-hidden="true">{state === 'complete' ? '✓' : state === 'pending' ? '•' : ''}</span>
                    <span>{step.label}</span>
                </div>;
            })}
        </div>
        <p className="cosmic-luna-process-card__status">{status || 'Working…'}</p>
    </div>;
}
