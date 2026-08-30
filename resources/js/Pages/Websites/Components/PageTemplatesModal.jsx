import axios from 'axios';
import { memo, useDeferredValue, useEffect, useMemo, useRef, useState } from 'react';
import { showCosmicNotification } from '../../../Components/CosmicNotification';
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import { BlockRegistry } from '../BlockRegistry';
import { createSparkTailwindRuntime, hasSparkTailwindSchema } from '../Blocks/Shared/sparkTailwindRuntime';
import ThemeSelector from '../Theme/ThemeSelector';
import useInfiniteReveal from '../../../Hooks/useInfiniteReveal';
import CosmicLoadingIcon from '../../../Components/CosmicLoadingIcon';
import { colorFamilies } from '../../../theme/colorFamilies';
import { resolveSemanticPalette } from '../../../theme/semanticPalette';
import { cosmicTypographyVars } from './CosmicTypography';
import { cosmicSectionVars, cosmicLocalSectionVars } from './CosmicSection';
import { cosmicBackgroundVars, cosmicLocalBackgroundVars } from './CosmicBackground';
import { cosmicComponentVars, cosmicLocalComponentVars } from './CosmicComponentTokens';
import renderContract from '../../../../render-contract.json';

const clone = (value) => typeof structuredClone === 'function' ? structuredClone(value) : JSON.parse(JSON.stringify(value));

const previewThemeCycle = ['primary', 'white', 'surface', 'white', 'primary', 'surface'];


const previewClampNumber = (value, min, max, fallback) => {
    const number = Number(value);
    return Number.isFinite(number) ? Math.max(min, Math.min(max, number)) : fallback;
};

const resolveTemplatePreviewTheme = (block, index) => {
    const requested = String(block?.theme || '').trim().toLowerCase();
    if (requested && requested !== 'auto') return requested;

    const resolved = String(block?.resolvedTheme || '').trim().toLowerCase();
    if (resolved && resolved !== 'auto') return resolved;

    return previewThemeCycle[index % previewThemeCycle.length];
};

