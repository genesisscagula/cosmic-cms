import { memo, useMemo, useState } from 'react';
import { BlockRegistry } from '../Websites/BlockRegistry';
import { AuthoredTypographyContext } from '../Websites/Blocks/Shared/EditableText';
import { createSparkTailwindRuntime, hasSparkTailwindSchema } from '../Websites/Blocks/Shared/sparkTailwindRuntime';
import { colorFamilies } from '../../theme/colorFamilies';
import { resolveSemanticPalette } from '../../theme/semanticPalette';
import { legoSurfaceVars as resolveLegoSurfaceVars, resolveLegoSectionSurface } from '../../theme/legoSurface';
import { cosmicTypographyVars } from '../Websites/Components/CosmicTypography';
import { cosmicSectionVars, cosmicLocalSectionVars } from '../Websites/Components/CosmicSection';
import { cosmicBackgroundVars, cosmicLocalBackgroundVars } from '../Websites/Components/CosmicBackground';
import { cosmicComponentVars, cosmicLocalComponentVars } from '../Websites/Components/CosmicComponentTokens';
import renderContract from '../../../render-contract.json';

const previewThemeCycle = ['primary', 'white', 'surface', 'white', 'primary', 'surface'];

const marketplaceDesignPalette = (websiteTheme = {}) => {
    const design = websiteTheme?.marketplace_design;
    if (!design || typeof design !== 'object') return null;
    const darkHeader = design.header_mode === 'dark';
    return {
        primary: design.primary || '#0f172a',
        primary_hover: design.primary_hover || design.primary || '#0f172a',
        secondary: design.surface_alt || design.surface || '#ffffff',
        accent: design.accent || design.primary || '#0f172a',
        page: design.page || '#ffffff',
        dark: design.dark || design.primary || '#0f172a',
        heading: design.heading || '#0f172a',
        body: design.body || '#475569',
        muted: design.body || '#64748b',
        border: design.border || '#e2e8f0',
        border_strong: design.border || '#cbd5e1',
        surface: design.surface || '#ffffff',
        surface_alt: design.surface_alt || design.page || '#f8fafc',
        brand_surface: design.surface_alt || design.surface || '#ffffff',
        on_primary: design.on_primary || '#ffffff',
        on_secondary: design.heading || '#0f172a',
        on_accent: design.button_text || '#ffffff',
        on_surface: design.heading || '#0f172a',
        on_dark: design.on_primary || '#ffffff',
        button_primary: design.button_primary || design.primary || '#0f172a',
        button_text: design.button_text || '#ffffff',
        button_secondary: design.surface || '#ffffff',
        button_secondary_text: design.heading || '#0f172a',
        success: '#15803d', warning: '#b45309', error: '#b91c1c',
        header_mode: darkHeader ? 'dark' : 'light',
        header_bg: darkHeader ? (design.primary || '#17110e') : (design.surface || '#ffffff'),
        header_text: darkHeader ? (design.on_primary || '#ffffff') : (design.heading || '#0f172a'),
        font_heading: design.font_heading || null,
        font_body: design.font_body || null,
    };
};


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
    const fixedPalette = marketplaceDesignPalette(websiteTheme);
    if (fixedPalette && String(block?.type || '').startsWith('marketplace_')) {
        return {
            vars: {
                ...cosmicTypographyVars(websiteTheme?.typography || {}),
                ...cosmicSectionVars(websiteTheme?.section_layout || {}),
                ...cosmicComponentVars(websiteTheme?.marketplace_design?.components || websiteTheme?.components || {}),
                '--cosmic-primary': fixedPalette.primary,
                '--cosmic-accent': fixedPalette.accent,
                '--cosmic-color-page': fixedPalette.page,
                '--cosmic-color-heading': fixedPalette.heading,
                '--cosmic-color-body': fixedPalette.body,
                '--cosmic-color-muted': fixedPalette.muted,
                '--cosmic-color-border': fixedPalette.border,
                '--cosmic-button-primary-bg': fixedPalette.button_primary,
                '--cosmic-button-primary-text': fixedPalette.button_text,
                ...(fixedPalette.font_heading ? { '--cosmic-font-display': fixedPalette.font_heading } : {}),
                ...(fixedPalette.font_body ? { '--cosmic-font-body': fixedPalette.font_body } : {}),
            },
            localCardSurface: null,
            heroNeedsDefaultPadding: false,
            hasLegacyDesignTypography: false,
        };
    }

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
            ...cosmicComponentVars(websiteTheme?.marketplace_design?.components || websiteTheme?.components || {}),
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
            '--cosmic-bg-surface-alt': semanticPalette.surface_alt,
            '--cosmic-bg-primary': semanticPalette.primary,
            '--cosmic-bg-primary-surface': semanticPalette.brand_surface,
            '--cosmic-bg-accent': semanticPalette.accent,
            '--cosmic-brand-primary': semanticPalette.primary,
            '--cosmic-brand-secondary': semanticPalette.secondary,
            '--cosmic-brand-accent': semanticPalette.accent,
            '--cosmic-brand-surface': semanticPalette.brand_surface,
            '--cosmic-color-dark': semanticPalette.dark,
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

