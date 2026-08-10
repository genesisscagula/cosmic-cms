import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';
import { showCosmicNotification } from '../../../Components/CosmicNotification';
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import { BlockRegistry } from '../BlockRegistry';
import ThemeSelector from '../Theme/ThemeSelector';

const clone = (value) => typeof structuredClone === 'function' ? structuredClone(value) : JSON.parse(JSON.stringify(value));

const previewThemeCycle = ['primary', 'white', 'surface', 'white', 'primary', 'surface'];

function buildBlocks(template, previewMode = false) {
    return (template.sections || []).map((type, index) => {
        const previewTheme = previewThemeCycle[index % previewThemeCycle.length];

        return {
            ...(clone(BlockRegistry[type]?.schema?.defaults || {})),
            type,
            theme: previewMode ? previewTheme : 'auto',
            resolvedTheme: previewMode ? previewTheme : 'auto',
        };
    }).filter((block) => BlockRegistry[block.type]);
}

function TemplateMiniPreview({ template, websiteTheme }) {
    const blocks = buildBlocks(template, true).slice(0, 6);

    return (
        <div className="h-52 overflow-hidden rounded-xl bg-white text-slate-900">
            <div className="origin-top-left w-[400%]" style={{ transform: 'scale(.25)' }}>
                {blocks.map((block, index) => {
                    const Component = BlockRegistry[block.type]?.component;
                    return Component ? (
                        <Component
                            key={`${block.type}-${index}`}
                            block={block}
                            blockIndex={index}
                            globalTheme={websiteTheme}
                            onUpdate={() => {}}
                            blogPosts={[]}
                        />
                    ) : null;
                })}
            </div>
        </div>
    );
}

