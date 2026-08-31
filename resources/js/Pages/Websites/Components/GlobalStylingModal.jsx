import { useEffect, useMemo, useState } from 'react';

const sansStack = (family) => `"${family}", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`;
const serifStack = (family) => `"${family}", Georgia, "Times New Roman", serif`;
const DISPLAY_STACK = sansStack('Manrope');
const SYSTEM_STACK = 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
const SERIF_STACK = 'Georgia, "Times New Roman", serif';

export const PREMIUM_TYPOGRAPHY_DEFAULTS = Object.freeze({
    font_display: DISPLAY_STACK,
    font_body: DISPLAY_STACK,

    h1_size: 'clamp(3rem, 6vw, 5.75rem)',
    h1_size_tablet: '4.75rem',
    h1_size_mobile: '3.25rem',
    h1_line: '.96',
    h1_weight: '700',
    h1_tracking: '-.05em',

    h2_size: 'clamp(2.25rem, 4.05vw, 4rem)',
    h2_size_tablet: '3.35rem',
    h2_size_mobile: '2.65rem',
    h2_line: '1',
    h2_weight: '700',
    h2_tracking: '-.045em',

    h3_size: 'clamp(1.35rem, 2vw, 1.75rem)',
    h3_size_tablet: '1.6rem',
    h3_size_mobile: '1.5rem',
    h3_line: '1.08',
    h3_weight: '700',
    h3_tracking: '-.025em',

    h4_size: 'clamp(1.125rem, 1.5vw, 1.35rem)',
    h4_size_tablet: '1.3rem',
    h4_size_mobile: '1.2rem',
    h4_line: '1.15',
    h4_weight: '700',
    h4_tracking: '-.015em',

    h5_size: 'clamp(1rem, 1.2vw, 1.125rem)',
    h5_size_tablet: '1.1rem',
    h5_size_mobile: '1rem',
    h5_line: '1.25',
    h5_weight: '700',
    h5_tracking: '-.01em',

    h6_size: 'clamp(.875rem, 1vw, 1rem)',
    h6_size_tablet: '1rem',
    h6_size_mobile: '.9rem',
    h6_line: '1.3',
    h6_weight: '700',
    h6_tracking: '0em',

    lead_size: 'clamp(1.05rem, 1.7vw, 1.25rem)',
    lead_size_tablet: '1.1rem',
    lead_size_mobile: '1.05rem',
    lead_line: '1.7',
    lead_weight: '400',
    lead_tracking: '0em',

    body_size: '1rem',
    body_size_tablet: '1rem',
    body_size_mobile: '.975rem',
    body_line: '1.75',
    body_weight: '400',
    body_tracking: '0em',

    eyebrow_size: '.75rem',
    eyebrow_size_tablet: '.75rem',
    eyebrow_size_mobile: '.72rem',
    eyebrow_line: '1.35',
    eyebrow_weight: '500',
    eyebrow_tracking: '.28em',

    button_size: '.9rem',
    button_size_tablet: '.9rem',
    button_size_mobile: '.875rem',
    button_line: '1.25',
    button_weight: '700',
    button_tracking: '0em',
});

export const PREMIUM_SECTION_DEFAULTS = Object.freeze({
    py: '100px',
    py_tablet: '80px',
    py_mobile: '56px',
    px: '28px',
    px_tablet: '24px',
    px_mobile: '20px',
    gap: '56px',
    gap_tablet: '44px',
    gap_mobile: '32px',
    grid_gap: '24px',
    grid_gap_tablet: '20px',
    grid_gap_mobile: '16px',
    card_padding: '32px',
    card_padding_tablet: '28px',
    card_padding_mobile: '22px',
    container_narrow: '52rem',
    container_content: '72rem',
    container_default: '88rem',
    container_wide: '96rem',
    container_full: '100%',
});

export const PREMIUM_COMPONENT_DEFAULTS = Object.freeze({
    button_height: '52px',
    button_height_tablet: '50px',
    button_height_mobile: '48px',
    button_px: '24px',
    button_px_tablet: '22px',
    button_px_mobile: '18px',
    button_radius: '9999px',
    button_weight: '700',
    button_hover_shift: '-1px',
    card_radius: '24px',
    card_padding: '32px',
    card_padding_tablet: '28px',
    card_padding_mobile: '22px',
    card_shadow: 'var(--cosmic-shadow-sm)',
    image_radius: '24px',
    input_radius: '10px',
    modal_radius: '32px',
    section_radius: '32px',
    media_object_fit: 'cover',
});

const FONT_GROUPS = [
    { label: 'Modern / SaaS', options: [
        ['Manrope · Premium', sansStack('Manrope')],
        ['Inter · Clean', sansStack('Inter')],
        ['Plus Jakarta Sans · Refined', sansStack('Plus Jakarta Sans')],
        ['DM Sans · Friendly', sansStack('DM Sans')],
        ['Outfit · Contemporary', sansStack('Outfit')],
        ['Sora · Technical', sansStack('Sora')],
        ['Urbanist · Modern', sansStack('Urbanist')],
    ]},
    { label: 'Business / Clean', options: [
        ['Source Sans 3 · Professional', sansStack('Source Sans 3')],
        ['Work Sans · Neutral', sansStack('Work Sans')],
        ['Nunito Sans · Approachable', sansStack('Nunito Sans')],
        ['IBM Plex Sans · Structured', sansStack('IBM Plex Sans')],
        ['System Sans · Native', SYSTEM_STACK],
    ]},
    { label: 'Editorial / Premium', options: [
        ['Playfair Display · Luxury', serifStack('Playfair Display')],
        ['Cormorant Garamond · Elegant', serifStack('Cormorant Garamond')],
        ['Libre Baskerville · Classic', serifStack('Libre Baskerville')],
        ['Lora · Editorial', serifStack('Lora')],
        ['Georgia · Native Serif', SERIF_STACK],
    ]},
];
const FONT_OPTIONS = FONT_GROUPS.flatMap((group) => group.options.map(([label, value]) => ({ label, value })));