const MarketplaceTemplatePreviewBlock = memo(function MarketplaceTemplatePreviewBlock({ block, index, websiteTheme }) {
    const registryEntry = BlockRegistry[block.type];
    const Component = registryEntry?.component;
    if (!Component) return null;

    // Marketplace DB blocks intentionally store customer-facing content/settings,
    // while the Spark source remains the schema authority. Hydrate defaults here so
    // every public demo receives the complete runtime shape expected by the Spark.
    const defaults = registryEntry?.schema?.defaults || {};
    const commonHeading = block?.heading || block?.title || block?.headline || defaults.heading || defaults.title || defaults.headline;
    const commonText = block?.text || block?.description || block?.subtext || block?.copy || defaults.text || defaults.description || defaults.subtext || defaults.copy;
    const hydratedBlock = {
        ...defaults,
        ...block,
        ...(commonHeading ? {
            heading: block?.heading || commonHeading,
            title: block?.title || commonHeading,
            headline: block?.headline || commonHeading,
        } : {}),
        ...(commonText ? {
            text: block?.text || commonText,
            description: block?.description || commonText,
            subtext: block?.subtext || commonText,
            copy: block?.copy || commonText,
        } : {}),
        ...(!Array.isArray(block?.slides) && Array.isArray(defaults?.slides) && defaults.slides.length > 0 ? {
            slides: defaults.slides.map((slide, slideIndex) => slideIndex === 0 ? {
                ...slide,
                ...(commonHeading ? { heading: commonHeading, title: commonHeading, headline: commonHeading } : {}),
                ...(commonText ? { text: commonText, description: commonText, copy: commonText } : {}),
            } : slide),
        } : {}),
    };
    const resolvedTheme = resolveTemplatePreviewTheme(hydratedBlock, index);
    const configuredLegoSurface = ['auto','white','slate','primary'].includes(hydratedBlock?.section_surface) ? hydratedBlock.section_surface : 'auto';
    const resolvedLegoSurface = resolveLegoSectionSurface(configuredLegoSurface, resolvedTheme);
    const previewBlock = { ...hydratedBlock, resolvedTheme, resolvedLegoSurface };
    const { vars, localCardSurface, heroNeedsDefaultPadding, hasLegacyDesignTypography } = templatePreviewRenderVars(previewBlock, websiteTheme, index);
    const isLegoBuild = previewBlock?.ai_flex?.source === 'build_your_own';
    const activeFamilyKey = String(websiteTheme?.primary || 'midnight');
    const legoPalette = resolveSemanticPalette(activeFamilyKey, websiteTheme || {});
    const legoVars = isLegoBuild ? resolveLegoSurfaceVars(resolvedLegoSurface, legoPalette) : {};
    const blockType = String(block?.type || '').toLowerCase();
    const isEmberMarketplace = blockType.startsWith('marketplace_ember_');
    const isHarborMarketplace = blockType.startsWith('marketplace_harbor_');
    const isLedgerMarketplace = blockType.startsWith('marketplace_ledger_');
    const marketplaceFamily = isEmberMarketplace ? 'ember' : (isLedgerMarketplace ? 'ledger' : undefined);
    const emberPaletteVars = isEmberMarketplace ? {
        '--cosmic-primary': '#1a1a16',
        '--cosmic-accent': '#d6a151',
        '--cosmic-bg-primary': '#1a1a16',
        '--cosmic-local-bg-primary': '#1a1a16',
        '--cosmic-bg-primary-surface': '#25251a',
        '--cosmic-local-bg-primary-surface': '#25251a',
        '--cosmic-bg-surface': '#f5f0e7',
        '--cosmic-local-bg-surface': '#f5f0e7',
        '--cosmic-bg-white': '#fffaf3',
        '--cosmic-local-bg-white': '#fffaf3',
        '--cosmic-on-primary': '#f5eddf',
        '--cosmic-on-primary-muted': 'rgba(245,237,223,.66)',
        '--cosmic-on-surface': '#2c211a',
        '--cosmic-on-surface-muted': '#65594f',
        '--cosmic-color-on-dark': '#f5eddf',
        '--cosmic-color-on-primary': '#f5eddf',
        '--cosmic-button-primary-bg': '#d6a151',
        '--cosmic-button-primary-text': '#17150e',
    } : {};
    const ledgerPaletteVars = isLedgerMarketplace ? {
        '--cosmic-primary': '#0c6670',
        '--cosmic-accent': '#d3b166',
        '--cosmic-bg-primary': '#09242d',
        '--cosmic-local-bg-primary': '#09242d',
        '--cosmic-bg-primary-surface': '#10343d',
        '--cosmic-local-bg-primary-surface': '#10343d',
        '--cosmic-bg-surface': '#f7f6f1',
        '--cosmic-local-bg-surface': '#f7f6f1',
        '--cosmic-bg-white': '#ffffff',
        '--cosmic-local-bg-white': '#ffffff',
        '--cosmic-on-primary': '#ffffff',
        '--cosmic-on-primary-muted': 'rgba(255,255,255,.66)',
        '--cosmic-on-surface': '#102b33',
        '--cosmic-on-surface-muted': '#60747a',
        '--cosmic-color-on-dark': '#ffffff',
        '--cosmic-color-on-primary': '#ffffff',
        '--cosmic-button-primary-bg': '#0c6670',
        '--cosmic-button-primary-text': '#ffffff',
    } : {};
    const hasLunaDesign = Object.keys(previewBlock?.luna_design_overrides || {}).length > 0 || heroNeedsDefaultPadding;

    // Harbor owns its section surfaces, type scale and card composition. Generic
    // Spark contracts would override these with the rotating preview theme.
    if (isHarborMarketplace) {
        return (
            <div data-cosmic-marketplace-family="harbor" data-cosmic-block-type={previewBlock.type} style={vars}>
                <AuthoredTypographyContext.Provider value={true}>
                    <Component block={previewBlock} blockIndex={index} globalTheme={websiteTheme}
                        tailwind={createSparkTailwindRuntime(previewBlock)} onUpdate={() => {}} blogPosts={[]} />
                </AuthoredTypographyContext.Provider>
            </div>
        );
    }

    return (
        <div
            data-cosmic-render-shell="1"
            data-cosmic-render-contract={renderContract.version}
            data-cosmic-spark="1"
            data-cosmic-design-system="1"
            data-cosmic-block-index={index}
            data-cosmic-block-type={previewBlock.type}
            data-cosmic-marketplace-family={marketplaceFamily}
            data-cosmic-tailwind-schema={hasSparkTailwindSchema(previewBlock) ? 'schema_backed' : 'legacy_fallback'}
            data-cosmic-resolved-theme={resolvedTheme}
            data-cosmic-card-surface={localCardSurface || undefined}
            data-cosmic-background-state={String(previewBlock?.universal_background_state || resolvedTheme || 'light').toLowerCase()}
            data-cosmic-layout-mode={/fullscreen|cinematic/.test(blockType) ? 'immersive' : (/hero|banner/.test(blockType) ? 'hero' : 'standard')}
            data-luna-design={hasLunaDesign ? '1' : undefined}
            data-luna-design-typography={hasLegacyDesignTypography ? '1' : undefined}
            className={`cosmic-render-shell ${hasLunaDesign ? 'cosmic-luna-design-host' : ''}`}
            style={{ ...vars, ...legoVars, ...emberPaletteVars, ...ledgerPaletteVars }}
        >
            <div className="cosmic-render-content pointer-events-none">
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


const isExternalUrl = (value) => /^https?:\/\//i.test(String(value || ''));

const normalizedPath = (value) => {
    const path = String(value || '').split('?')[0].split('#')[0];
    if (!path || path === '/') return 'home';
    return path.replace(/^\/+|\/+$/g, '').split('/').pop() || 'home';
};

function demoHref(template, pageSlug, embed = false) {
    const base = String(template?.demo_path || '').replace(/\/$/, '');
    const slug = String(pageSlug || 'home');
    const path = slug === 'home' ? base : `${base}/${encodeURIComponent(slug)}`;
    return `${path}${embed ? '?embed=1' : ''}`;
}

function EmberMarketplaceTemplateHeader({ template, embed = false }) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const current = String(template?.current_page?.slug || 'home');
    const items = [
        ['Home', 'home'], ['About', 'about'], ['Menu', 'menu'], ['Private Dining', 'private-dining'], ['Contact', 'contact'],
    ];
    const href = (slug) => `${demoHref(template, slug, embed)}${slug === 'contact' && !embed ? '#reserve' : ''}`;
    return <header className="relative z-50 border-b" style={{ background: '#161613', borderColor: 'rgba(211,166,91,.18)', color: '#f3ecdf' }}>
        <div className="flex min-h-[86px] w-full items-center justify-between gap-6 px-6 sm:px-8 lg:px-[50px]">
            <a href={demoHref(template, 'home', embed)} className="shrink-0 font-serif text-[clamp(1.15rem,2vw,1.8rem)] uppercase tracking-[.16em] text-white">
                <span>Ember </span><span style={{ color: '#d5a153' }}>&amp;</span><span> Olive</span>
            </a>
            <nav className="hidden items-center gap-1 lg:flex" aria-label="Ember & Olive navigation">
                {items.map(([label, slug]) => <a key={label} href={href(slug)} className="relative px-4 py-3 text-xs font-semibold text-white/70 transition hover:text-white">
                    {label}{current === slug && <span className="absolute bottom-1 left-4 right-4 h-px" style={{ background: '#d5a153' }} />}
                </a>)}
                <a href={href('contact')} className="ml-3 inline-flex min-h-10 items-center justify-center rounded-[3px] px-5 text-xs font-bold text-[#17140f] shadow-[0_10px_24px_rgba(0,0,0,.2)] transition hover:-translate-y-0.5 hover:brightness-105" style={{ background: 'linear-gradient(180deg,#e7bd71,#c98d36)' }}>Reserve a Table</a>
            </nav>
            <button type="button" onClick={() => setMobileOpen((value) => !value)} className="grid h-10 w-10 place-items-center rounded-[3px] border text-lg lg:hidden" style={{ borderColor: 'rgba(255,255,255,.2)', color: '#f3ecdf' }} aria-label="Toggle navigation">{mobileOpen ? '×' : '☰'}</button>
        </div>
        {mobileOpen && <nav className="grid border-t px-6 py-4 lg:hidden" style={{ borderColor: 'rgba(211,166,91,.16)', background: '#191915' }}>{items.map(([label, slug]) => <a key={label} href={href(slug)} onClick={() => setMobileOpen(false)} className="border-b px-2 py-3 text-sm font-semibold text-white/75 last:border-b-0" style={{ borderColor: 'rgba(255,255,255,.08)' }}>{label}</a>)}<a href={href('contact')} onClick={() => setMobileOpen(false)} className="mt-4 inline-flex min-h-11 items-center justify-center rounded-[3px] px-5 text-sm font-bold text-[#17140f]" style={{ background: 'linear-gradient(180deg,#e7bd71,#c98d36)' }}>Reserve a Table</a></nav>}
    </header>;
}

function EmberMarketplaceTemplateFooter({ template, embed = false }) {
    const link = (slug) => demoHref(template, slug, embed);
    return <footer style={{ background: '#171713', color: '#eee5d7' }}>
        <div className="grid w-full gap-10 px-6 py-14 sm:px-8 md:grid-cols-2 lg:grid-cols-[1.35fr_.7fr_.7fr_1fr] lg:px-12 xl:px-16">
            <div><a href={link('home')} className="font-serif text-2xl uppercase tracking-[.13em] text-white"><span>Ember </span><span style={{ color: '#d5a153' }}>&amp;</span><span> Olive</span></a><p className="mt-4 max-w-[320px] text-xs leading-6 text-white/50">Fire-crafted cuisine. Genuine hospitality. Moments worth savoring.</p><div className="mt-6 flex gap-5 text-xs text-white/50"><span>◎</span><span>f</span><span>𝕏</span><span>◉</span></div></div>
            <div><p className="font-serif text-base text-white">Navigate</p><div className="mt-4 grid gap-2 text-xs text-white/50"><a href={link('home')}>Home</a><a href={link('about')}>About</a><a href={link('menu')}>Menu</a><a href={link('private-dining')}>Private Dining</a><a href={link('contact')}>Reservations</a><a href={link('contact')}>Contact</a></div></div>
            <div><p className="font-serif text-base text-white">Information</p><div className="mt-4 grid gap-2 text-xs text-white/50"><span>Gift Cards</span><span>Careers</span><span>Press</span><span>Accessibility</span><span>Privacy Policy</span><span>Terms of Service</span></div></div>
            <div><p className="font-serif text-base text-white">Contact</p><div className="mt-4 grid gap-2 text-xs leading-5 text-white/50"><span>(555) 123-4567</span><span>hello@emberandolive.com</span><span>123 Hearthwood Lane<br />Portland, OR 97201</span></div></div>
        </div>
        <div className="border-t" style={{ borderColor: 'rgba(255,255,255,.08)' }}><div className="flex w-full flex-col gap-2 px-6 py-5 text-[10px] text-white/40 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12 xl:px-16"><span>© {new Date().getFullYear()} Ember & Olive. All rights reserved.</span><span>Seasonal dining · warm hospitality</span></div></div>
    </footer>;
}

function LedgerMarketplaceTemplateHeader({ template, embed = false }) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const current = String(template?.current_page?.slug || 'home');
    const items = [['Home','home'],['About','about'],['Services','services'],['FAQ','faq']];
    const href = (slug) => demoHref(template, slug, embed);
    return <header className="relative z-50 border-b bg-white" style={{ borderColor:'#dfe7e5', color:'#102b33' }}><div className="flex min-h-[82px] w-full items-center justify-between gap-6 px-6 sm:px-8 lg:px-12 xl:px-16"><a href={href('home')} className="text-xl font-semibold tracking-[-.035em]"><span>Ledger</span><span style={{color:'#16838d'}}>Point</span><span className="ml-2 text-[9px] font-semibold uppercase tracking-[.18em] opacity-50">Accounting</span></a><nav className="hidden items-center gap-1 lg:flex" aria-label="LedgerPoint navigation">{items.map(([label,slug])=><a key={slug} href={href(slug)} className="relative px-4 py-3 text-sm font-semibold opacity-70 transition hover:opacity-100">{label}{current===slug&&<span className="absolute bottom-1 left-4 right-4 h-[2px]" style={{background:'#16838d'}} />}</a>)}<a href={href('contact')} className="ml-3 inline-flex min-h-11 items-center rounded-full px-6 text-sm font-semibold text-white" style={{background:'#0c6670'}}>Book a Consultation</a></nav><button type="button" onClick={()=>setMobileOpen(v=>!v)} className="grid h-10 w-10 place-items-center rounded-full border lg:hidden" style={{borderColor:'#dfe7e5'}} aria-label="Toggle navigation">{mobileOpen?'×':'☰'}</button></div>{mobileOpen&&<nav className="grid border-t px-6 py-4 lg:hidden" style={{borderColor:'#dfe7e5'}}>{items.map(([label,slug])=><a key={slug} href={href(slug)} className="border-b px-2 py-3 text-sm font-semibold" style={{borderColor:'#dfe7e5'}}>{label}</a>)}<a href={href('contact')} className="mt-4 inline-flex min-h-11 items-center justify-center rounded-full text-sm font-semibold text-white" style={{background:'#0c6670'}}>Book a Consultation</a></nav>}</header>;
}

function LedgerMarketplaceTemplateFooter({ template, embed = false }) {
    const href=(slug)=>demoHref(template,slug,embed);
    return <footer style={{background:'#09242d',color:'#fff'}}><div className="grid w-full gap-10 px-6 py-14 sm:px-8 md:grid-cols-2 lg:grid-cols-[1.5fr_.7fr_.7fr_1fr] lg:px-12 xl:px-16"><div><a href={href('home')} className="text-2xl font-semibold tracking-[-.035em]">Ledger<span style={{color:'#d3b166'}}>Point</span></a><p className="mt-4 max-w-[360px] text-sm leading-6 text-white/55">Clear accounting, practical advice, and a better view of what comes next.</p></div><div><p className="font-semibold">Navigate</p><div className="mt-4 grid gap-2 text-xs text-white/55"><a href={href('home')}>Home</a><a href={href('about')}>About</a><a href={href('services')}>Services</a></div></div><div><p className="font-semibold">Support</p><div className="mt-4 grid gap-2 text-xs text-white/55"><a href={href('faq')}>FAQ</a><span>Privacy</span><span>Terms</span></div></div><div><p className="font-semibold">Contact</p><div className="mt-4 grid gap-2 text-xs leading-5 text-white/55"><span>(02) 5550 0148</span><span>hello@ledgerpoint.example</span><span>Level 6 · 42 Market Street<br/>Sydney NSW</span></div></div></div><div className="border-t px-6 py-5 text-[10px] text-white/40 sm:px-8 lg:px-12 xl:px-16" style={{borderColor:'rgba(255,255,255,.1)'}}>© {new Date().getFullYear()} LedgerPoint Accounting. All rights reserved.</div></footer>;
}

function HarborMarketplaceTemplateHeader({ template, embed = false }) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const current = String(template?.current_page?.slug || 'home');
    const items = [['Home','home'],['Properties','listings'],['Buy','buyers'],['Sell','sellers'],['About Us','about'],['Neighborhoods','neighborhoods'],['Contact','contact']];
    const href=(slug)=>demoHref(template,slug,embed);
    const BrandMark=()=> <span className="flex items-center gap-3">
        <svg viewBox="0 0 44 52" className="h-[52px] w-[44px] shrink-0" fill="none" stroke="#c8a052" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 19 22 5l16 14"/><path d="M11 17v29M33 17v13M11 28h22"/><path d="M22 14v11M18 19h8"/><path d="M11 36h11l4 4h10"/><circle cx="36" cy="40" r="2.2"/></svg>
        <span className="leading-none"><span className="block font-serif text-[21px] tracking-[.055em] text-white">HARBOR &amp; KEY</span><span className="mt-1 block text-center text-[9px] font-bold tracking-[.42em]" style={{color:'#c8a052'}}>REALTY</span></span>
    </span>;
    return <header className="relative z-50" style={{background:'#071f3d',color:'#fff'}}>
        <div className="flex min-h-[80px] w-full items-center justify-between gap-5 px-5 sm:px-8 lg:px-[78px]">
            <a href={href('home')} className="shrink-0"><BrandMark/></a>
            <nav className="hidden min-w-0 items-center gap-1 xl:flex" aria-label="Harbor & Key navigation">
                {items.map(([label,slug])=><a key={slug} href={href(slug)} className="relative px-4 py-7 text-[12px] font-semibold text-white/90 transition hover:text-white">{label}{current===slug&&<span className="absolute bottom-0 left-4 right-4 h-[3px]" style={{background:'#c8a052'}}/>}</a>)}
            </nav>
            <div className="hidden shrink-0 items-center gap-5 xl:flex"><span className="inline-flex items-center gap-2 text-[12px] font-semibold text-white/90"><span style={{color:'#c8a052'}}>☎</span> +63 912 345 6789</span><a href={href('contact')} className="inline-flex min-h-[40px] items-center justify-center rounded-[3px] px-5 text-[11px] font-black text-white" style={{background:'#c6a052'}}>BOOK A CONSULTATION</a></div>
            <button type="button" onClick={()=>setMobileOpen(v=>!v)} className="grid h-10 w-10 place-items-center rounded-[3px] border text-lg xl:hidden" style={{borderColor:'rgba(255,255,255,.22)',color:'#fff'}} aria-label="Toggle navigation">{mobileOpen?'×':'☰'}</button>
        </div>
        {mobileOpen&&<nav className="grid border-t px-5 py-4 xl:hidden" style={{borderColor:'rgba(255,255,255,.12)',background:'#071f3d'}}>{items.map(([label,slug])=><a key={slug} href={href(slug)} onClick={()=>setMobileOpen(false)} className="border-b px-2 py-3 text-sm font-semibold text-white/80" style={{borderColor:'rgba(255,255,255,.08)'}}>{label}</a>)}<div className="mt-4 flex flex-col gap-3"><span className="text-xs text-white/70">☎ +63 912 345 6789</span><a href={href('contact')} className="inline-flex min-h-11 items-center justify-center rounded-[3px] text-xs font-black text-white" style={{background:'#c6a052'}}>BOOK A CONSULTATION</a></div></nav>}
    </header>;
}