export default function PageTemplatesModal({
    open,
    onClose,
    onInstall,
    websiteContext = '',
    websiteId = null,
    trialMode = false,
    trialToken = null,
    websiteTheme = null,
    themeValue = 'midnight',
    onThemeChange,
    themeAccess,
    customTheme,
    hasLogo,
    brandMatchNeeded,
    onMatchBrandToLogo,
    brandMatchBusy,
}) {
    const { setBalance } = useCreditBalance();
    const [templates, setTemplates] = useState([]);
    const [tab, setTab] = useState('marketplace');
    const [query, setQuery] = useState('');
    const [tag, setTag] = useState('All');
    const [busy, setBusy] = useState(null);
    const [selected, setSelected] = useState(null);
    const [preview, setPreview] = useState(null);
    const [mode, setMode] = useState('generic');
    const [instruction, setInstruction] = useState('');
    const [confirmInstall, setConfirmInstall] = useState(false);

    useEffect(() => {
        if (!open) return;

        axios
            .get(trialMode && trialToken ? `/trial-assets/${trialToken}/templates` : '/page-templates/catalog')
            .then(({ data }) => setTemplates(data.templates || []))
            .catch(() => showCosmicNotification({
                title: 'Could not load Templates',
                message: 'Please refresh and try again.',
                tone: 'error',
            }));
    }, [open, trialMode, trialToken]);

    const tags = useMemo(
        () => ['All', ...new Set(templates.flatMap((item) => item.tags || []))],
        [templates],
    );

    const visible = useMemo(() => templates.filter((item) => {
        if (tab === 'owned' && !item.owned) return false;
        if (tab === 'favorites' && !item.favorited) return false;
        if (tag !== 'All' && !(item.tags || []).includes(tag)) return false;

        return `${item.name} ${item.description} ${(item.tags || []).join(' ')}`
            .toLowerCase()
            .includes(query.trim().toLowerCase());
    }), [templates, tab, tag, query]);

    const isInstalling = Boolean(selected && busy === `install-${selected.key}`);
    const isPersonalizing = Boolean(isInstalling && mode === 'personalized');

    if (!open) return null;

    const unlock = async (template) => {
        setBusy(template.key);

        try {
            const { data } = await axios.post(
                trialMode && trialToken
                    ? `/trial-assets/${trialToken}/templates/${template.key}/unlock`
                    : `/page-templates/${template.key}/unlock`,
            );

            setTemplates((items) => items.map((item) => (
                item.key === template.key
                    ? { ...item, owned: true, purchased: true }
                    : item
            )));

            if (preview?.key === template.key) {
                setPreview((item) => ({ ...item, owned: true, purchased: true }));
            }

            setBalance(data.credit_balance);
            showCosmicNotification({ title: 'Template owned', message: data.message, tone: 'success' });
        } catch (error) {
            showCosmicNotification({
                title: 'Could not purchase Template',
                message: error.response?.data?.message || 'Please check your credits and try again.',
                tone: 'error',
            });
        } finally {
            setBusy(null);
        }
    };

    const favorite = async (template) => {
        setBusy(`fav-${template.key}`);

        try {
            const { data } = await axios.post(
                trialMode && trialToken
                    ? `/trial-assets/${trialToken}/templates/${template.key}/favorite`
                    : `/page-templates/${template.key}/favorite`,
            );

            setTemplates((items) => items.map((item) => (
                item.key === template.key ? { ...item, favorited: data.favorited } : item
            )));

            if (preview?.key === template.key) {
                setPreview((item) => ({ ...item, favorited: data.favorited }));
            }
        } catch (error) {
            showCosmicNotification({
                title: 'Could not update Favorite',
                message: error.response?.data?.message || 'Please try again.',
                tone: 'error',
            });
        } finally {
            setBusy(null);
        }
    };

    const install = async () => {
        if (!selected || busy) return;

        setBusy(`install-${selected.key}`);

        try {
            let blocks = buildBlocks(selected);

            if (mode === 'personalized') {
                if (trialMode) {
                    throw new Error(
                        'AI personalization is available after sign up. Install Generic now and your owned Template will transfer to your account.',
                    );
                }

                const prompt = [
                    websiteContext || 'Create professional website content.',
                    instruction.trim() || `Personalize the ${selected.name} page template for this business. Keep the selected layout and section order.`,
                ].join('\n\n');

                const { data } = await axios.post('/ai/generate-content', {
                    prompt,
                    sections: selected.sections,
                    generation_type: 'template',
                    website_id: websiteId,
                });

                if (!data.blocks?.length) {
                    throw new Error('Cosmic AI did not return template content.');
                }

                blocks = data.blocks;
                setBalance(data.credit_balance);
            }

            const installed = onInstall(blocks, selected);
            if (installed === false) return;

            showCosmicNotification({
                title: 'Template installed',
                message: `${selected.name} is now on this page.`,
                tone: 'success',
            });

            setSelected(null);
            setPreview(null);
            setInstruction('');
            setMode('generic');
            onClose();
        } catch (error) {
            showCosmicNotification({
                title: 'Could not install Template',
                message: error.response?.data?.message || error.message || 'Please try again.',
                tone: 'error',
            });
        } finally {
            setBusy(null);
        }
    };

    return (
        <div className="cosmic-page-templates fixed inset-0 z-[920] flex items-center justify-center p-3 sm:p-5">
            <button
                type="button"
                className="cosmic-page-templates-backdrop absolute inset-0"
                onClick={onClose}
                aria-label="Close Templates"
            />

            <section className="cosmic-page-templates-panel relative z-10 flex max-h-[94vh] w-full max-w-7xl flex-col overflow-hidden rounded-3xl border">
                <header className="cosmic-page-templates-header border-b px-5 py-5 sm:px-7">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="cosmic-template-eyebrow text-[10px] font-bold uppercase tracking-[0.22em]">Cosmic Builder</p>
                            <h2 className="mt-1 text-2xl font-semibold">✦ Templates</h2>
                            <p className="cosmic-template-muted mt-1 text-sm">Install complete premium pages built from Cosmic Sparks.</p>
                        </div>

                        <div className="flex items-center gap-2">
                            <ThemeSelector
                                compact
                                value={themeValue}
                                onChange={onThemeChange}
                                themeAccess={themeAccess}
                                customTheme={customTheme}
                                hasLogo={hasLogo}
                                brandMatchNeeded={brandMatchNeeded}
                                onMatchBrandToLogo={onMatchBrandToLogo}
                                brandMatchBusy={brandMatchBusy}
                            />
                            <button type="button" onClick={onClose} className="cosmic-template-secondary h-10 rounded-xl border px-4 text-sm font-semibold">
                                Close
                            </button>
                        </div>
                    </div>

                    <div className="mt-5 flex flex-wrap items-center gap-2">
                        {['marketplace', 'owned', 'favorites'].map((value) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setTab(value)}
                                className={`cosmic-template-tab rounded-full px-4 py-2 text-xs font-bold capitalize ${tab === value ? 'is-active' : ''}`}
                            >
                                {value}
                            </button>
                        ))}

                        <input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search templates..."
                            className="cosmic-template-input ml-auto h-9 min-w-48 rounded-xl border px-3 text-xs outline-none"
                        />
                    </div>

                    <div className="mt-3 flex gap-2 overflow-x-auto pb-1">
                        {tags.map((value) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setTag(value)}
                                className={`cosmic-template-filter shrink-0 rounded-full border px-3 py-1.5 text-[11px] font-semibold ${tag === value ? 'is-active' : ''}`}
                            >
                                {value}
                            </button>
                        ))}
                    </div>
                </header>

                <div className="overflow-y-auto p-5 sm:p-7">
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {visible.map((template) => (
                            <article key={template.key} className="cosmic-template-card overflow-hidden rounded-2xl border">
                                <div
                                    role="button"
                                    tabIndex={0}
                                    onClick={() => setPreview(template)}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter' || event.key === ' ') {
                                            event.preventDefault();
                                            setPreview(template);
                                        }
                                    }}
                                    className="block w-full cursor-pointer p-3 text-left focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500"
                                >
                                    <TemplateMiniPreview template={template} websiteTheme={websiteTheme} />
                                </div>

                                <div className="p-4 pt-1">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 className="font-semibold">{template.name}</h3>
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {(template.tags || []).slice(0, 3).map((itemTag) => (
                                                    <span key={itemTag} className="cosmic-template-tag rounded-full px-2 py-0.5 text-[9px] font-bold">
                                                        {itemTag}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            disabled={busy === `fav-${template.key}`}
                                            onClick={() => favorite(template)}
                                            className={`cosmic-template-favorite h-8 w-8 rounded-lg border disabled:opacity-50 ${template.favorited ? 'is-active' : ''}`}
                                            aria-label={template.favorited ? 'Remove from Favorites' : 'Add to Favorites'}
                                        >
                                            {template.favorited ? '♥' : '♡'}
                                        </button>
                                    </div>

                                    <p className="cosmic-template-muted mt-3 min-h-10 text-xs leading-5">{template.description}</p>

                                    <div className="mt-4 flex gap-2">
                                        <button type="button" onClick={() => setPreview(template)} className="cosmic-template-secondary rounded-xl border px-4 py-2.5 text-sm font-bold">
                                            Preview
                                        </button>

                                        {template.owned ? (
                                            <button type="button" onClick={() => setSelected(template)} className="cosmic-template-primary flex-1 rounded-xl px-4 py-2.5 text-sm font-bold">
                                                Install
                                            </button>
                                        ) : (
                                            <button
                                                type="button"
                                                disabled={busy === template.key}
                                                onClick={() => unlock(template)}
                                                className="cosmic-template-accent flex-1 rounded-xl px-4 py-2.5 text-sm font-bold disabled:opacity-50"
                                            >
                                                {busy === template.key ? 'Purchasing…' : `Buy · ⚡${template.credits}`}
                                            </button>
                                        )}
                                    </div>

                                    {template.owned && <p className="cosmic-template-owned mt-2 text-right text-[10px] font-bold">✓ Owned</p>}
                                </div>
                            </article>
                        ))}
                    </div>

                    {!visible.length && (
                        <div className="cosmic-template-muted py-16 text-center text-sm">No templates match this view.</div>
                    )}
                </div>
            </section>

            {preview && (
                <div className="cosmic-template-preview fixed inset-0 z-[940]">
                    <section className="cosmic-template-preview-panel flex h-full w-full flex-col">
                        <header className="cosmic-template-preview-header sticky top-0 z-20 flex shrink-0 items-center justify-between gap-4 border-b px-4 py-3 sm:px-6">
                            <div className="min-w-0">
                                <p className="cosmic-template-eyebrow text-[10px] font-bold uppercase tracking-[0.2em]">Live theme preview</p>
                                <h3 className="truncate text-lg font-semibold sm:text-xl">{preview.name}</h3>
                                <p className="cosmic-template-muted hidden text-xs sm:block">Scroll through the complete page before installing.</p>
                            </div>

                            <div className="flex shrink-0 items-center gap-2">
                                {preview.owned ? (
                                    <button
                                        type="button"
                                        onClick={() => setSelected(preview)}
                                        className="cosmic-template-primary rounded-xl px-4 py-2.5 text-sm font-bold sm:px-5"
                                    >
                                        Install
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        disabled={busy === preview.key}
                                        onClick={() => unlock(preview)}
                                        className="cosmic-template-accent rounded-xl px-4 py-2.5 text-sm font-bold sm:px-5 disabled:opacity-50"
                                    >
                                        {busy === preview.key ? 'Purchasing…' : `Buy · ⚡${preview.credits}`}
                                    </button>
                                )}

                                <button
                                    type="button"
                                    onClick={() => setPreview(null)}
                                    className="cosmic-template-secondary rounded-xl border px-4 py-2.5 text-sm font-semibold"
                                >
                                    Close
                                </button>
                            </div>
                        </header>

                        <div className="cosmic-template-preview-scroll min-h-0 flex-1 overflow-y-auto">
                            <div className="w-full">
                                {buildBlocks(preview, true).map((block, index) => {
                                    const Component = BlockRegistry[block.type]?.component;
                                    return Component ? (
                                        <Component
                                            key={`${block.type}-${index}`}
                                            block={block}
                                            blockIndex={index}
                                            globalTheme={websiteTheme}
                                            onUpdate={() => {}}
                                            blogPosts={[]}
                                        />
                                    ) : null;
                                })}
                            </div>
                        </div>
                    </section>
                </div>
            )}

            {selected && (
                <div className="cosmic-template-install-overlay fixed inset-0 z-[960] flex items-center justify-center p-4">
                    <button
                        type="button"
                        disabled={isInstalling}
                        className="absolute inset-0 disabled:cursor-wait"
                        onClick={() => setSelected(null)}
                        aria-label="Cancel Template installation"
                    />

                    <section
                        className="cosmic-template-install-panel relative z-10 w-full max-w-lg rounded-2xl border p-6 shadow-2xl"
                        aria-busy={isInstalling}
                    >
                        <p className="cosmic-template-eyebrow text-[10px] font-bold uppercase tracking-[0.2em]">Install Template</p>
                        <h3 className="mt-1 text-xl font-semibold">{selected.name}</h3>
                        <p className="cosmic-template-muted mt-2 text-sm">
                            Choose Generic for the original premade content, or let Cosmic AI personalize the complete page for your business.
                        </p>

                        <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <button
                                type="button"
                                disabled={isInstalling}
                                onClick={() => setMode('generic')}
                                className={`cosmic-template-mode rounded-xl border p-4 text-left disabled:opacity-50 ${mode === 'generic' ? 'is-active is-generic' : ''}`}
                            >
                                <b className="block text-sm">Generic</b>
                                <span className="cosmic-template-owned mt-1 block text-xs">FREE</span>
                            </button>

                            <button
                                type="button"
                                disabled={isInstalling}
                                onClick={() => setMode('personalized')}
                                className={`cosmic-template-mode rounded-xl border p-4 text-left disabled:opacity-50 ${mode === 'personalized' ? 'is-active is-personalized' : ''}`}
                            >
                                <b className="block text-sm">Personalized</b>
                                <span className="cosmic-template-eyebrow mt-1 block text-xs">⚡ 50 Credits</span>
                            </button>
                        </div>

                        {mode === 'personalized' && (
                            <textarea
                                disabled={isInstalling}
                                value={instruction}
                                onChange={(e) => setInstruction(e.target.value.slice(0, 500))}
                                placeholder="Optional instructions for Cosmic AI..."
                                rows={4}
                                className="cosmic-template-input mt-4 w-full resize-none rounded-xl border p-3 text-sm leading-6 outline-none disabled:opacity-60"
                            />
                        )}

                        <div className="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                disabled={isInstalling}
                                onClick={() => setSelected(null)}
                                className="cosmic-template-secondary rounded-xl border px-4 py-2.5 text-sm font-semibold disabled:opacity-50"
                            >
                                Cancel
                            </button>

                            <button
                                type="button"
                                disabled={isInstalling}
                                onClick={() => setConfirmInstall(true)}
                                className="cosmic-template-primary rounded-xl px-5 py-2.5 text-sm font-bold disabled:opacity-50"
                            >
                                {isInstalling
                                    ? (mode === 'personalized' ? 'Personalizing…' : 'Installing…')
                                    : (mode === 'personalized' ? 'Personalize & Install · ⚡50' : 'Install · FREE')}
                            </button>
                        </div>
                    </section>
                </div>
            )}

            {confirmInstall && selected && !isInstalling && (
                <div className="cosmic-template-confirm-overlay fixed inset-0 z-[985] flex items-center justify-center p-4">
                    <button type="button" className="absolute inset-0" onClick={() => setConfirmInstall(false)} aria-label="Cancel replacement confirmation" />
                    <section className="cosmic-template-confirm-panel relative z-10 w-full max-w-md rounded-2xl border p-6 shadow-2xl">
                        <p className="cosmic-template-eyebrow text-[10px] font-bold uppercase tracking-[0.2em]">Replace page content?</p>
                        <h3 className="mt-2 text-xl font-semibold">{selected.name}</h3>
                        <p className="cosmic-template-muted mt-2 text-sm leading-6">
                            Installing this Template will replace the current page layout and content. Confirm before Cosmic continues.
                        </p>
                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" onClick={() => setConfirmInstall(false)} className="cosmic-template-secondary rounded-xl border px-4 py-2.5 text-sm font-semibold">Cancel</button>
                            <button type="button" onClick={() => { setConfirmInstall(false); install(); }} className="cosmic-template-primary rounded-xl px-5 py-2.5 text-sm font-bold">Confirm & Continue</button>
                        </div>
                    </section>
                </div>
            )}

            {isPersonalizing && (
                <div
                    className="cosmic-template-ai-loading fixed inset-0 z-[990] grid place-items-center px-5 text-center"
                    role="status"
                    aria-live="polite"
                    aria-busy="true"
                >
                    <div className="cosmic-template-ai-loading-card w-full max-w-xl rounded-[28px] border px-6 py-8 shadow-2xl sm:px-9 sm:py-10">
                        <div className="relative mx-auto h-16 w-16" aria-hidden="true">
                            <div className="cosmic-template-ai-orbit absolute inset-0 animate-spin rounded-full border-[3px]" />
                            <div className="cosmic-template-ai-star absolute inset-[3px] grid place-items-center rounded-full text-xl shadow-lg">✦</div>
                        </div>

                        <p className="cosmic-template-eyebrow mt-5 text-xs font-semibold uppercase tracking-[0.2em]">Cosmic AI</p>
                        <h3 className="mt-2 text-2xl font-semibold tracking-tight">Personalizing your template</h3>
                        <p className="cosmic-template-muted mt-3 text-sm">
                            Writing business-ready content and fitting it into the selected layout.
                        </p>

                        <div className="mt-7 grid grid-cols-1 gap-2 sm:grid-cols-3">
                            {['Understand business', 'Create content', 'Install page'].map((label, index) => (
                                <div key={label} className="cosmic-template-ai-step flex items-center gap-2 rounded-lg border px-3 py-2 text-left text-xs font-medium">
                                    <span className={`grid h-5 w-5 shrink-0 place-items-center rounded-full text-[10px] ${index === 0 ? 'is-active' : ''}`}>
                                        {index + 1}
                                    </span>
                                    <span>{label}</span>
                                </div>
                            ))}
                        </div>

                        <p className="cosmic-template-muted mt-5 text-xs">
                            Please keep this window open while Cosmic AI finishes.
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}