const ROLE_ROWS = [
    ['h1', 'H1', 'Primary hero / page title'],
    ['h2', 'H2', 'Major section heading'],
    ['h3', 'H3', 'Card groups / sub-sections'],
    ['h4', 'H4', 'Supporting heading'],
    ['h5', 'H5', 'Small heading'],
    ['h6', 'H6', 'Micro heading'],
    ['lead', 'Lead', 'Intro / hero supporting copy'],
    ['body', 'Body', 'Paragraph and standard copy'],
    ['eyebrow', 'Labels', 'Eyebrows and compact labels'],
    ['button', 'Buttons', 'CTA and action text'],
];

const SHADOW_OPTIONS = [
    ['None', 'none'],
    ['Soft · Premium', 'var(--cosmic-shadow-sm)'],
    ['Medium', 'var(--cosmic-shadow-md)'],
    ['Strong', 'var(--cosmic-shadow-lg)'],
];

const RADIUS_OPTIONS = [
    ['Sharp', '0px'],
    ['Subtle', '10px'],
    ['Balanced', '16px'],
    ['Premium', '24px'],
    ['Soft', '32px'],
    ['Pill', '9999px'],
];

const mergeTypography = (value) => ({
    ...PREMIUM_TYPOGRAPHY_DEFAULTS,
    ...(value && typeof value === 'object' ? value : {}),
});

const mergeStyling = (value) => {
    const source = value && typeof value === 'object' ? value : {};
    const looksLikeLegacyTypography = !source.typography && Object.keys(source).some((key) => key.startsWith('h1_') || key === 'font_display');
    const typography = mergeTypography(looksLikeLegacyTypography ? source : source.typography);
    const sectionLayout = {
        ...PREMIUM_SECTION_DEFAULTS,
        ...(source.section_layout && typeof source.section_layout === 'object' ? source.section_layout : {}),
    };
    const components = {
        ...PREMIUM_COMPONENT_DEFAULTS,
        ...(source.components && typeof source.components === 'object' ? source.components : {}),
    };

    // These tokens have both semantic-layout and legacy-component consumers.
    // Keep their popup draft synchronized so Apply cannot create two competing
    // global defaults for the same visual role.
    ['card_padding', 'card_padding_tablet', 'card_padding_mobile'].forEach((key) => {
        const sourceValue = source.components?.[key] ?? source.section_layout?.[key];
        if (sourceValue !== undefined && sourceValue !== null && sourceValue !== '') {
            components[key] = sourceValue;
            sectionLayout[key] = sourceValue;
        }
    });
    components.button_weight = typography.button_weight || components.button_weight;

    return { typography, section_layout: sectionLayout, components };
};

const previewSize = (value, fallback) => {
    const raw = String(value || '').trim();
    return raw || fallback;
};

const deviceValue = (settings, key, device) => {
    if (device === 'desktop') return settings[key];
    return settings[`${key}_${device}`] || settings[key];
};

function FontSelect({ label, value, onChange, light }) {
    const known = FONT_OPTIONS.some((option) => option.value === value);
    return (
        <label className="block">
            <span className="text-[10px] font-bold uppercase tracking-[.16em] text-slate-500">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={`mt-2 h-11 w-full rounded-xl border px-3 text-sm font-semibold outline-none transition focus:ring-2 focus:ring-violet-400/40 ${light ? 'border-slate-200 bg-white text-slate-900' : 'border-white/10 bg-black/20 text-white'}`}
            >
                {!known && value ? <option value={value}>Custom font</option> : null}
                {FONT_GROUPS.map((group) => (
                    <optgroup key={group.label} label={group.label}>
                        {group.options.map(([optionLabel, optionValue]) => <option key={`${group.label}-${optionLabel}`} value={optionValue}>{optionLabel}</option>)}
                    </optgroup>
                ))}
            </select>
        </label>
    );
}

function TokenInput({ label, value, onChange, light, compact = false, placeholder = '', invalid = false }) {
    return (
        <label className="block min-w-0">
            <span className="block truncate text-[9px] font-bold uppercase tracking-[.12em] text-slate-500">{label}</span>
            <input
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                spellCheck={false}
                placeholder={placeholder}
                aria-invalid={invalid || undefined}
                className={`mt-1.5 h-9 w-full min-w-0 rounded-lg border px-2.5 text-[11px] font-semibold outline-none transition focus:ring-2 focus:ring-violet-400/35 ${compact ? 'text-center' : ''} ${invalid ? 'border-rose-400 bg-rose-50/5 ring-1 ring-rose-400/25' : light ? 'border-slate-200 bg-white text-slate-800 placeholder:text-slate-300' : 'border-white/10 bg-black/20 text-slate-200 placeholder:text-slate-700'}`}
            />
        </label>
    );
}

function PresetSelect({ label, value, options, onChange, light }) {
    const known = options.some(([, optionValue]) => optionValue === value);
    return (
        <label className="block min-w-0">
            <span className="block truncate text-[9px] font-bold uppercase tracking-[.12em] text-slate-500">{label}</span>
            <select value={value ?? ''} onChange={(event) => onChange(event.target.value)} className={`mt-1.5 h-9 w-full rounded-lg border px-2.5 text-[11px] font-semibold outline-none transition focus:ring-2 focus:ring-violet-400/35 ${light ? 'border-slate-200 bg-white text-slate-800' : 'border-white/10 bg-black/20 text-slate-200'}`}>
                {!known && value ? <option value={value}>Custom · {value}</option> : null}
                {options.map(([optionLabel, optionValue]) => <option key={`${optionLabel}-${optionValue}`} value={optionValue}>{optionLabel}</option>)}
            </select>
        </label>
    );
}