const templatePreviewRenderVars = (block, websiteTheme = {}, index = 0) => {
    const activeFamilyKey = String(websiteTheme?.primary || 'midnight');
    const activeFamily = activeFamilyKey === 'my-brand'
        ? (websiteTheme?.custom_brand_theme || colorFamilies.midnight)
        : (colorFamilies[activeFamilyKey] || colorFamilies.midnight);
    const semanticPalette = resolveSemanticPalette(activeFamilyKey, websiteTheme || {});
    const design = block?.luna_design_overrides || {};
    const localSection = block?.luna_section_overrides || {};
    const localBackground = block?.luna_background_overrides || {};
    const localTypography = block?.luna_typography_overrides || {};
    const localComponent = block?.luna_component_overrides || {};
    const localCardSurface = localComponent?.card_surface === 'dark' ? 'dark' : null;
    const localCardSurfaceVars = localCardSurface === 'dark' ? {
        '--cosmic-local-card-bg': 'var(--cosmic-bg-primary-surface,var(--cosmic-brand-primary,#0f172a))',
        '--cosmic-local-card-heading': 'var(--cosmic-color-on-dark,#f8fafc)',
        '--cosmic-local-card-text': 'color-mix(in srgb,var(--cosmic-color-on-dark,#f8fafc) 82%,transparent)',
        '--cosmic-local-card-border': 'color-mix(in srgb,var(--cosmic-color-on-dark,#f8fafc) 24%,transparent)',
    } : {};
    const localTypographyVars = Object.fromEntries(
        Object.entries(localTypography)
            .filter(([, value]) => value !== null && value !== undefined && value !== '')
            .map(([key, value]) => [`--cosmic-local-${String(key).replaceAll('_', '-')}`, String(value)]),
    );
    const blockType = String(block?.type || '').toLowerCase();
    const heroNeedsDefaultPadding = (index === 0 || /hero|banner/.test(blockType))
        && !/fullscreen|cinematic/.test(blockType)
        && design.section_padding_y == null;

    return {
        vars: {
            ...cosmicTypographyVars(websiteTheme?.typography || {}),
            ...cosmicSectionVars(websiteTheme?.section_layout || {}),
            ...cosmicBackgroundVars(activeFamily, websiteTheme?.background_style || {}),
            ...cosmicComponentVars(websiteTheme?.components || {}),
            ...cosmicLocalSectionVars(localSection),
            ...cosmicLocalComponentVars(localComponent),
            ...localCardSurfaceVars,
            ...cosmicLocalBackgroundVars(localBackground),
            ...localTypographyVars,
            '--cosmic-primary': semanticPalette.primary,
            '--cosmic-surface': semanticPalette.brand_surface,
            '--cosmic-accent': semanticPalette.accent,
            '--cosmic-bg-white': semanticPalette.white,
            '--cosmic-bg-surface': semanticPalette.surface,
            '--cosmic-bg-primary': semanticPalette.primary,
            '--cosmic-bg-primary-surface': semanticPalette.brand_surface,
            '--cosmic-bg-accent': semanticPalette.accent,
            '--cosmic-brand-primary': semanticPalette.primary,
            '--cosmic-brand-secondary': semanticPalette.secondary,
            '--cosmic-brand-accent': semanticPalette.accent,
            '--cosmic-color-heading': semanticPalette.heading,
            '--cosmic-color-body': semanticPalette.body,
            '--cosmic-color-muted': semanticPalette.muted,
            '--cosmic-color-border': semanticPalette.border,
            '--cosmic-color-border-strong': semanticPalette.border_strong,
            '--cosmic-color-surface': semanticPalette.surface,
            '--cosmic-color-surface-alt': semanticPalette.surface_alt,
            '--cosmic-color-page': semanticPalette.page,
            '--cosmic-color-on-primary': semanticPalette.on_primary,
            '--cosmic-color-on-secondary': semanticPalette.on_secondary,
            '--cosmic-color-on-accent': semanticPalette.on_accent,
            '--cosmic-color-on-surface': semanticPalette.on_surface,
            '--cosmic-color-on-dark': semanticPalette.on_dark,
            '--cosmic-button-primary-bg': semanticPalette.button_primary,
            '--cosmic-button-primary-text': semanticPalette.button_text,
            '--cosmic-button-secondary-bg': semanticPalette.button_secondary,
            '--cosmic-button-secondary-text': semanticPalette.button_secondary_text,
            '--cosmic-link-color': semanticPalette.primary,
            '--cosmic-link-hover': semanticPalette.primary_hover,
            '--cosmic-color-success': semanticPalette.success,
            '--cosmic-color-warning': semanticPalette.warning,
            '--cosmic-color-error': semanticPalette.error,
            '--cosmic-gradient-from': 'var(--cosmic-local-gradient-from,var(--cosmic-bg-gradient-from))',
            '--cosmic-gradient-via': 'var(--cosmic-local-gradient-via,var(--cosmic-bg-gradient-via))',
            '--cosmic-gradient-to': 'var(--cosmic-local-gradient-to,var(--cosmic-bg-gradient-to))',
            '--cosmic-gradient-glow': 'var(--cosmic-local-gradient-glow,var(--cosmic-bg-gradient-glow))',
            '--cosmic-gradient-angle': 'var(--cosmic-local-gradient-angle,var(--cosmic-bg-gradient-angle))',
            ...(design.heading_size != null ? {
                '--cosmic-local-h1-size': `${previewClampNumber(design.heading_size, 20, 112, 52)}px`,
                '--cosmic-local-h2-size': `${previewClampNumber(design.heading_size, 20, 112, 52)}px`,
                '--cosmic-local-h3-size': `${previewClampNumber(design.heading_size * .62, 16, 72, 32)}px`,
            } : {}),
            ...(design.body_size != null ? {
                '--cosmic-local-body-size': `${previewClampNumber(design.body_size, 12, 26, 16)}px`,
                '--cosmic-local-lead-size': `${previewClampNumber(design.body_size * 1.12, 13, 34, 18)}px`,
            } : {}),
            ...(design.heading_line_height != null ? {
                '--cosmic-local-h1-line': previewClampNumber(design.heading_line_height, .88, 1.6, 1.05),
                '--cosmic-local-h2-line': previewClampNumber(design.heading_line_height, .88, 1.6, 1.05),
                '--cosmic-local-h3-line': previewClampNumber(design.heading_line_height, .88, 1.6, 1.08),
            } : {}),
            ...(design.body_line_height != null ? {
                '--cosmic-local-body-line': previewClampNumber(design.body_line_height, 1.15, 2, 1.55),
                '--cosmic-local-lead-line': previewClampNumber(design.body_line_height, 1.15, 2, 1.65),
            } : {}),
            ...(design.letter_spacing != null ? {
                '--cosmic-local-h1-tracking': `${previewClampNumber(design.letter_spacing, -2, 8, 0)}px`,
                '--cosmic-local-h2-tracking': `${previewClampNumber(design.letter_spacing, -2, 8, 0)}px`,
                '--cosmic-local-h3-tracking': `${previewClampNumber(design.letter_spacing, -2, 8, 0)}px`,
            } : {}),
            ...(design.section_padding_y != null
                ? {
                    '--luna-section-py': `${previewClampNumber(design.section_padding_y, 0, 200, 72)}px`,
                    '--cosmic-local-section-py': `${previewClampNumber(design.section_padding_y, 0, 200, 72)}px`,
                }
                : heroNeedsDefaultPadding
                    ? {
                        '--luna-section-py': '100px',
                        '--luna-section-py-tablet': '76px',
                        '--luna-section-py-mobile': '56px',
                        '--cosmic-local-section-py': '100px',
                    }
                    : {}),
            ...(design.section_padding_x != null ? {
                '--luna-section-px': `${previewClampNumber(design.section_padding_x, 0, 120, 24)}px`,
                '--cosmic-local-section-px': `${previewClampNumber(design.section_padding_x, 0, 120, 24)}px`,
            } : {}),
            ...(design.content_gap != null ? {
                '--luna-content-gap': `${previewClampNumber(design.content_gap, 0, 96, 24)}px`,
                '--cosmic-local-section-gap': `${previewClampNumber(design.content_gap, 0, 96, 24)}px`,
            } : {}),
            ...((design.card_radius != null || localComponent.card_radius != null) ? {
                '--luna-card-radius': localComponent.card_radius != null
                    ? String(localComponent.card_radius)
                    : `${previewClampNumber(design.card_radius, 0, 64, 16)}px`,
                ...(localComponent.card_radius == null ? {
                    '--cosmic-local-card-radius': `${previewClampNumber(design.card_radius, 0, 64, 16)}px`,
                } : {}),
            } : {}),
            ...(design.image_radius != null ? {
                '--luna-image-radius': `${previewClampNumber(design.image_radius, 0, 64, 16)}px`,
                '--cosmic-local-image-radius': `${previewClampNumber(design.image_radius, 0, 64, 16)}px`,
            } : {}),
            ...(design.content_max_width != null ? {
                '--luna-content-max': `${previewClampNumber(design.content_max_width, 560, 1800, 1280)}px`,
                '--cosmic-local-section-container': `${previewClampNumber(design.content_max_width, 560, 1800, 1280)}px`,
            } : {}),
            ...(design.section_min_height != null ? {
                '--luna-section-min-height': `${previewClampNumber(design.section_min_height, 0, 1200, 0)}px`,
                '--cosmic-local-section-min-height': `${previewClampNumber(design.section_min_height, 0, 1200, 0)}px`,
            } : {}),
            ...(['left', 'center', 'right'].includes(design.text_align) ? { '--luna-text-align': design.text_align } : {}),
        },
        localCardSurface,
        heroNeedsDefaultPadding,
        hasLegacyDesignTypography: ['heading_size', 'body_size', 'heading_line_height', 'body_line_height', 'letter_spacing', 'text_align']
            .some((key) => design[key] !== null && design[key] !== undefined && design[key] !== ''),
    };
};