function MarketplaceTemplateHeader({ template, palette, embed = false }) {
    const [mobileOpen, setMobileOpen] = useState(false);
    if (String(template?.slug || '') === 'bistro-classic') return <EmberMarketplaceTemplateHeader template={template} embed={embed} />;
    if (String(template?.slug || '') === 'ledger-start') return <LedgerMarketplaceTemplateHeader template={template} embed={embed} />;
    if (String(template?.slug || '') === 'harbor-key-realty') return <HarborMarketplaceTemplateHeader template={template} embed={embed} />;
    const navigation = Array.isArray(template?.navigation) ? template.navigation : [];
    const header = template?.global_header || {};
    const logoText = header.logo_text || header.brand || template?.name || 'Website';

    const hrefFor = (item) => {
        if (item?.page_slug) return demoHref(template, item.page_slug, embed);
        if (isExternalUrl(item?.url)) return item.url;
        return demoHref(template, normalizedPath(item?.url), embed);
    };

    const navItem = (item, mobile = false) => {
        const children = Array.isArray(item.children) ? item.children : [];
        const common = item.is_cta
            ? { background: palette.button_primary, color: palette.button_text }
            : { color: palette.header_text || palette.heading };

        if (children.length && !mobile) {
            return (
                <div key={item.id || item.label} className="group relative">
                    <a href={hrefFor(item)} target={item.target || undefined} className="inline-flex min-h-10 items-center gap-1.5 px-2 text-sm font-bold transition hover:opacity-70" style={common}>
                        {item.label} <span className="text-[10px] opacity-55">⌄</span>
                    </a>
                    <div className="invisible absolute left-0 top-full z-50 min-w-[220px] translate-y-2 rounded-2xl border p-2 opacity-0 shadow-[0_22px_60px_rgba(15,23,42,.16)] transition group-hover:visible group-hover:translate-y-0 group-hover:opacity-100" style={{ borderColor: palette.border, background: palette.surface }}>
                        {children.map((child) => (
                            <a key={child.id || child.label} href={hrefFor(child)} target={child.target || undefined} className="block rounded-xl px-4 py-3 text-sm font-bold transition hover:bg-slate-50" style={{ color: palette.heading }}>{child.label}</a>
                        ))}
                    </div>
                </div>
            );
        }

        return (
            <a
                key={item.id || item.label}
                href={hrefFor(item)}
                target={item.target || undefined}
                onClick={() => mobile && setMobileOpen(false)}
                className={item.is_cta
                    ? 'inline-flex min-h-11 items-center justify-center rounded-full px-5 text-sm font-black shadow-sm transition hover:-translate-y-0.5'
                    : 'inline-flex min-h-10 items-center px-2 text-sm font-bold transition hover:opacity-70'}
                style={common}
            >
                {item.label}
            </a>
        );
    };

    return (
        <header className="relative z-50 border-b backdrop-blur-xl" style={{ borderColor: palette.border, background: palette.header_bg || palette.surface }}>
            <div className="mx-auto flex min-h-[78px] max-w-[var(--cosmic-section-container,1280px)] items-center justify-between gap-5 px-5 sm:px-7 lg:px-8">
                <a href={demoHref(template, 'home', embed)} className="flex min-w-0 items-center gap-3 font-black tracking-[-.03em]" style={{ color: palette.header_text || palette.heading, fontFamily: palette.font_heading || undefined }}>
                    {header.logo_image_url ? <img src={header.logo_image_url} alt={logoText} className="h-10 max-w-[190px] object-contain" /> : <span className="truncate text-lg sm:text-xl">{logoText}</span>}
                </a>
                <nav className="hidden items-center gap-2 lg:flex" aria-label="Website navigation">
                    {navigation.map((item) => navItem(item))}
                </nav>
                <button type="button" onClick={() => setMobileOpen((value) => !value)} className="grid h-11 w-11 place-items-center rounded-xl border text-xl lg:hidden" style={{ color: palette.header_text || palette.heading, borderColor: palette.border }} aria-label="Toggle navigation">{mobileOpen ? '×' : '☰'}</button>
            </div>
            {mobileOpen && (
                <div className="border-t px-5 py-4 lg:hidden" style={{ borderColor: palette.border, background: palette.header_bg || palette.surface }}>
                    <nav className="mx-auto grid max-w-[var(--cosmic-section-container,1280px)] gap-1">
                        {navigation.map((item) => (
                            <div key={item.id || item.label} className="grid gap-1">
                                {navItem(item, true)}
                                {Array.isArray(item.children) && item.children.length > 0 && (
                                    <div className="ml-4 grid border-l pl-3" style={{ borderColor: palette.border }}>
                                        {item.children.map((child) => navItem(child, true))}
                                    </div>
                                )}
                            </div>
                        ))}
                    </nav>
                </div>
            )}
        </header>
    );
}