function DeviceSwitch({ device, setDevice, light }) {
    return (
        <div className={`flex rounded-lg border p-0.5 ${light ? 'border-slate-200 bg-slate-100' : 'border-white/10 bg-black/20'}`}>
            {['desktop', 'tablet', 'mobile'].map((item) => (
                <button key={item} type="button" onClick={() => setDevice(item)} aria-pressed={device === item} title={item} className={`h-7 rounded-md px-2 text-[10px] font-bold uppercase transition ${device === item ? 'bg-violet-600 text-white' : light ? 'text-slate-500 hover:bg-white' : 'text-slate-500 hover:bg-white/5 hover:text-slate-300'}`}>
                    {item === 'desktop' ? 'D' : item === 'tablet' ? 'T' : 'M'}
                </button>
            ))}
        </div>
    );
}

function SettingsCard({ title, description, children, light }) {
    return (
        <div className={`rounded-2xl border p-4 ${light ? 'border-slate-200 bg-slate-50/60' : 'border-white/10 bg-white/[0.018]'}`}>
            <div>
                <p className={`text-xs font-bold ${light ? 'text-slate-950' : 'text-white'}`}>{title}</p>
                {description ? <p className="mt-1 text-[10px] leading-4 text-slate-500">{description}</p> : null}
            </div>
            <div className="mt-4">{children}</div>
        </div>
    );
}

const SAFE_MEASURE = /^-?(?:\d+|\d*\.\d+)(?:px|rem|em|%)?$|^clamp\(\s*-?(?:\d+|\d*\.\d+)(?:px|rem|em|%)?\s*,\s*-?(?:\d+|\d*\.\d+)(?:vw|vh|rem|em|px|%)\s*,\s*-?(?:\d+|\d*\.\d+)(?:px|rem|em|%)?\s*\)$/i;
const SAFE_NUMBER = /^-?(?:\d+|\d*\.\d+)$/;
const typographyValidation = (typography = {}) => {
    const errors = {};
    ROLE_ROWS.forEach(([role]) => {
        [`${role}_size`, `${role}_size_tablet`, `${role}_size_mobile`, `${role}_tracking`].forEach((key) => {
            if (!SAFE_MEASURE.test(String(typography[key] ?? '').trim())) errors[key] = true;
        });
        [`${role}_line`, `${role}_weight`].forEach((key) => {
            if (!SAFE_NUMBER.test(String(typography[key] ?? '').trim())) errors[key] = true;
        });
        const weight = Number(typography[`${role}_weight`]);
        if (!Number.isFinite(weight) || weight < 100 || weight > 900 || weight % 100 !== 0) errors[`${role}_weight`] = true;
        const line = Number(typography[`${role}_line`]);
        if (!Number.isFinite(line) || line < .7 || line > 3) errors[`${role}_line`] = true;
    });
    return errors;
};

const SAFE_POSITIVE_MEASURE = /^(?:0|(?:\d+|\d*\.\d+)(?:px|rem|em|%|vw|vh|svh)?)$/i;
const SAFE_SIGNED_MEASURE = /^-?(?:\d+|\d*\.\d+)(?:px|rem|em|%|vw|vh|svh)?$/i;
const designValidation = (layout = {}, components = {}) => {
    const errors = {};
    const positiveLayout = [
        'py','py_tablet','py_mobile','px','px_tablet','px_mobile',
        'gap','gap_tablet','gap_mobile','grid_gap','grid_gap_tablet','grid_gap_mobile',
        'card_padding','card_padding_tablet','card_padding_mobile',
        'container_narrow','container_content','container_default','container_wide',
    ];
    positiveLayout.forEach((key) => {
        if (!SAFE_POSITIVE_MEASURE.test(String(layout[key] ?? '').trim())) errors[`section_layout.${key}`] = true;
    });
    const positiveComponents = [
        'button_height','button_height_tablet','button_height_mobile',
        'button_px','button_px_tablet','button_px_mobile',
        'card_radius','card_padding','card_padding_tablet','card_padding_mobile',
        'image_radius','input_radius','modal_radius','section_radius',
    ];
    positiveComponents.forEach((key) => {
        if (!SAFE_POSITIVE_MEASURE.test(String(components[key] ?? '').trim())) errors[`components.${key}`] = true;
    });
    if (!SAFE_SIGNED_MEASURE.test(String(components.button_hover_shift ?? '').trim())) errors['components.button_hover_shift'] = true;
    const buttonWeight = Number(components.button_weight);
    if (!Number.isFinite(buttonWeight) || buttonWeight < 100 || buttonWeight > 900 || buttonWeight % 100 !== 0) errors['components.button_weight'] = true;
    if (!['cover','contain'].includes(String(components.media_object_fit || ''))) errors['components.media_object_fit'] = true;
    const safeShadows = ['none','var(--cosmic-shadow-sm)','var(--cosmic-shadow-md)','var(--cosmic-shadow-lg)'];
    if (!safeShadows.includes(String(components.card_shadow || ''))) errors['components.card_shadow'] = true;
    return errors;
};