const TemplatePreviewBlock = memo(function TemplatePreviewBlock({ block, index, websiteTheme }) {
    const Component = BlockRegistry[block.type]?.component;
    if (!Component) return null;

    const resolvedTheme = resolveTemplatePreviewTheme(block, index);
    const previewBlock = { ...block, resolvedTheme };
    const { vars, localCardSurface, heroNeedsDefaultPadding, hasLegacyDesignTypography } = templatePreviewRenderVars(previewBlock, websiteTheme, index);
    const blockType = String(block?.type || '').toLowerCase();
    const hasLunaDesign = Object.keys(block?.luna_design_overrides || {}).length > 0 || heroNeedsDefaultPadding;

    return (
        <div
            data-cosmic-render-shell="1"
            data-cosmic-render-contract={renderContract.version}
            data-cosmic-spark="1"
            data-cosmic-design-system="1"
            data-cosmic-block-index={index}
            data-cosmic-block-type={block.type}
            data-cosmic-tailwind-schema={hasSparkTailwindSchema(previewBlock) ? 'schema_backed' : 'legacy_fallback'}
            data-cosmic-resolved-theme={resolvedTheme}
            data-cosmic-card-surface={localCardSurface || undefined}
            data-cosmic-background-state={String(block?.universal_background_state || resolvedTheme || 'light').toLowerCase()}
            data-cosmic-layout-mode={/fullscreen|cinematic/.test(blockType) ? 'immersive' : (/hero|banner/.test(blockType) ? 'hero' : 'standard')}
            data-luna-design={hasLunaDesign ? '1' : undefined}
            data-luna-design-typography={hasLegacyDesignTypography ? '1' : undefined}
            className={`cosmic-render-shell ${hasLunaDesign ? 'cosmic-luna-design-host' : ''}`}
            style={vars}
        >
            <div className="cosmic-render-content">
                <Component
                    block={previewBlock}
                    blockIndex={index}
                    globalTheme={websiteTheme}
                    tailwind={createSparkTailwindRuntime(previewBlock)}
                    onUpdate={() => {}}
                    blogPosts={[]}
                />
            </div>
        </div>
    );
});