function HarborMarketplaceTemplateFooter({ template, embed = false }) {
    const footer = template?.global_footer || {};
    const mega = footer.mega_footer || {};
    const href=(slug)=>demoHref(template,slug,embed);
    const hrefFor=(url)=> isExternalUrl(url) ? url : href(normalizedPath(url));
    const fallbackColumns = [
        {title:'Quick Links',items:[{label:'Home',url:'/'},{label:'Properties',url:'/listings'},{label:'Buy',url:'/buyers'},{label:'Sell',url:'/sellers'},{label:'About Us',url:'/about'},{label:'Contact',url:'/contact'}]},
        {title:'Our Services',items:[{label:'Residential Sales',url:'/listings'},{label:'Luxury Properties',url:'/listings'},{label:'Rentals & Leasing',url:'/listings'},{label:'Property Management',url:'/contact'},{label:'Investment Consulting',url:'/contact'},{label:'Relocation Services',url:'/contact'}]},
        {title:'Contact Us',items:[{label:'+63 912 345 6789',url:'tel:+639123456789'},{label:'hello@harborandkey.com',url:'mailto:hello@harborandkey.com'},{label:'www.harborandkey.com',url:'/'},{label:'Mon–Sat · 9:00 AM–6:00 PM',url:'/contact'}]},
        {title:'Our Office',items:[{label:'Harbor & Key Realty',url:'/contact'},{label:'8F The Waterfront Tower',url:'/contact'},{label:'Lahug, Cebu City 6000',url:'/contact'},{label:'View on Map →',url:'/contact'}]},
    ];
    const columns = Array.isArray(mega.columns) && mega.columns.length ? mega.columns : fallbackColumns;
    const newsletter = footer.newsletter || {};
    const socials = Array.isArray(footer.social_links) ? footer.social_links : [];
    const BrandMark=()=> <span className="flex items-center gap-3"><svg viewBox="0 0 44 52" className="h-[48px] w-[40px] shrink-0" fill="none" stroke="#c8a052" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 19 22 5l16 14"/><path d="M11 17v29M33 17v13M11 28h22"/><path d="M22 14v11M18 19h8"/><path d="M11 36h11l4 4h10"/><circle cx="36" cy="40" r="2.2"/></svg><span className="leading-none"><span className="block font-serif text-[17px] tracking-[.055em] text-white">HARBOR &amp; KEY</span><span className="mt-1 block text-center text-[8px] font-bold tracking-[.42em]" style={{color:'#c8a052'}}>REALTY</span></span></span>;
    return <footer style={{background:'#071f3d',color:'#fff'}}>
        <div className="grid w-full gap-9 px-6 py-12 sm:px-8 md:grid-cols-2 lg:px-10 xl:grid-cols-[1.22fr_.68fr_.82fr_.9fr_.9fr_1.02fr] xl:gap-7 xl:px-[50px]">
            <div><a href={href('home')}><BrandMark/></a><p className="mt-4 max-w-[300px] text-[11px] leading-6 text-white/60">{mega.tagline || footer.tagline || footer.description || 'Connecting you to exceptional properties and experiences across Cebu and beyond.'}</p><p className="mt-6 text-[9px] font-black uppercase tracking-[.16em] text-white/50">Follow us</p><div className="mt-3 flex flex-wrap gap-4 text-[10px] font-bold text-white/70">{socials.length ? socials.map((item,index)=><a key={`${item.label}-${index}`} href={item.url||'#'}>{item.label}</a>) : <><span>f</span><span>◎</span><span>in</span><span>▶</span></>}</div></div>
            {columns.slice(0,4).map((column,index)=><div key={column.title||index}><p className="text-[10px] font-black uppercase tracking-[.14em] text-white/90">{column.title}</p><div className="mt-4 grid gap-2.5">{(column.items || column.links || []).slice(0,6).map((item,itemIndex)=><a key={`${item.label}-${itemIndex}`} href={hrefFor(item.url||'#')} className="text-[10px] leading-5 text-white/60 transition hover:text-white">{item.label}</a>)}</div></div>)}
            <div><p className="text-[10px] font-black uppercase tracking-[.14em] text-white/90">{newsletter.title || 'Newsletter'}</p><p className="mt-4 text-[10px] leading-5 text-white/60">{newsletter.text || 'Be the first to get the latest property listings and news.'}</p><div className="mt-4 grid gap-2"><input type="email" placeholder={newsletter.placeholder || 'Enter your email'} className="min-h-[38px] border bg-transparent px-3 text-[10px] text-white outline-none placeholder:text-white/30" style={{borderColor:'rgba(255,255,255,.28)',borderRadius:2}}/><button type="button" className="min-h-[38px] px-3 text-[9px] font-black text-white" style={{background:'#c6a052',borderRadius:2}}>{newsletter.button_label || 'SUBSCRIBE'}</button></div></div>
        </div>
        <div className="border-t px-6 py-5 text-[9px] text-white/40 sm:px-8 lg:px-10 xl:px-[50px]" style={{borderColor:'rgba(255,255,255,.1)'}}><div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><span>{footer.copyright || `© ${new Date().getFullYear()} Harbor & Key Realty. All Rights Reserved.`}</span><span className="flex flex-wrap gap-5"><a href={hrefFor(footer.privacy_url || '/privacy-policy')}>Privacy Policy</a><a href={hrefFor(footer.terms_url || '/terms-and-conditions')}>Terms of Use</a><a href="/sitemap.xml">Sitemap</a></span></div></div>
    </footer>;
}