function TypographyPreview({ draft, device, light }) {
    const suffix = device === 'desktop' ? '' : `_${device}`;
    const preview = {
        h1: previewSize(draft[`h1_size${suffix}`], draft.h1_size),
        h2: previewSize(draft[`h2_size${suffix}`], draft.h2_size),
        lead: previewSize(draft[`lead_size${suffix}`], draft.lead_size),
        body: previewSize(draft[`body_size${suffix}`], draft.body_size),
        eyebrow: previewSize(draft[`eyebrow_size${suffix}`], draft.eyebrow_size),
        button: previewSize(draft[`button_size${suffix}`], draft.button_size),
    };

    return (
        <div style={{ fontFamily: draft.font_body }}>
            <p style={{ fontFamily: draft.font_body, fontSize: preview.eyebrow, lineHeight: draft.eyebrow_line, fontWeight: draft.eyebrow_weight, letterSpacing: draft.eyebrow_tracking }} className="uppercase text-violet-500">Cosmic Studio</p>
            <div style={{ fontFamily: draft.font_display, fontSize: preview.h1, lineHeight: draft.h1_line, fontWeight: draft.h1_weight, letterSpacing: draft.h1_tracking }} className={`mt-3 max-w-full break-words ${light ? 'text-slate-950' : 'text-white'}`}>Premium by default.</div>
            <div style={{ fontFamily: draft.font_display, fontSize: preview.h2, lineHeight: draft.h2_line, fontWeight: draft.h2_weight, letterSpacing: draft.h2_tracking }} className={`mt-4 ${light ? 'text-slate-800' : 'text-slate-200'}`}>One design system.</div>
            <p style={{ fontSize: preview.lead, lineHeight: draft.lead_line, fontWeight: draft.lead_weight, letterSpacing: draft.lead_tracking }} className={`mt-3 ${light ? 'text-slate-600' : 'text-slate-400'}`}>Website-wide typography stays consistent across responsive sizes.</p>
            <p style={{ fontSize: preview.body, lineHeight: draft.body_line, fontWeight: draft.body_weight, letterSpacing: draft.body_tracking }} className="mt-3 text-slate-500">Individual Sparks can still keep a local override when needed.</p>
            <button type="button" tabIndex={-1} style={{ fontFamily: draft.font_body, fontSize: preview.button, lineHeight: draft.button_line, fontWeight: draft.button_weight, letterSpacing: draft.button_tracking }} className="mt-4 rounded-full bg-violet-600 px-5 py-2.5 text-white">Sample button</button>
        </div>
    );
}

function DesignSystemPreview({ layout, components, device, light }) {
    const sectionPy = deviceValue(layout, 'py', device);
    const sectionPx = deviceValue(layout, 'px', device);
    const gridGap = deviceValue(layout, 'grid_gap', device);
    const cardPadding = deviceValue(components, 'card_padding', device) || deviceValue(layout, 'card_padding', device);
    const buttonHeight = deviceValue(components, 'button_height', device);
    const buttonPx = deviceValue(components, 'button_px', device);
    const deviceWidth = device === 'desktop' ? '100%' : device === 'tablet' ? '78%' : '52%';

    return (
        <div className="mx-auto transition-all duration-200" style={{ width: deviceWidth, maxWidth: '100%' }}>
            <div className={`overflow-hidden border ${light ? 'border-slate-200 bg-white' : 'border-white/10 bg-[#17191f]'}`} style={{ borderRadius: components.section_radius || '32px', padding: `${sectionPy || '56px'} ${sectionPx || '20px'}` }}>
                <p className="text-[9px] font-bold uppercase tracking-[.2em] text-violet-500">Design system preview</p>
                <h4 className={`mt-2 text-lg font-semibold ${light ? 'text-slate-950' : 'text-white'}`}>Spacing, corners and effects</h4>
                <div className="mt-4 grid grid-cols-2" style={{ gap: gridGap || '20px' }}>
                    {[1, 2].map((item) => (
                        <div key={item} className={`border ${light ? 'border-slate-200 bg-slate-50' : 'border-white/10 bg-white/[0.04]'}`} style={{ borderRadius: components.card_radius || '24px', padding: cardPadding || '24px', boxShadow: components.card_shadow || 'none' }}>
                            <div className="aspect-[4/3] w-full bg-gradient-to-br from-violet-500/20 to-cyan-400/10" style={{ borderRadius: components.image_radius || '24px' }} />
                            <p className={`mt-3 text-xs font-bold ${light ? 'text-slate-900' : 'text-white'}`}>Premium card</p>
                            <p className="mt-1 text-[10px] leading-4 text-slate-500">Shared global tokens keep every Spark visually related.</p>
                        </div>
                    ))}
                </div>
                <div className="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" tabIndex={-1} className="inline-flex items-center justify-center bg-violet-600 text-[11px] font-bold text-white" style={{ minHeight: buttonHeight || '48px', paddingInline: buttonPx || '18px', borderRadius: components.button_radius || '9999px', fontWeight: components.button_weight || '700' }}>Primary action</button>
                    <button type="button" tabIndex={-1} className={`inline-flex items-center justify-center border text-[11px] font-bold ${light ? 'border-slate-300 bg-white text-slate-700' : 'border-white/10 bg-white/[0.04] text-slate-200'}`} style={{ minHeight: buttonHeight || '48px', paddingInline: buttonPx || '18px', borderRadius: components.button_radius || '9999px', fontWeight: components.button_weight || '700' }}>Secondary</button>
                </div>
            </div>
        </div>
    );
}