function buildBlocks(template, previewMode = false) {
    if (template?.saved && Array.isArray(template.blocks) && template.blocks.length) {
        return clone(template.blocks).filter((block) => block?.type && BlockRegistry[block.type]);
    }

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

const TemplateMiniPreview = memo(function TemplateMiniPreview({ template, websiteTheme }) {
    // Keep marketplace cards lightweight: the full six-section composition still renders in the dedicated Preview.
    const blocks = useMemo(() => buildBlocks(template, true).slice(0, 3), [template]);

    return (
        <div
            className="cosmic-preview-isolation h-52 overflow-hidden rounded-xl bg-white"
            data-cosmic-preview-isolation="true"
        >
            <div className="origin-top-left w-[400%]" style={{ transform: 'scale(.25)' }}>
                {blocks.map((block, index) => (
                    <TemplatePreviewBlock
                        key={`${block.type}-${index}`}
                        block={block}
                        index={index}
                        websiteTheme={websiteTheme}
                    />
                ))}
            </div>
        </div>
    );
});

export default function PageTemplatesModal({
    open,
    onClose,
    onInstall,
    websiteContext = '',
    websiteId = null,
    headerOverlayEnabled = false,
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
    preloadedCatalog = [],
    preloadedCatalogLoading = false,
    preloadedCatalogLoaded = false,
    preparedVisibleCount = 0,
}) {
    const { setBalance } = useCreditBalance();
    const [templates, setTemplates] = useState(() => preloadedCatalogLoaded ? preloadedCatalog : []);
    const templatesScrollRef = useRef(null);
    const [popupActive, setPopupActive] = useState(false);
    const [tab, setTab] = useState('marketplace');
    const [query, setQuery] = useState('');
    const deferredQuery = useDeferredValue(query);
    const [aiSearchBusy, setAiSearchBusy] = useState(false);
    const [aiResults, setAiResults] = useState(null);
    const [aiPrompt, setAiPrompt] = useState('');
    const [tag, setTag] = useState('All');
    const [busy, setBusy] = useState(null);
    const [selected, setSelected] = useState(null);
    const [preview, setPreview] = useState(null);
    const [mode, setMode] = useState('generic');
    const [instruction, setInstruction] = useState('');
    const [confirmInstall, setConfirmInstall] = useState(false);
    const [renameTarget, setRenameTarget] = useState(null);
    const [renameName, setRenameName] = useState('');
    const [renameDescription, setRenameDescription] = useState('');
    const [deleteTarget, setDeleteTarget] = useState(null);
    const trialSignupUrl = trialToken ? `/register?trial=${encodeURIComponent(trialToken)}` : '/register';

    useEffect(() => {
        if (!preloadedCatalogLoaded) return;
        setTemplates(preloadedCatalog || []);
    }, [preloadedCatalog, preloadedCatalogLoaded]);

    useEffect(() => {
        // Builder preloads this catalog on landing. Only fall back to a modal-time
        // request when that preload was unavailable, preventing repeat fetches on reopen.
        if (!open || preloadedCatalogLoaded || preloadedCatalogLoading || templates.length > 0) return;

        axios
            .get(trialMode && trialToken ? `/trial-assets/${trialToken}/templates` : '/page-templates/catalog')
            .then(({ data }) => setTemplates(data.templates || []))
            .catch(() => showCosmicNotification({
                title: 'Could not load Templates',
                message: 'Please refresh and try again.',
                tone: 'error',
            }));
    }, [open, preloadedCatalogLoaded, preloadedCatalogLoading, templates.length, trialMode, trialToken]);

    const tags = useMemo(
        () => ['All', ...new Set(templates.flatMap((item) => item.tags || []))],
        [templates],
    );

    const tabCounts = useMemo(() => ({
        marketplace: templates.filter((item) => !item.saved).length,
        purchased: templates.filter((item) => item.purchased && !item.saved).length,
        saved: templates.filter((item) => item.saved).length,
        favorites: templates.filter((item) => item.favorited && !item.saved).length,
    }), [templates]);

    const normalizedQuery = deferredQuery.trim().toLowerCase();
    const aiResultMap = useMemo(() => new Map((aiResults || []).map((result, index) => [result.id, { ...result, rank: index }])), [aiResults]);
    const filteredTemplates = useMemo(() => {
        const filtered = templates.filter((item) => {
            if (tab === 'marketplace' && item.saved) return false;
            if (tab === 'purchased' && !item.purchased) return false;
            if (tab === 'saved' && !item.saved) return false;
            if (tab === 'favorites' && !item.favorited) return false;
            if (tag !== 'All' && !(item.tags || []).includes(tag)) return false;

            if (aiResults) return aiResultMap.has(item.key);
            if (!normalizedQuery) return true;

            return `${item.name} ${item.description} ${(item.tags || []).join(' ')} ${(item.aliases || []).join(' ')} ${(item.industry || []).join(' ')}`
                .toLowerCase()
                .includes(normalizedQuery);
        });

        if (aiResults) {
            filtered.sort((a, b) => (aiResultMap.get(a.key)?.rank ?? 999) - (aiResultMap.get(b.key)?.rank ?? 999));
        }

        return filtered;
    }, [templates, tab, tag, normalizedQuery, aiResults, aiResultMap]);

    const templateRevealKey = `${tab}|${tag}|${normalizedQuery}|${aiResults ? 'ai' : 'browse'}|${filteredTemplates.length}`;
    const {
        visibleItems: visible,
        sentinelRef: templateSentinelRef,
        hasMore: hasMoreTemplates,
        isRevealing: isRevealingTemplates,
    } = useInfiniteReveal(filteredTemplates, {
        batchSize: 50,
        resetKey: templateRevealKey,
        root: templatesScrollRef,
        rootMargin: '420px 0px',
        disabled: !open || !popupActive || preloadedCatalogLoading,
    });

    const runAiSearch = async () => {
        const prompt = query.trim();
        if (aiSearchBusy || prompt.length < 2 || trialMode || tab !== 'marketplace') return;

        setAiSearchBusy(true);
        try {
            const { data } = await axios.post('/ai/library-search', {
                type: 'templates',
                prompt,
                limit: 8,
            });
            const results = Array.isArray(data?.results) ? data.results : [];
            setAiResults(results);
            setAiPrompt(prompt);
            setTag('All');
            if (!results.length) {
                showCosmicNotification({
                    title: 'No AI matches yet',
                    message: 'Try describing the industry, style, audience, or features you want.',
                    tone: 'warning',
                });
            }
        } catch (error) {
            showCosmicNotification({
                title: 'AI Template Search unavailable',
                message: error.response?.data?.message || 'Normal template search is still available.',
                tone: 'error',
            });
        } finally {
            setAiSearchBusy(false);
        }
    };

    const clearAiSearch = () => {
        setAiResults(null);
        setAiPrompt('');
    };

    const isInstalling = Boolean(selected && busy === `install-${selected.key}`);
    const isPersonalizing = Boolean(isInstalling && mode === 'personalized');

    if (!open) return null;

    const unlock = async (template) => {
        // One marketplace mutation at a time. This prevents accidental multi-purchases
        // from fast taps while the first credit transaction is still in flight.
        if (busy || template?.owned || template?.saved) return;
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
            showCosmicNotification({ title: 'Template purchased', message: data.message, tone: 'success' });
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

    const duplicateSaved = async (template) => {
        if (!template?.saved_template_id || busy) return;
        setBusy(`duplicate-${template.key}`);

        try {
            const { data } = await axios.post(`/page-templates/saved/${template.saved_template_id}/duplicate`);
            setTemplates((items) => [data.template, ...items]);
            setTab('saved');
            showCosmicNotification({ title: 'Template duplicated', message: data.message, tone: 'success' });
        } catch (error) {
            showCosmicNotification({
                title: 'Could not duplicate Template',
                message: error.response?.data?.message || 'Please try again.',
                tone: 'error',
            });
        } finally {
            setBusy(null);
        }
    };

    const openRename = (template) => {
        setRenameTarget(template);
        setRenameName(template.name || '');
        setRenameDescription(template.description || '');
    };

    const renameSaved = async () => {
        if (!renameTarget?.saved_template_id || !renameName.trim() || busy) return;
        setBusy(`rename-${renameTarget.key}`);

        try {
            const { data } = await axios.patch(`/page-templates/saved/${renameTarget.saved_template_id}`, {
                name: renameName.trim(),
                description: renameDescription.trim(),
            });
            setTemplates((items) => items.map((item) => item.key === renameTarget.key ? data.template : item));
            if (preview?.key === renameTarget.key) setPreview(data.template);
            showCosmicNotification({ title: 'Template updated', message: data.message, tone: 'success' });
            setRenameTarget(null);
        } catch (error) {
            showCosmicNotification({
                title: 'Could not update Template',
                message: error.response?.data?.message || 'Please try again.',
                tone: 'error',
            });
        } finally {
            setBusy(null);
        }
    };

    const deleteSaved = async () => {
        if (!deleteTarget?.saved_template_id || busy) return;
        setBusy(`delete-${deleteTarget.key}`);

        try {
            const { data } = await axios.delete(`/page-templates/saved/${deleteTarget.saved_template_id}`);
            setTemplates((items) => items.filter((item) => item.key !== deleteTarget.key));
            if (preview?.key === deleteTarget.key) setPreview(null);
            showCosmicNotification({ title: 'Template removed', message: data.message, tone: 'success' });
            setDeleteTarget(null);
        } catch (error) {
            showCosmicNotification({
                title: 'Could not remove Template',
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
                        'AI personalization is available after sign up. Install Generic now and your purchased Template will transfer to your account.',
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
                    header_overlay_enabled: Boolean(headerOverlayEnabled),
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

            <section
                onPointerEnter={() => setPopupActive(true)}
                onPointerLeave={() => setPopupActive(false)}
                className={`cosmic-page-templates-panel relative z-10 flex max-h-[94vh] w-full max-w-7xl flex-col overflow-hidden rounded-3xl border ${popupActive ? 'is-active' : ''}`}
            >
                <header className="cosmic-page-templates-header border-b px-5 py-5 sm:px-7">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="cosmic-template-eyebrow text-[10px] font-bold uppercase tracking-[0.22em]">Cosmic Builder</p>
                            <h2 className="mt-1 text-2xl font-semibold">✦ Templates</h2>
                            <p className="cosmic-template-muted mt-1 text-sm">{trialMode ? '10 Templates are ready to use in your trial. Keep scrolling to preview the full library; sign up to unlock the rest.' : 'Install complete premium pages built from Cosmic Sparks.'}</p>
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

                    {trialMode && (
                        <div className="mt-4 flex flex-col gap-2 rounded-2xl border border-violet-400/20 bg-violet-400/[0.07] px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <div><b className="text-violet-700 dark:text-violet-200">Trial access: 10 Templates available</b><p className="cosmic-template-muted mt-0.5 text-xs">Preview everything. Create a free account to unlock the full Template library.</p></div>
                            <a href={trialSignupUrl} className="cosmic-template-accent shrink-0 rounded-xl px-4 py-2 text-center text-xs font-bold">Sign up to unlock</a>
                        </div>
                    )}

                    <div className="mt-5 flex flex-wrap items-center gap-2">
                        <div className="cosmic-template-tabs flex max-w-full gap-1 overflow-x-auto rounded-xl border p-1">
                            {(trialMode ? ['marketplace'] : ['marketplace', 'purchased', 'saved', 'favorites']).map((value) => {
                                const label = trialMode && value === 'marketplace'
                                    ? 'Trial Templates'
                                    : value === 'saved'
                                        ? 'Saved Templates'
                                        : value.charAt(0).toUpperCase() + value.slice(1);

                                return (
                                    <button
                                        key={value}
                                        type="button"
                                        onClick={() => { setTab(value); clearAiSearch(); }}
                                        className={`cosmic-template-tab whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold ${tab === value ? 'is-active' : ''}`}
                                    >
                                        {label} ({tabCounts[value] ?? 0})
                                    </button>
                                );
                            })}
                        </div>

                        <div className="ml-auto flex min-w-0 items-center gap-2">
                            <input
                                value={query}
                                onChange={(e) => {
                                    setQuery(e.target.value);
                                    if (aiResults) clearAiSearch();
                                }}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter' && !trialMode && tab === 'marketplace') {
                                        event.preventDefault();
                                        runAiSearch();
                                    }
                                }}
                                placeholder="Search, or describe the website you need..."
                                className="cosmic-template-input h-9 min-w-52 sm:min-w-80 rounded-xl border px-3 text-xs outline-none"
                            />
                            {!trialMode && tab === 'marketplace' && (
                                <button
                                    type="button"
                                    disabled={aiSearchBusy || query.trim().length < 2}
                                    onClick={runAiSearch}
                                    className="cosmic-template-accent inline-flex h-9 shrink-0 items-center gap-1.5 rounded-xl px-3 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-50"
                                    title="Let Luna rank the best matching Templates"
                                >
                                    {aiSearchBusy ? <><CosmicLoadingIcon className="h-3.5 w-3.5"/>Searching…</> : <><span aria-hidden="true">✦</span>Ask Luna</>}
                                </button>
                            )}
                        </div>
                    </div>

                    {aiResults && (
                        <div className="cosmic-template-muted mt-3 flex flex-wrap items-center gap-2 text-xs">
                            <span><b>✦ Luna results</b> for “{aiPrompt}” · {visible.length} match{visible.length === 1 ? '' : 'es'}</span>
                            <button type="button" onClick={clearAiSearch} className="cosmic-template-secondary rounded-full border px-2.5 py-1 text-[11px] font-semibold">Clear AI results</button>
                        </div>
                    )}

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

                <div ref={templatesScrollRef} className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5 sm:p-7">
                    {(preloadedCatalogLoading && templates.length === 0) ? (
                        <div className="flex min-h-[420px] flex-col items-center justify-center rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/40 px-6 text-center">
                            <div className="h-10 w-10 animate-spin rounded-full border-4 border-emerald-200 border-t-emerald-600" />
                            <h3 className="mt-4 text-base font-semibold text-slate-800">Loading Templates…</h3>
                            <p className="mt-1 text-sm text-slate-500">Preparing Templates in the background.</p>
                            <div className="mt-6 grid w-full max-w-3xl gap-3 sm:grid-cols-3">
                                {Array.from({ length: 6 }).map((_, index) => <div key={index} className="h-28 animate-pulse rounded-xl bg-slate-200/70" />)}
                            </div>
                        </div>
                    ) : (
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {visible.map((template) => (
                            <article key={template.key} style={{ contentVisibility: 'auto', containIntrinsicSize: '520px' }} className="cosmic-template-card relative overflow-hidden rounded-2xl border">
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
                                    className={`block w-full cursor-pointer p-3 text-left focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500 ${template.trial_locked ? 'blur-[3px] saturate-50 opacity-60' : ''}`}
                                >
                                    <TemplateMiniPreview template={template} websiteTheme={websiteTheme} />
                                </div>

                                {template.trial_locked && (
                                    <div className="pointer-events-none absolute left-1/2 top-20 z-10 -translate-x-1/2 rounded-full border border-white/15 bg-slate-950/85 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.14em] text-white shadow-xl backdrop-blur">🔒 Sign up to unlock</div>
                                )}
                                <div className="p-4 pt-1">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="font-semibold">{template.name}</h3>
                                                {!template.saved && Number(template.credits || 0) >= 200 && (
                                                    <span className="rounded-full border border-amber-300/40 bg-amber-400/10 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] text-amber-600 dark:text-amber-300">Premium · ⚡{template.credits}</span>
                                                )}
                                            </div>
                                            {aiResults && aiResultMap.get(template.key)?.reason && (
                                                <p className="mt-1 text-[11px] leading-4 text-emerald-600 dark:text-emerald-300">✦ {aiResultMap.get(template.key).reason}</p>
                                            )}
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {(template.tags || []).slice(0, 3).map((itemTag) => (
                                                    <span key={itemTag} className="cosmic-template-tag rounded-full px-2 py-0.5 text-[9px] font-bold">
                                                        {itemTag}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>

                                        {!trialMode && !template.saved && (
                                            <button
                                                type="button"
                                                disabled={busy === `fav-${template.key}`}
                                                onClick={() => favorite(template)}
                                                className={`cosmic-template-favorite h-8 w-8 rounded-lg border disabled:opacity-50 ${template.favorited ? 'is-active' : ''}`}
                                                aria-label={template.favorited ? 'Remove from Favorites' : 'Add to Favorites'}
                                            >
                                                {template.favorited ? '♥' : '♡'}
                                            </button>
                                        )}
                                    </div>

                                    <p className={`cosmic-template-muted mt-3 min-h-10 text-xs leading-5 ${template.trial_locked ? 'blur-[2px] select-none opacity-55' : ''}`}>{template.description}</p>

                                    <div className="mt-4 flex gap-2">
                                        <button type="button" onClick={() => setPreview(template)} className="cosmic-template-secondary rounded-xl border px-4 py-2.5 text-sm font-bold">
                                            Preview
                                        </button>

                                        {template.trial_locked ? (
                                            <a href={trialSignupUrl} className="cosmic-template-accent flex-1 rounded-xl px-4 py-2.5 text-center text-sm font-bold">
                                                Sign up to unlock
                                            </a>
                                        ) : template.owned ? (
                                            <button type="button" onClick={() => setSelected(template)} className="cosmic-template-primary flex-1 rounded-xl px-4 py-2.5 text-sm font-bold">
                                                {template.saved ? 'Use Template' : 'Install'}
                                            </button>
                                        ) : (
                                            <button
                                                type="button"
                                                disabled={Boolean(busy)}
                                                onClick={() => unlock(template)}
                                                className="cosmic-template-accent flex-1 rounded-xl px-4 py-2.5 text-sm font-bold disabled:cursor-wait disabled:opacity-50"
                                            >
                                                {busy === template.key ? 'Purchasing…' : `Buy · ⚡${template.credits}`}
                                            </button>
                                        )}
                                    </div>

                                    {template.saved && (
                                        <div className="mt-2 grid grid-cols-3 gap-2">
                                            <button type="button" disabled={Boolean(busy)} onClick={() => duplicateSaved(template)} className="cosmic-template-secondary rounded-lg border px-2 py-2 text-[11px] font-semibold disabled:opacity-50">Duplicate</button>
                                            <button type="button" disabled={Boolean(busy)} onClick={() => openRename(template)} className="cosmic-template-secondary rounded-lg border px-2 py-2 text-[11px] font-semibold disabled:opacity-50">Rename</button>
                                            <button type="button" disabled={Boolean(busy)} onClick={() => setDeleteTarget(template)} className="cosmic-template-danger rounded-lg border px-2 py-2 text-[11px] font-semibold disabled:opacity-50">Delete</button>
                                        </div>
                                    )}
                                    {template.purchased && <p className="cosmic-template-owned mt-2 text-right text-[10px] font-bold">✓ Purchased</p>}
                                    {template.saved && <p className="cosmic-template-saved mt-2 text-right text-[10px] font-bold">✓ Saved Template · PAGE</p>}
                                </div>
                            </article>
                        ))}
                        {hasMoreTemplates && <div ref={templateSentinelRef} data-cosmic-infinite-sentinel="templates" className="col-span-full h-px w-full" aria-hidden="true" />}
                        {isRevealingTemplates && <div className="col-span-full py-3 text-center text-xs text-slate-500">Loading more Templates…</div>}
                    </div>
                    )}

                    {!preloadedCatalogLoading && !visible.length && (
                        <div className="cosmic-template-muted py-16 text-center text-sm">{tab === 'saved' ? 'No saved templates yet. Save a page from the Builder and it will appear here.' : 'No templates match this view.'}</div>
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
                                {preview.trial_locked ? (
                                    <a href={trialSignupUrl} className="cosmic-template-accent rounded-xl px-4 py-2.5 text-sm font-bold sm:px-5">Sign up to unlock</a>
                                ) : preview.owned ? (
                                    <button
                                        type="button"
                                        onClick={() => setSelected(preview)}
                                        className="cosmic-template-primary rounded-xl px-4 py-2.5 text-sm font-bold sm:px-5"
                                    >
                                        {preview.saved ? 'Use Template' : 'Install'}
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        disabled={Boolean(busy)}
                                        onClick={() => unlock(preview)}
                                        className="cosmic-template-accent rounded-xl px-4 py-2.5 text-sm font-bold sm:px-5 disabled:cursor-wait disabled:opacity-50"
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
                            <div
                                className="cosmic-template-readonly-preview cosmic-preview-isolation w-full"
                                data-cosmic-preview-isolation="true"
                                data-cosmic-readonly-preview="true"
                                onClickCapture={(event) => {
                                    const target = event.target;
                                    if (!(target instanceof Element)) return;
                                    // Keep the showroom read-only. Links/forms/Builder edit affordances are
                                    // inert, while dedicated slider/accordion buttons can still demonstrate motion.
                                    if (target.closest('a[href], form, input, textarea, select, [contenteditable="true"]')) {
                                        event.preventDefault();
                                        event.stopPropagation();
                                        return;
                                    }
                                    if (target.closest('[data-cosmic-edit-control], [data-editable-media]')) {
                                        event.preventDefault();
                                        event.stopPropagation();
                                        return;
                                    }
                                    const editableShell = target.closest('.cursor-pointer');
                                    if (editableShell && !target.closest('button, a, input, textarea, select, [role="button"]')) {
                                        event.preventDefault();
                                        event.stopPropagation();
                                    }
                                }}
                                onDoubleClickCapture={(event) => { event.preventDefault(); event.stopPropagation(); }}
                            >
                                {buildBlocks(preview, true).map((block, index) => (
                                    <TemplatePreviewBlock
                                        key={`${block.type}-${index}`}
                                        block={block}
                                        index={index}
                                        websiteTheme={websiteTheme}
                                    />
                                ))}
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
                        <p className="cosmic-template-eyebrow text-[10px] font-bold uppercase tracking-[0.2em]">{selected.saved ? 'Use Saved Template' : 'Install Template'}</p>
                        <h3 className="mt-1 text-xl font-semibold">{selected.name}</h3>
                        <p className="cosmic-template-muted mt-2 text-sm">
                            {selected.saved ? 'Apply this saved design exactly as it was stored, or optionally let Cosmic AI rewrite its content while preserving the section structure.' : 'Choose Generic for the original premade content, or let Cosmic AI personalize the complete page for your business.'}
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

            {renameTarget && (
                <div className="cosmic-template-confirm-overlay fixed inset-0 z-[982] flex items-center justify-center p-4">
                    <button type="button" className="absolute inset-0" onClick={() => !busy && setRenameTarget(null)} aria-label="Close rename Template" />
                    <section className="cosmic-template-confirm-panel relative z-10 w-full max-w-md rounded-2xl border p-6 shadow-2xl">
                        <p className="cosmic-template-eyebrow text-[10px] font-bold uppercase tracking-[0.2em]">Saved Template</p>
                        <h3 className="mt-2 text-xl font-semibold">Rename Template</h3>
                        <label className="cosmic-template-muted mt-5 block text-xs font-semibold">Template name</label>
                        <input value={renameName} onChange={(e) => setRenameName(e.target.value.slice(0, 140))} className="cosmic-template-input mt-2 w-full rounded-xl border px-3 py-2.5 text-sm outline-none" autoFocus />
                        <label className="cosmic-template-muted mt-4 block text-xs font-semibold">Description</label>
                        <textarea value={renameDescription} onChange={(e) => setRenameDescription(e.target.value.slice(0, 500))} rows={3} className="cosmic-template-input mt-2 w-full resize-none rounded-xl border p-3 text-sm outline-none" />
                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" disabled={Boolean(busy)} onClick={() => setRenameTarget(null)} className="cosmic-template-secondary rounded-xl border px-4 py-2.5 text-sm font-semibold disabled:opacity-50">Cancel</button>
                            <button type="button" disabled={Boolean(busy) || !renameName.trim()} onClick={renameSaved} className="cosmic-template-primary rounded-xl px-5 py-2.5 text-sm font-bold disabled:opacity-50">{busy ? 'Saving…' : 'Save Changes'}</button>
                        </div>
                    </section>
                </div>
            )}

            {deleteTarget && (
                <div className="cosmic-template-confirm-overlay fixed inset-0 z-[984] flex items-center justify-center p-4">
                    <button type="button" className="absolute inset-0" onClick={() => !busy && setDeleteTarget(null)} aria-label="Cancel Template deletion" />
                    <section className="cosmic-template-confirm-panel relative z-10 w-full max-w-md rounded-2xl border p-6 shadow-2xl">
                        <p className="cosmic-template-eyebrow text-[10px] font-bold uppercase tracking-[0.2em]">Remove Saved Template?</p>
                        <h3 className="mt-2 text-xl font-semibold">{deleteTarget.name}</h3>
                        <p className="cosmic-template-muted mt-2 text-sm leading-6">This removes the template from your Saved Templates library. Pages that already used it are not changed.</p>
                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" disabled={Boolean(busy)} onClick={() => setDeleteTarget(null)} className="cosmic-template-secondary rounded-xl border px-4 py-2.5 text-sm font-semibold disabled:opacity-50">Cancel</button>
                            <button type="button" disabled={Boolean(busy)} onClick={deleteSaved} className="cosmic-template-danger rounded-xl border px-5 py-2.5 text-sm font-bold disabled:opacity-50">{busy ? 'Removing…' : 'Delete Template'}</button>
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