function MarketplaceTemplateFooter({ template, palette, embed = false }) {
    if (String(template?.slug || '') === 'bistro-classic') return <EmberMarketplaceTemplateFooter template={template} embed={embed} />;
    if (String(template?.slug || '') === 'ledger-start') return <LedgerMarketplaceTemplateFooter template={template} embed={embed} />;
    if (String(template?.slug || '') === 'harbor-key-realty') return <HarborMarketplaceTemplateFooter template={template} embed={embed} />;
    const footer = template?.global_footer || {};
    const columns = Array.isArray(footer.columns) ? footer.columns : [];
    const pages = Array.isArray(template?.pages) ? template.pages : [];
    const hrefFor = (url) => {
        if (isExternalUrl(url)) return url;
        const slug = normalizedPath(url);
        return demoHref(template, pages.some((page) => page.slug === slug) ? slug : 'home', embed);
    };

    return (
        <footer style={{ background: palette.primary, color: palette.on_primary }}>
            <div className="mx-auto grid max-w-[var(--cosmic-section-container,1280px)] gap-10 px-5 py-14 sm:px-7 md:grid-cols-[1.2fr_2fr] lg:px-8 lg:py-16">
                <div>
                    <a href={demoHref(template, 'home', embed)} className="text-xl font-black tracking-[-.03em]">{footer.brand || template?.name}</a>
                    <p className="mt-4 max-w-md text-sm leading-6 opacity-70">{footer.description || template?.summary}</p>
                </div>
                {columns.length > 0 && (
                    <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                        {columns.map((column, index) => (
                            <div key={column.title || index}>
                                <p className="text-xs font-black uppercase tracking-[.18em] opacity-55">{column.title || 'Explore'}</p>
                                <div className="mt-4 grid gap-2.5">
                                    {(Array.isArray(column.links) ? column.links : []).map((link, linkIndex) => (
                                        <a key={`${link.label || link.title || 'Link'}-${linkIndex}`} href={hrefFor(link.url || link.href)} className="text-sm font-semibold opacity-75 transition hover:opacity-100">{link.label || link.title || 'Link'}</a>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
            <div className="border-t border-white/10">
                <div className="mx-auto flex max-w-[var(--cosmic-section-container,1280px)] flex-col gap-2 px-5 py-5 text-xs font-semibold opacity-55 sm:flex-row sm:items-center sm:justify-between sm:px-7 lg:px-8">
                    <span>© {new Date().getFullYear()} {footer.brand || template?.name}</span>
                    <span>Preview website · content will be personalized with Luna</span>
                </div>
            </div>
        </footer>
    );
}

export default function TemplateSiteRenderer({ template, embed = false }) {
    const websiteTheme = useMemo(() => ({
        ...(template?.theme_settings || {}),
        primary: template?.theme_key || template?.theme_settings?.primary || 'midnight',
    }), [template?.theme_key, template?.theme_settings]);

    const palette = useMemo(() => marketplaceDesignPalette(websiteTheme) || resolveSemanticPalette(websiteTheme.primary || 'midnight', websiteTheme), [websiteTheme]);
    const blocks = Array.isArray(template?.current_page?.blocks) ? template.current_page.blocks : [];
    const fixedMarketplaceDesign = Boolean(websiteTheme?.marketplace_design);
    const rootVars = {
        ...cosmicTypographyVars(websiteTheme?.typography || {}),
        ...cosmicSectionVars(websiteTheme?.section_layout || {}),
        ...(!fixedMarketplaceDesign ? cosmicBackgroundVars(
            websiteTheme.primary === 'my-brand' ? (websiteTheme.custom_brand_theme || colorFamilies.midnight) : (colorFamilies[websiteTheme.primary] || colorFamilies.midnight),
            websiteTheme?.background_style || {},
        ) : {}),
        ...cosmicComponentVars(websiteTheme?.marketplace_design?.components || websiteTheme?.components || {}),
        '--cosmic-primary': palette.primary,
        '--cosmic-surface': palette.brand_surface,
        '--cosmic-accent': palette.accent,
        '--cosmic-color-page': palette.page,
        '--cosmic-color-heading': palette.heading,
        '--cosmic-color-body': palette.body,
        '--cosmic-color-muted': palette.muted,
        '--cosmic-color-border': palette.border,
        '--cosmic-button-primary-bg': palette.button_primary,
        '--cosmic-button-primary-text': palette.button_text,
        ...(fixedMarketplaceDesign && palette.font_heading ? { '--cosmic-font-display': palette.font_heading } : {}),
        ...(fixedMarketplaceDesign && palette.font_body ? { '--cosmic-font-body': palette.font_body } : {}),
        ...(palette.font_body ? { fontFamily: palette.font_body } : {}),
    };

    return (
        <div className="min-h-screen overflow-x-hidden" style={{ ...rootVars, background: palette.page, color: palette.body }} data-marketplace-template-preview="1">
            <MarketplaceTemplateHeader template={template} palette={palette} embed={embed} />
            <main>
                {blocks.length > 0 ? blocks.map((block, index) => (
                    <MarketplaceTemplatePreviewBlock key={block.id || `${block.type}-${index}`} block={block} index={index} websiteTheme={websiteTheme} />
                )) : (
                    <section className="grid min-h-[55vh] place-items-center px-6 py-20 text-center">
                        <div><p className="text-sm font-black uppercase tracking-[.2em]" style={{ color: palette.primary }}>{template?.current_page?.name || 'Page'}</p><h1 className="mt-3 text-4xl font-black" style={{ color: palette.heading }}>Preview coming soon</h1></div>
                    </section>
                )}
            </main>
            <MarketplaceTemplateFooter template={template} palette={palette} embed={embed} />
        </div>
    );
}