export default function GlobalStylingModal({ open, value, onCancel, onApply, trialMode = false, darkMode = true }) {
    const light = trialMode || !darkMode;
    const [draft, setDraft] = useState(() => mergeStyling(value));
    const [device, setDevice] = useState('desktop');
    const [tab, setTab] = useState('typography');

    useEffect(() => {
        if (!open) return;
        setDraft(mergeStyling(value));
        setDevice('desktop');
        setTab('typography');
    }, [open, value]);

    useEffect(() => {
        if (!open) return undefined;
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') onCancel?.();
        };
        document.addEventListener('keydown', closeOnEscape);
        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [open, onCancel]);

    const previewNote = useMemo(() => device === 'desktop' ? 'Desktop' : device === 'tablet' ? 'Tablet' : 'Mobile', [device]);
    const baseline = useMemo(() => mergeStyling(value), [value]);
    const hasChanges = useMemo(() => JSON.stringify(draft) !== JSON.stringify(baseline), [draft, baseline]);
    const typographyErrors = useMemo(() => typographyValidation(draft.typography), [draft.typography]);
    const designErrors = useMemo(() => designValidation(draft.section_layout, draft.components), [draft.section_layout, draft.components]);
    const typographyValid = Object.keys(typographyErrors).length === 0;
    const designValid = Object.keys(designErrors).length === 0;
    const allValid = typographyValid && designValid;

    if (!open) return null;

    const setTypographyToken = (key, nextValue) => setDraft((current) => ({
        ...current,
        typography: { ...current.typography, [key]: nextValue },
        components: key === 'button_weight'
            ? { ...current.components, button_weight: nextValue }
            : current.components,
    }));
    const setLayoutToken = (key, nextValue) => setDraft((current) => ({
        ...current,
        section_layout: { ...current.section_layout, [key]: nextValue },
    }));
    const setComponentToken = (key, nextValue) => setDraft((current) => ({
        ...current,
        components: { ...current.components, [key]: nextValue },
    }));
    const setButtonWeight = (nextValue) => setDraft((current) => ({
        ...current,
        typography: { ...current.typography, button_weight: nextValue },
        components: { ...current.components, button_weight: nextValue },
    }));
    const setCardPadding = (key, nextValue) => setDraft((current) => ({
        ...current,
        section_layout: { ...current.section_layout, [key]: nextValue },
        components: { ...current.components, [key]: nextValue },
    }));

    const resetTypography = () => setDraft((current) => ({
        ...current,
        typography: { ...PREMIUM_TYPOGRAPHY_DEFAULTS },
        components: { ...current.components, button_weight: PREMIUM_TYPOGRAPHY_DEFAULTS.button_weight },
    }));
    const resetDesignSystem = () => setDraft((current) => ({
        ...current,
        section_layout: { ...PREMIUM_SECTION_DEFAULTS },
        components: {
            ...PREMIUM_COMPONENT_DEFAULTS,
            button_weight: current.typography.button_weight || PREMIUM_COMPONENT_DEFAULTS.button_weight,
        },
    }));

    return (
        <div
            className="fixed inset-0 z-[10090] flex items-center justify-center bg-black/60 p-3 backdrop-blur-sm sm:p-5"
            style={{ backgroundColor: 'rgba(2, 6, 23, 0.62)', backdropFilter: 'blur(8px)' }}
            data-cosmic-app-modal="global-styling"
            data-appearance={light ? 'light' : 'dark'}
            role="dialog"
            aria-modal="true"
            aria-labelledby="cosmic-global-styling-title"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) onCancel?.();
            }}
        >
            <div className={`cosmic-global-styling-dialog flex max-h-[94vh] w-[min(96vw,90rem)] flex-col overflow-hidden rounded-3xl border shadow-2xl ${light ? 'border-slate-200 bg-white text-slate-900' : 'border-white/10 bg-[#111318] text-white'}`}>
                <div className={`flex items-start justify-between gap-5 border-b px-5 py-4 sm:px-6 ${light ? 'border-slate-200' : 'border-white/10'}`}>
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-[.18em] text-violet-500">Design · Global Styling</p>
                        <h3 id="cosmic-global-styling-title" className={`mt-1 text-xl font-semibold ${light ? 'text-slate-950' : 'text-white'}`}>Website Design System</h3>
                        <p className={`mt-1 max-w-2xl text-xs leading-5 ${light ? 'text-slate-600' : 'text-slate-400'}`}>Premium defaults are already active. Customize the global system here; individual Spark overrides can still win locally.</p>
                    </div>
                    <button type="button" onClick={onCancel} className={`h-9 w-9 rounded-xl text-lg transition ${light ? 'text-slate-500 hover:bg-slate-100 hover:text-slate-900' : 'text-slate-400 hover:bg-white/10 hover:text-white'}`} aria-label="Close Global Styling">×</button>
                </div>

                <div className={`flex gap-1 border-b px-5 pt-3 sm:px-6 ${light ? 'border-slate-200' : 'border-white/10'}`} role="tablist" aria-label="Global Styling categories">
                    {[
                        ['typography', 'Aa', 'Typography'],
                        ['design', '◇', 'Layout & Components'],
                    ].map(([key, icon, label]) => (
                        <button key={key} type="button" role="tab" data-active={tab === key ? 'true' : 'false'} aria-selected={tab === key} onClick={() => setTab(key)} className={`cosmic-global-styling-tab rounded-t-xl border border-b-0 px-4 py-2.5 text-xs font-bold transition ${tab === key ? (light ? 'border-violet-300 bg-violet-600 text-white' : 'border-violet-400/40 bg-violet-500/20 text-violet-100') : (light ? 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-violet-700' : 'border-transparent text-slate-500 hover:text-violet-300')}`}>
                            <span className="mr-2" aria-hidden="true">{icon}</span>{label}
                        </button>
                    ))}
                </div>

                <div className="grid min-h-0 flex-1 lg:grid-cols-[minmax(300px,.82fr)_minmax(0,1.7fr)]">
                    <aside className={`min-h-0 overflow-y-auto border-b p-5 lg:border-b-0 lg:border-r sm:p-6 ${light ? 'border-slate-200 bg-slate-50/70' : 'border-white/10 bg-black/15'}`}>
                        {tab === 'typography' ? (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                                    <FontSelect label="Heading font" value={draft.typography.font_display} onChange={(next) => setTypographyToken('font_display', next)} light={light} />
                                    <FontSelect label="Body font" value={draft.typography.font_body} onChange={(next) => setTypographyToken('font_body', next)} light={light} />
                                </div>
                                <div className={`mt-5 rounded-2xl border p-4 ${light ? 'border-slate-200 bg-white' : 'border-white/10 bg-white/[0.025]'}`}>
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className={`text-xs font-bold ${light ? 'text-slate-950' : 'text-white'}`}>Live popup preview</p>
                                            <p className="mt-0.5 text-[10px] text-slate-500">{previewNote} · Builder stays frozen until Apply.</p>
                                        </div>
                                        <DeviceSwitch device={device} setDevice={setDevice} light={light} />
                                    </div>
                                    <div className={`mt-4 overflow-hidden rounded-2xl border p-5 ${light ? 'border-slate-200 bg-white' : 'border-white/10 bg-[#17191f]'}`}>
                                        <TypographyPreview draft={draft.typography} device={device} light={light} />
                                    </div>
                                </div>
                            </>
                        ) : (
                            <div className={`rounded-2xl border p-4 ${light ? 'border-slate-200 bg-white' : 'border-white/10 bg-white/[0.025]'}`}>
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className={`text-xs font-bold ${light ? 'text-slate-950' : 'text-white'}`}>Design preview</p>
                                        <p className="mt-0.5 text-[10px] text-slate-500">{previewNote} · preview only.</p>
                                    </div>
                                    <DeviceSwitch device={device} setDevice={setDevice} light={light} />
                                </div>
                                <div className="mt-4">
                                    <DesignSystemPreview layout={draft.section_layout} components={draft.components} device={device} light={light} />
                                </div>
                                <div className={`mt-4 rounded-xl border p-3 text-[10px] leading-5 ${light ? 'border-violet-100 bg-violet-50 text-violet-800' : 'border-violet-400/15 bg-violet-500/[0.07] text-violet-200'}`}>
                                    Global values are the fallback. A section, card, image or button with an intentional local override remains untouched.
                                </div>
                            </div>
                        )}
                    </aside>

                    <section className="min-h-0 overflow-y-auto p-5 sm:p-6">
                        {tab === 'typography' ? (
                            <>
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className={`text-sm font-bold ${light ? 'text-slate-950' : 'text-white'}`}>Responsive type scale</p>
                                        <p className="mt-1 text-[11px] leading-5 text-slate-500">Sizes accept px, rem, em, %, or a safe clamp() value. Line-height and weight stay unitless.</p>
                                    </div>
                                    <button type="button" onClick={resetTypography} className={`rounded-xl border px-3 py-2 text-[11px] font-bold transition ${light ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' : 'border-white/10 text-slate-300 hover:bg-white/5'}`}>Reset Typography</button>
                                </div>
                                {!typographyValid ? <div className="mt-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-[10px] font-semibold leading-5 text-rose-700">Fix the highlighted typography values before applying. Sizes accept px/rem/em/%/clamp(); weights must be 100–900; line-height must be 0.7–3.</div> : null}
                                <div className="mt-4 space-y-2.5">
                                    {ROLE_ROWS.map(([role, label, description]) => (
                                        <div key={role} className={`rounded-2xl border p-3.5 ${light ? 'border-slate-200 bg-slate-50/60' : 'border-white/10 bg-white/[0.018]'}`}>
                                            <div className="grid gap-3 xl:grid-cols-[150px_repeat(3,minmax(88px,1fr))_82px_76px_86px] xl:items-end">
                                                <div className="min-w-0 pb-0.5">
                                                    <p className={`text-xs font-bold ${light ? 'text-slate-950' : 'text-white'}`}>{label}</p>
                                                    <p className="mt-0.5 text-[9px] leading-4 text-slate-500">{description}</p>
                                                </div>
                                                <TokenInput label="Desktop" value={draft.typography[`${role}_size`]} onChange={(next) => setTypographyToken(`${role}_size`, next)} light={light} invalid={Boolean(typographyErrors[`${role}_size`])} />
                                                <TokenInput label="Tablet" value={draft.typography[`${role}_size_tablet`]} onChange={(next) => setTypographyToken(`${role}_size_tablet`, next)} light={light} invalid={Boolean(typographyErrors[`${role}_size_tablet`])} />
                                                <TokenInput label="Mobile" value={draft.typography[`${role}_size_mobile`]} onChange={(next) => setTypographyToken(`${role}_size_mobile`, next)} light={light} invalid={Boolean(typographyErrors[`${role}_size_mobile`])} />
                                                <TokenInput label="Weight" value={draft.typography[`${role}_weight`]} onChange={(next) => setTypographyToken(`${role}_weight`, next)} light={light} compact invalid={Boolean(typographyErrors[`${role}_weight`])} />
                                                <TokenInput label="Line" value={draft.typography[`${role}_line`]} onChange={(next) => setTypographyToken(`${role}_line`, next)} light={light} compact invalid={Boolean(typographyErrors[`${role}_line`])} />
                                                <TokenInput label="Tracking" value={draft.typography[`${role}_tracking`]} onChange={(next) => setTypographyToken(`${role}_tracking`, next)} light={light} compact invalid={Boolean(typographyErrors[`${role}_tracking`])} />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className={`text-sm font-bold ${light ? 'text-slate-950' : 'text-white'}`}>Layout & component tokens</p>
                                        <p className="mt-1 text-[11px] leading-5 text-slate-500">Control site-wide spacing, containers, buttons, cards, media corners and effects without editing every Spark.</p>
                                    </div>
                                    <button type="button" onClick={resetDesignSystem} className={`rounded-xl border px-3 py-2 text-[11px] font-bold transition ${light ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' : 'border-white/10 text-slate-300 hover:bg-white/5'}`}>Reset Design System</button>
                                </div>
                                {!designValid ? <div className="mt-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-[10px] font-semibold leading-5 text-rose-700">Fix the highlighted design values before applying. Spacing, widths, radius, heights and padding must be safe CSS measurements; button weight must be 100–900.</div> : null}

                                <div className="mt-4 grid gap-3 xl:grid-cols-2">
                                    <SettingsCard title="Section spacing" description="Global vertical and horizontal breathing room." light={light}>
                                        <div className="grid grid-cols-3 gap-2.5">
                                            <TokenInput label="Y Desktop" value={draft.section_layout.py} onChange={(next) => setLayoutToken('py', next)} light={light} invalid={Boolean(designErrors['section_layout.py'])} />
                                            <TokenInput label="Y Tablet" value={draft.section_layout.py_tablet} onChange={(next) => setLayoutToken('py_tablet', next)} light={light} invalid={Boolean(designErrors['section_layout.py_tablet'])} />
                                            <TokenInput label="Y Mobile" value={draft.section_layout.py_mobile} onChange={(next) => setLayoutToken('py_mobile', next)} light={light} invalid={Boolean(designErrors['section_layout.py_mobile'])} />
                                            <TokenInput label="X Desktop" value={draft.section_layout.px} onChange={(next) => setLayoutToken('px', next)} light={light} invalid={Boolean(designErrors['section_layout.px'])} />
                                            <TokenInput label="X Tablet" value={draft.section_layout.px_tablet} onChange={(next) => setLayoutToken('px_tablet', next)} light={light} invalid={Boolean(designErrors['section_layout.px_tablet'])} />
                                            <TokenInput label="X Mobile" value={draft.section_layout.px_mobile} onChange={(next) => setLayoutToken('px_mobile', next)} light={light} invalid={Boolean(designErrors['section_layout.px_mobile'])} />
                                        </div>
                                    </SettingsCard>

                                    <SettingsCard title="Content rhythm" description="Spacing between large groups and responsive grids." light={light}>
                                        <div className="grid grid-cols-3 gap-2.5">
                                            <TokenInput label="Stack D" value={draft.section_layout.gap} onChange={(next) => setLayoutToken('gap', next)} light={light} invalid={Boolean(designErrors['section_layout.gap'])} />
                                            <TokenInput label="Stack T" value={draft.section_layout.gap_tablet} onChange={(next) => setLayoutToken('gap_tablet', next)} light={light} invalid={Boolean(designErrors['section_layout.gap_tablet'])} />
                                            <TokenInput label="Stack M" value={draft.section_layout.gap_mobile} onChange={(next) => setLayoutToken('gap_mobile', next)} light={light} invalid={Boolean(designErrors['section_layout.gap_mobile'])} />
                                            <TokenInput label="Grid D" value={draft.section_layout.grid_gap} onChange={(next) => setLayoutToken('grid_gap', next)} light={light} invalid={Boolean(designErrors['section_layout.grid_gap'])} />
                                            <TokenInput label="Grid T" value={draft.section_layout.grid_gap_tablet} onChange={(next) => setLayoutToken('grid_gap_tablet', next)} light={light} invalid={Boolean(designErrors['section_layout.grid_gap_tablet'])} />
                                            <TokenInput label="Grid M" value={draft.section_layout.grid_gap_mobile} onChange={(next) => setLayoutToken('grid_gap_mobile', next)} light={light} invalid={Boolean(designErrors['section_layout.grid_gap_mobile'])} />
                                        </div>
                                    </SettingsCard>

                                    <SettingsCard title="Container widths" description="Premium content ceilings for narrow, standard and wide sections." light={light}>
                                        <div className="grid grid-cols-2 gap-2.5">
                                            <TokenInput label="Narrow" value={draft.section_layout.container_narrow} onChange={(next) => setLayoutToken('container_narrow', next)} light={light} invalid={Boolean(designErrors['section_layout.container_narrow'])} />
                                            <TokenInput label="Content" value={draft.section_layout.container_content} onChange={(next) => setLayoutToken('container_content', next)} light={light} invalid={Boolean(designErrors['section_layout.container_content'])} />
                                            <TokenInput label="Default" value={draft.section_layout.container_default} onChange={(next) => setLayoutToken('container_default', next)} light={light} invalid={Boolean(designErrors['section_layout.container_default'])} />
                                            <TokenInput label="Wide" value={draft.section_layout.container_wide} onChange={(next) => setLayoutToken('container_wide', next)} light={light} invalid={Boolean(designErrors['section_layout.container_wide'])} />
                                        </div>
                                    </SettingsCard>

                                    <SettingsCard title="Cards" description="Padding, corner language and global card elevation." light={light}>
                                        <div className="grid grid-cols-3 gap-2.5">
                                            <TokenInput label="Padding D" value={draft.components.card_padding} onChange={(next) => setCardPadding('card_padding', next)} light={light} invalid={Boolean(designErrors['components.card_padding'])} />
                                            <TokenInput label="Padding T" value={draft.components.card_padding_tablet} onChange={(next) => setCardPadding('card_padding_tablet', next)} light={light} invalid={Boolean(designErrors['components.card_padding_tablet'])} />
                                            <TokenInput label="Padding M" value={draft.components.card_padding_mobile} onChange={(next) => setCardPadding('card_padding_mobile', next)} light={light} invalid={Boolean(designErrors['components.card_padding_mobile'])} />
                                        </div>
                                        <div className="mt-3 grid grid-cols-2 gap-2.5">
                                            <PresetSelect label="Card radius" value={draft.components.card_radius} options={RADIUS_OPTIONS.filter(([, value]) => value !== '9999px')} onChange={(next) => setComponentToken('card_radius', next)} light={light} />
                                            <PresetSelect label="Card shadow" value={draft.components.card_shadow} options={SHADOW_OPTIONS} onChange={(next) => setComponentToken('card_shadow', next)} light={light} />
                                        </div>
                                    </SettingsCard>

                                    <SettingsCard title="Buttons" description="Shared sizing and corner style for primary and secondary CTAs." light={light}>
                                        <div className="grid grid-cols-3 gap-2.5">
                                            <TokenInput label="Height D" value={draft.components.button_height} onChange={(next) => setComponentToken('button_height', next)} light={light} invalid={Boolean(designErrors['components.button_height'])} />
                                            <TokenInput label="Height T" value={draft.components.button_height_tablet} onChange={(next) => setComponentToken('button_height_tablet', next)} light={light} invalid={Boolean(designErrors['components.button_height_tablet'])} />
                                            <TokenInput label="Height M" value={draft.components.button_height_mobile} onChange={(next) => setComponentToken('button_height_mobile', next)} light={light} invalid={Boolean(designErrors['components.button_height_mobile'])} />
                                            <TokenInput label="Padding D" value={draft.components.button_px} onChange={(next) => setComponentToken('button_px', next)} light={light} invalid={Boolean(designErrors['components.button_px'])} />
                                            <TokenInput label="Padding T" value={draft.components.button_px_tablet} onChange={(next) => setComponentToken('button_px_tablet', next)} light={light} invalid={Boolean(designErrors['components.button_px_tablet'])} />
                                            <TokenInput label="Padding M" value={draft.components.button_px_mobile} onChange={(next) => setComponentToken('button_px_mobile', next)} light={light} invalid={Boolean(designErrors['components.button_px_mobile'])} />
                                        </div>
                                        <div className="mt-3 grid grid-cols-3 gap-2.5">
                                            <PresetSelect label="Radius" value={draft.components.button_radius} options={RADIUS_OPTIONS} onChange={(next) => setComponentToken('button_radius', next)} light={light} />
                                            <TokenInput label="Weight" value={draft.components.button_weight} onChange={setButtonWeight} light={light} compact invalid={Boolean(designErrors['components.button_weight'])} />
                                            <TokenInput label="Hover shift" value={draft.components.button_hover_shift} onChange={(next) => setComponentToken('button_hover_shift', next)} light={light} invalid={Boolean(designErrors['components.button_hover_shift'])} compact />
                                        </div>
                                    </SettingsCard>

                                    <SettingsCard title="Corners & media" description="Keep images, fields, popups and framed sections visually consistent." light={light}>
                                        <div className="grid grid-cols-2 gap-2.5">
                                            <PresetSelect label="Image radius" value={draft.components.image_radius} options={RADIUS_OPTIONS.filter(([, value]) => value !== '9999px')} onChange={(next) => setComponentToken('image_radius', next)} light={light} />
                                            <PresetSelect label="Section radius" value={draft.components.section_radius} options={RADIUS_OPTIONS.filter(([, value]) => value !== '9999px')} onChange={(next) => setComponentToken('section_radius', next)} light={light} />
                                            <PresetSelect label="Input radius" value={draft.components.input_radius} options={RADIUS_OPTIONS.filter(([, value]) => !['9999px', '32px'].includes(value))} onChange={(next) => setComponentToken('input_radius', next)} light={light} />
                                            <PresetSelect label="Modal radius" value={draft.components.modal_radius} options={RADIUS_OPTIONS.filter(([, value]) => value !== '9999px')} onChange={(next) => setComponentToken('modal_radius', next)} light={light} />
                                        </div>
                                        <div className="mt-3">
                                            <PresetSelect label="Image fit" value={draft.components.media_object_fit} options={[["Cover · Premium", "cover"], ["Contain", "contain"]]} onChange={(next) => setComponentToken('media_object_fit', next)} light={light} />
                                        </div>
                                    </SettingsCard>
                                </div>
                            </>
                        )}
                    </section>
                </div>

                <div className={`flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4 sm:px-6 ${light ? 'border-slate-200 bg-slate-50' : 'border-white/10 bg-white/[0.02]'}`}>
                    <p className="text-[10px] text-slate-500">Apply updates the Builder draft only. Save Draft / Publish remains the final website save step.</p>
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={onCancel} className={`rounded-xl border px-4 py-2 text-xs font-bold transition ${light ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' : 'border-white/10 text-slate-300 hover:bg-white/5'}`}>Cancel</button>
                        <button type="button" disabled={!hasChanges || !allValid} onClick={() => onApply?.({ typography: draft.typography, section_layout: draft.section_layout, components: draft.components })} className="rounded-xl bg-violet-600 px-5 py-2 text-xs font-bold text-white transition hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-40">{!typographyValid ? 'Fix Typography Values' : !designValid ? 'Fix Design Values' : hasChanges ? 'Apply Global Styles' : 'No Changes'}</button>
                    </div>
                </div>
            </div>
        </div>
    );
}
