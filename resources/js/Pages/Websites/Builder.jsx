import { useEffect, useRef, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import html2canvas from 'html2canvas';
import { confirmCosmicAction, showCosmicNotification } from '../../Components/CosmicNotification';
import CreditBalanceBadge from '../../Components/CosmicCredits/CreditBalanceBadge';
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import { logoFilterFor } from '@/Branding/logoFilters';

import AddSectionModal, { GlobalSparkPreviewModal } from "./Components/AddSectionModal";
import { BlockRegistry as MarketplaceSparkRegistry } from "./Components/SparkRegistry";
import PageTemplatesModal from "./Components/PageTemplatesModal";
import SavePageTemplateModal from "./Components/SavePageTemplateModal";
import GeneratePageModal from "./Components/GeneratePageModal";

import ThemeSelector from "./Theme/ThemeSelector";
import { colorFamilies, installCustomBrandTheme } from "../../theme/colorFamilies";
import PageStyleSelector from "./PageStyle/PageStyleSelector";

import { BlockRegistry } from "./BlockRegistry";
import { BLOG_SPARK_GROUPS, FREE_BLOG_SPARKS } from "./Sparks/Blog";
import { DarkCyanHeader, GlassmorphismHeader } from './GenerateHeader';

import { MinimalFooter } from './GenerateFooter';
import MediaPickerModal from '@/Components/Media/MediaPickerModal';

const MEDIA_FIELD_PATTERN = /(image|photo|avatar|poster|logo|video|media)/i;

const isWebsiteUploadedMedia = (value, websiteId) => {
    if (typeof value !== 'string' || !websiteId) return false;
    const normalized = value.trim();
    if (!normalized) return false;
    return normalized.includes(`/storage/websites/${websiteId}/`) || normalized.includes(`/websites/${websiteId}/media-library/files/`);
};

const preserveUploadedMedia = (existing, generated, websiteId, fieldName = '') => {
    if (isWebsiteUploadedMedia(existing, websiteId) && MEDIA_FIELD_PATTERN.test(fieldName)) {
        return existing;
    }

    if (Array.isArray(existing) && Array.isArray(generated)) {
        return generated.map((item, index) =>
            preserveUploadedMedia(existing[index], item, websiteId, fieldName)
        );
    }

    if (existing && generated && typeof existing === 'object' && typeof generated === 'object' && !Array.isArray(existing) && !Array.isArray(generated)) {
        const merged = { ...generated };
        Object.keys(generated).forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(existing, key)) {
                merged[key] = preserveUploadedMedia(existing[key], generated[key], websiteId, key);
            }
        });
        return merged;
    }

    return generated;
};

const mergeAiBlocksWithProtectedMedia = (existingBlocks = [], generatedBlocks = [], websiteId = null) => {
    const usedIndexes = new Set();
    let preservedCount = 0;

    const blocks = generatedBlocks.map((generatedBlock, generatedIndex) => {
        const generatedType = generatedBlock?.type;
        let existingIndex = existingBlocks.findIndex((block, index) =>
            !usedIndexes.has(index) && block?.type === generatedType && index === generatedIndex
        );

        if (existingIndex < 0) {
            existingIndex = existingBlocks.findIndex((block, index) =>
                !usedIndexes.has(index) && block?.type === generatedType
            );
        }

        if (existingIndex < 0) return generatedBlock;
        usedIndexes.add(existingIndex);

        const existingBlock = existingBlocks[existingIndex];
        const merged = preserveUploadedMedia(existingBlock, generatedBlock, websiteId);

        const countProtected = (before, after, key = '') => {
            if (isWebsiteUploadedMedia(before, websiteId) && MEDIA_FIELD_PATTERN.test(key) && before === after) {
                preservedCount += 1;
                return;
            }
            if (Array.isArray(before) && Array.isArray(after)) {
                after.forEach((item, index) => countProtected(before[index], item, key));
                return;
            }
            if (before && after && typeof before === 'object' && typeof after === 'object' && !Array.isArray(before) && !Array.isArray(after)) {
                Object.keys(after).forEach((childKey) => countProtected(before[childKey], after[childKey], childKey));
            }
        };

        countProtected(existingBlock, merged);
        return merged;
    });

    return { blocks, preservedCount };
};

const createRenderKey = () => globalThis.crypto?.randomUUID?.()
    || `cosmic-${Date.now()}-${Math.random().toString(36).slice(2)}`;

const normalizeRenderKeys = (blocks = []) => {
    const used = new Set();

    return (blocks || []).map((block) => {
        let renderKey = block?._renderKey;

        if (!renderKey || used.has(renderKey)) {
            renderKey = createRenderKey();
        }

        used.add(renderKey);

        return {
            ...block,
            _renderKey: renderKey,
        };
    });
};

const stripClientBlockFields = (blocks = []) => (blocks || []).map(({ _renderKey, ...block }) => block);

const COMMERCE_PRODUCT_CONTEXT_BLOCKS = new Set([
    'commerce_product_gallery',
    'commerce_price',
    'commerce_variation_selector',
    'commerce_related_products',
]);

const bindDefaultCommerceProduct = (block, commerce) => {
    if (!COMMERCE_PRODUCT_CONTEXT_BLOCKS.has(block?.type) || block?.product_id) return block;
    const product = (commerce?.products || []).find((item) => item?.status === 'published' && item?.visibility !== 'hidden')
        || (commerce?.products || [])[0];
    return product ? { ...block, product_id: product.id } : block;
};

const publishHealthIssues = (health) => Object.values(health?.categories || {})
    .flatMap((category) => category?.findings || [])
    .filter((finding) => finding?.status === 'critical');





const withoutLegacyFooterSparks = (blocks = []) => (Array.isArray(blocks) ? blocks.filter((block) => !String(block?.type || '').startsWith('footer_')) : []);
const normalizeGlobalFooterBlock = (footer = {}) => ({
    ...(footer || {}),
    type: 'minimal_footer',
    privacy_label: footer?.privacy_label || 'Privacy Policy',
    privacy_url: footer?.privacy_url || '/privacy-policy',
    terms_label: footer?.terms_label || 'Terms & Conditions',
    terms_url: footer?.terms_url || '/terms-and-conditions',
    mega_enabled: Boolean(footer?.mega_enabled ?? footer?.mega_footer?.enabled ?? false),
    mega_footer: {
        theme: ['auto', 'primary', 'white', 'surface'].includes(footer?.mega_footer?.theme) ? footer.mega_footer.theme : 'auto',
        enabled: Boolean(footer?.mega_enabled ?? footer?.mega_footer?.enabled ?? false),
        tagline: footer?.mega_footer?.tagline || 'A premium information-rich footer.',
        primary_label: footer?.mega_footer?.primary_label || 'Get in touch',
        primary_url: footer?.mega_footer?.primary_url || '#contact',
        columns: Array.isArray(footer?.mega_footer?.columns) && footer.mega_footer.columns.length ? footer.mega_footer.columns.slice(0, 4) : [
            { title: 'Company', items: [{ label: 'About us', url: '#about' }, { label: 'Careers', url: '#careers' }, { label: 'Contact', url: '#contact' }] },
            { title: 'Services', items: [{ label: 'What we do', url: '#services' }, { label: 'Solutions', url: '#solutions' }, { label: 'Pricing', url: '#pricing' }] },
            { title: 'Resources', items: [{ label: 'Insights', url: '#insights' }, { label: 'Guides', url: '#guides' }, { label: 'Updates', url: '#updates' }] },
        ],
    },
});

export default function Builder({ page, website, previewUrl: initialPreviewUrl = null, previewDeployment: initialPreviewDeployment = null, blogPosts: initialBlogPosts = [], hasWebsiteContent = false, websiteContext = "", websitePages = [], trialMode = false, trialToken = null, trialExperience = null, websiteMediaPack = null, trialCapabilities = {}, cosmicPricing = {}, pageStyle = 'balanced', pageStyleOptions = [], themeAccess: builderThemeAccess = null, commerce = { enabled:false, currency:'USD', currency_decimals:2, products:[], categories:[] }, contentWorkspace = { types: [] }, websiteAccessRole = null }) {
    const { props } = usePage();
    const currentPlanKey = builderThemeAccess?.plan_key || props?.auth?.effectivePlanKey || props?.auth?.user?.plan_key || 'starter';
    // The Builder receives a route-specific entitlement payload because this
    // token-aware route can be rendered outside the normal auth route group.
    // Shared auth remains a backwards-compatible fallback only.
    const themeAccess = builderThemeAccess ?? props?.auth?.themeAccess ?? null;
    const { balance: creditBalance, setBalance: setCreditBalance } = useCreditBalance();

    const capabilities = {
        canNavigateAway: !trialMode,
        canChangeTheme: !trialMode,
        canGenerateAi: !trialMode,
        canPublish: !trialMode,
        canManageBlocks: !trialMode,
        canEditGlobalShell: !trialMode,
        canSave: true,
        canPurchase: trialMode,
        ...trialCapabilities,
    };
    const defaultHeader = {
        type: 'glassmorphism_header',
        logo_text: trialMode ? 'Your Logo' : (website?.name || 'Your Website'),
        logo_image_url: '/storage/branding/your-logo.png',
        logo_height: 42,
        logo_filter_key: 'midnight',
        overlay_header_on_banner: false,
        cta_label: 'Get Started',
        cta_url: '#',
        menu: [
            { label: 'Home', url: '#' },
            { label: 'About', url: '#' },
            { label: 'Services', url: '#' },
        ],
    };
    // Gi-apil na ang global_header sa form state
    const { data, setData, setDefaults, isDirty } = useForm({
        blocks: normalizeRenderKeys(withoutLegacyFooterSparks(page.blocks || [])),
        global_header: props.globalHeaderBlock || page.website?.global_header || defaultHeader,
        global_footer: normalizeGlobalFooterBlock(props.globalFooterBlock || page.website?.global_footer || { 
            type: 'minimal_footer',
            theme: 'white',
            logo_text: trialMode ? 'Your Logo' : (website?.name || 'Your Website'),
            logo_image_url: '/storage/branding/your-logo.png',
            logo_height: 36,
            logo_filter_key: 'midnight',
            copyright: '© 2026. All rights reserved.'
        })
    });

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isTemplatesOpen, setIsTemplatesOpen] = useState(false);
    const [isSaveTemplateOpen, setIsSaveTemplateOpen] = useState(false);
    const [isSavingTemplate, setIsSavingTemplate] = useState(false);
    const [isGeneratePageOpen, setIsGeneratePageOpen] = useState(false);
    const customWebsiteMode = false;
    const aiOnlyBuilder = !trialMode;
    const [customSparkOpen, setCustomSparkOpen] = useState(false);
    const [customSparkFile, setCustomSparkFile] = useState(null);
    const [customSparkBusy, setCustomSparkBusy] = useState(false);
    const [customSparkError, setCustomSparkError] = useState('');
    const [customSparkStage, setCustomSparkStage] = useState('');
    const [customSparkProgress, setCustomSparkProgress] = useState(0);
    const updateCustomBuildProgress = (next, completed = false) => {
        const numeric = Number(next);
        if (!Number.isFinite(numeric)) return;
        setCustomSparkProgress((current) => {
            const capped = completed ? 100 : Math.min(98, Math.max(1, numeric));
            return Math.max(Number(current) || 0, capped);
        });
    };
    const [customSparkInstructions, setCustomSparkInstructions] = useState('');
    const [cosmicAiChat, setCosmicAiChat] = useState({ open:false, blockIndex:null, key:'', name:'', messages:[], qa:null, targetScope:'section', targetKey:'' });
    const [cosmicAiPrompt, setCosmicAiPrompt] = useState('');
    const [cosmicAiAsset, setCosmicAiAsset] = useState(null);
    const [cosmicAiBusy, setCosmicAiBusy] = useState(false);
    const [cosmicAiError, setCosmicAiError] = useState('');
    const [pageAiOpen, setPageAiOpen] = useState(false);
    const [pageAiPrompt, setPageAiPrompt] = useState('');
    const [pageAiBusy, setPageAiBusy] = useState(false);
    const [pageAiError, setPageAiError] = useState('');
    const [lunaChatOpen, setLunaChatOpen] = useState(false);
    const [lunaScope, setLunaScope] = useState({ type:'page', blockIndex:null, label:'Whole Page' });
    const [lunaMessages, setLunaMessages] = useState([]);
    const [lunaUndoStack, setLunaUndoStack] = useState([]);



    const [savingCustomSparkKey, setSavingCustomSparkKey] = useState(null);
    const [savedCustomSparkKeys, setSavedCustomSparkKeys] = useState(() => new Set());
    const [customSparkLibrary, setCustomSparkLibrary] = useState([]);
    const [customSparkLibraryOpen, setCustomSparkLibraryOpen] = useState(false);
    const [customSparkLibraryBusy, setCustomSparkLibraryBusy] = useState(false);
    const [aiResult, setAiResult] = useState(null);
    const [aiLoading, setAiLoading] = useState(false);

    const [themeMenu, setThemeMenu] = useState(null);
    const [footerThemeMenu, setFooterThemeMenu] = useState(false);
    const [layoutMenu, setLayoutMenu] = useState(null);
    const [layoutApplying, setLayoutApplying] = useState(null);
    const [layoutBusyKey, setLayoutBusyKey] = useState(null);
    const [layoutPreview, setLayoutPreview] = useState(null);
    const [sparkCatalog, setSparkCatalog] = useState([]);
    const [sparkCatalogLoading, setSparkCatalogLoading] = useState(false);
    const [sparkCatalogLoaded, setSparkCatalogLoaded] = useState(false);
    const [pageTemplateCatalog, setPageTemplateCatalog] = useState([]);
    const [pageTemplateCatalogLoading, setPageTemplateCatalogLoading] = useState(false);
    const [pageTemplateCatalogLoaded, setPageTemplateCatalogLoaded] = useState(false);
    const [preparedSparkCount, setPreparedSparkCount] = useState(0);
    const [preparedTemplateCount, setPreparedTemplateCount] = useState(0);
    const [sparkInsertTarget, setSparkInsertTarget] = useState(null);
    const builderCanvasRef = useRef(null);
    const [builderViewportBudget, setBuilderViewportBudget] = useState(620);
    const [isSaving, setIsSaving] = useState(false);
    const [isPublishing, setIsPublishing] = useState(false);
    const [isCheckingHealth, setIsCheckingHealth] = useState(false);
    const [publishHealthReview, setPublishHealthReview] = useState(null);
    const [previewUrl, setPreviewUrl] = useState(initialPreviewUrl);
    const [previewDeployedAt, setPreviewDeployedAt] = useState(initialPreviewDeployment?.deployed_at || null);
    const [previewDeploymentError, setPreviewDeploymentError] = useState(initialPreviewDeployment?.error || '');
    const [saveError, setSaveError] = useState('');
    const [hasUnsavedTheme, setHasUnsavedTheme] = useState(false);
    const [showTrialEmailModal, setShowTrialEmailModal] = useState(false);
    const [trialEmail, setTrialEmail] = useState(trialExperience?.email || '');
    const [trialEmailCaptured, setTrialEmailCaptured] = useState(Boolean(trialExperience?.email_captured));
    const [trialEmailSaving, setTrialEmailSaving] = useState(false);
    const [trialPurchasePending, setTrialPurchasePending] = useState(false);
    const [showRegenerateModal, setShowRegenerateModal] = useState(false);
    const [regeneratePrompt, setRegeneratePrompt] = useState('');
    const [regenerating, setRegenerating] = useState(false);
    const [regenerateProgress, setRegenerateProgress] = useState(0);
    const [regenerateStage, setRegenerateStage] = useState('Understanding your new direction...');
    const [regenerationUsed, setRegenerationUsed] = useState(Number(trialExperience?.regenerations_used || 0));
    const [showLogoModal, setShowLogoModal] = useState(false);
    const [logoMediaLibraryOpen, setLogoMediaLibraryOpen] = useState(false);
    const [showLogoGenerateForm, setShowLogoGenerateForm] = useState(false);
    const [logoCompanyName, setLogoCompanyName] = useState(trialExperience?.logo_company_name || website?.name || '');
    const [logoBusy, setLogoBusy] = useState(false);
    const [logoReplacementStarted, setLogoReplacementStarted] = useState(false);
    const [logoAiAction, setLogoAiAction] = useState(null);
    const [logoAiProgress, setLogoAiProgress] = useState(0);
    const [logoAiStage, setLogoAiStage] = useState('');
    const [logoRegenerationsUsed, setLogoRegenerationsUsed] = useState(Number(trialExperience?.logo_regenerations_used || 0));
    const [logoSyncState, setLogoSyncState] = useState(() => {
        if (trialMode) return trialExperience?.logo_theme_sync_state || null;
        const raw = website?.theme_settings;
        if (raw && typeof raw === 'object') return raw.logo_theme_sync_state || null;
        if (typeof raw === 'string') {
            try { return JSON.parse(raw)?.logo_theme_sync_state || null; } catch (_) { return null; }
        }
        return null;
    });
    const [themeFromLogoPreview, setThemeFromLogoPreview] = useState(null);
    const [pendingUploadedLogoThemeChoice, setPendingUploadedLogoThemeChoice] = useState(null);
    const [pendingSvgLogoMatch, setPendingSvgLogoMatch] = useState(null);
    const [pendingThemeLogoAdapt, setPendingThemeLogoAdapt] = useState(null);
    const [themeLogoAdaptBusy, setThemeLogoAdaptBusy] = useState(false);
    // H23: only confirmed Regenerate Page may bypass the unsaved-changes
    // browser warning. All normal navigation/refresh/close protection remains.
    const intentionalRegenerateRef = useRef(false);
    // Trial-only unload suppression closes the small React dirty-state race after
    // a successful save/email capture before setDefaults() has propagated.
    // Normal registered Builder protection stays unchanged.
    const trialUnloadSuppressUntilRef = useRef(0);
    const lastSaveErrorRef = useRef('');
    const logoUploadRef = useRef(null);
    const logoCropFrameRef = useRef(null);
    const logoCropSafeFrameRef = useRef(null);
    const logoCropDragRef = useRef(null);
    const [logoCropOpen, setLogoCropOpen] = useState(false);
    const [logoCropSource, setLogoCropSource] = useState('');
    const [logoCropOriginalSourceUrl, setLogoCropOriginalSourceUrl] = useState('');
    const [logoCropSourceKind, setLogoCropSourceKind] = useState('upload');
    const [logoCropCompanyName, setLogoCropCompanyName] = useState('');
    const [logoCropZoom, setLogoCropZoom] = useState(1);
    const [logoCropX, setLogoCropX] = useState(0);
    const [logoCropY, setLogoCropY] = useState(0);
    const [logoCropNatural, setLogoCropNatural] = useState({ width: 0, height: 0 });
    const [logoCropSaving, setLogoCropSaving] = useState(false);
    const [logoCropEntryPrompt, setLogoCropEntryPrompt] = useState(false);
    const [logoCropAutoAdaptTheme, setLogoCropAutoAdaptTheme] = useState(true);
    const [trialLogoCropConfirmed, setTrialLogoCropConfirmed] = useState(Boolean(trialExperience?.logo_crop_confirmed));
    const trialLogoCropPromptedRef = useRef(false);
    const [pageStatus, setPageStatus] = useState(page.status || 'draft');
    const [publishError, setPublishError] = useState(page.publish_error || '');
    const [blogPosts, setBlogPosts] = useState(initialBlogPosts);
    const [currentPageStyle, setCurrentPageStyle] = useState(['balanced','clean','premium'].includes(String(pageStyle || '').toLowerCase()) ? String(pageStyle).toLowerCase() : 'balanced');
    const [styleOptions, setStyleOptions] = useState(pageStyleOptions || []);
    const hasUnsavedChanges = isDirty || hasUnsavedTheme;
    const previewIsStale = Boolean(previewUrl) && (hasUnsavedChanges || pageStatus !== 'published');

    useEffect(() => {
        // Trial email capture is prompted once on the first Builder landing.
        // If dismissed, it stays out of the way until the user explicitly clicks Save.
        if (trialMode && trialToken && !trialEmailCaptured) {
            setShowTrialEmailModal(true);
        }
    }, [trialMode, trialToken, trialEmailCaptured]);

    useEffect(() => {
        const rawServerBalance = cosmicPricing?.balance;
        const rawTrialBalance = trialExperience?.guest_credits;
        const resolvedBalance = rawServerBalance !== null && rawServerBalance !== undefined
            ? Number(rawServerBalance)
            : (rawTrialBalance !== null && rawTrialBalance !== undefined
                ? Number(rawTrialBalance)
                : (trialMode ? 500 : Number(creditBalance || 0)));

        if (Number.isFinite(resolvedBalance)) {
            setCreditBalance(resolvedBalance);
        }
    }, [cosmicPricing?.balance, trialExperience?.guest_credits, trialMode, setCreditBalance]);

    useEffect(() => {
        const warnBeforeLeaving = (event) => {
            const trialFlowOwnsNavigation = trialMode && (
                showTrialEmailModal
                || trialEmailSaving
                || trialPurchasePending
                || Date.now() < trialUnloadSuppressUntilRef.current
            );

            if (
                intentionalRegenerateRef.current
                || trialFlowOwnsNavigation
                || !hasUnsavedChanges
                || isSaving
                || isPublishing
            ) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', warnBeforeLeaving);

        return () => window.removeEventListener('beforeunload', warnBeforeLeaving);
    }, [hasUnsavedChanges, isPublishing, isSaving, showTrialEmailModal, trialEmailSaving, trialMode, trialPurchasePending]);

    useEffect(() => {
        // Preload the Spark Marketplace as soon as Builder lands so opening
        // Add Spark is a UI-only action, not another network round trip.
        if (customWebsiteMode) { setSparkCatalog([]); setSparkCatalogLoaded(true); return; }
        if (!(capabilities.canGenerateAi || capabilities.canManageBlocks || trialMode)) return;

        let cancelled = false;
        const endpoint = trialMode && trialToken
            ? `/trial-assets/${trialToken}/sparks`
            : '/sparks/catalog';

        setSparkCatalogLoading(true);
        axios.get(endpoint)
            .then(({ data: responseData }) => {
                if (cancelled) return;
                const sparks = responseData.sparks || [];
                setSparkCatalog(sparks);
                setPreparedSparkCount(Math.min(50, sparks.length));
                setSparkCatalogLoaded(true);
            })
            .catch(() => {
                if (cancelled) return;
                setSparkCatalog([]);
                setSparkCatalogLoaded(false);
            })
            .finally(() => {
                if (!cancelled) setSparkCatalogLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [capabilities.canGenerateAi, capabilities.canManageBlocks, trialMode, trialToken, customWebsiteMode]);


    useEffect(() => {
        // Templates follow the same fast-open contract as Sparks: fetch catalog
        // data once on Builder landing, keep it in memory, and never refetch just
        // because the modal was closed and reopened. Rendering remains lazy.
        if (customWebsiteMode) { setPageTemplateCatalog([]); setPageTemplateCatalogLoaded(true); return; }
        if (!(capabilities.canGenerateAi || trialMode)) return;

        let cancelled = false;
        const endpoint = trialMode && trialToken
            ? `/trial-assets/${trialToken}/templates`
            : '/page-templates/catalog';

        setPageTemplateCatalogLoading(true);
        axios.get(endpoint)
            .then(({ data: responseData }) => {
                if (cancelled) return;
                const templates = responseData.templates || [];
                setPageTemplateCatalog(templates);
                setPreparedTemplateCount(Math.min(50, templates.length));
                setPageTemplateCatalogLoaded(true);
            })
            .catch(() => {
                if (cancelled) return;
                setPageTemplateCatalog([]);
                setPageTemplateCatalogLoaded(false);
            })
            .finally(() => {
                if (!cancelled) setPageTemplateCatalogLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [capabilities.canGenerateAi, trialMode, trialToken, customWebsiteMode]);

    useEffect(() => {
        if (typeof window === 'undefined') return undefined;

        const measureBuilderViewport = () => {
            const canvas = builderCanvasRef.current;
            if (!canvas) return;

            const rect = canvas.getBoundingClientRect();
            const available = Math.floor(window.innerHeight - Math.max(0, rect.top) - 16);
            setBuilderViewportBudget(Math.max(420, Math.min(760, available)));
        };

        measureBuilderViewport();
        window.addEventListener('resize', measureBuilderViewport);

        const observer = typeof ResizeObserver !== 'undefined'
            ? new ResizeObserver(measureBuilderViewport)
            : null;
        if (observer && builderCanvasRef.current) observer.observe(builderCanvasRef.current);

        return () => {
            window.removeEventListener('resize', measureBuilderViewport);
            observer?.disconnect();
        };
    }, []);

    const trialActionCosts = {
        page_style: Number(cosmicPricing?.trial_actions?.page_style || 20),
        regenerate_page: Number(cosmicPricing?.trial_actions?.regenerate_page || 50),
        generate_logo: Number(cosmicPricing?.trial_actions?.generate_logo || 50),
        match_logo_to_theme: Number(cosmicPricing?.trial_actions?.match_logo_to_theme || 50),
        match_theme_to_logo: Number(cosmicPricing?.trial_actions?.match_theme_to_logo || 50),
    };

    // Trial balance must be available on the very first render, before React effects run.
    // Server props are the source of truth; 500 is only the final compatibility fallback.
    const effectiveCreditBalance = (() => {
        const candidates = [cosmicPricing?.balance, trialExperience?.guest_credits, creditBalance];
        for (const candidate of candidates) {
            if (candidate !== null && candidate !== undefined && candidate !== '') {
                const parsed = Number(candidate);
                if (Number.isFinite(parsed)) return parsed;
            }
        }
        return trialMode ? 500 : 0;
    })();

    const creditMessage = (cost) => `Cost: ${cost} Cosmic Credits. Balance: ${effectiveCreditBalance} → ${Math.max(0, effectiveCreditBalance - cost)}.`;

    const handleThemeChange = async (theme) => {
        if (!theme || theme === globalSelections?.primary) return;
        const isBrandTheme = theme === 'my-brand' && globalSelections?.custom_brand_theme;
        const cost = isBrandTheme ? 0 : Number(cosmicPricing?.themes?.[theme]?.credits || 20);
        const confirmed = await confirmCosmicAction({
            title: `Change theme to ${colorFamilies[theme]?.name || (isBrandTheme ? 'My Brand Theme' : theme)}?`,
            message: isBrandTheme
                ? 'Apply your saved custom brand colors. No additional Cosmic Credits are required.'
                : (trialMode
                    ? creditMessage(cost)
                    : `This theme costs ${cost} Cosmic Credits if it has not already been unlocked. The credit charge is finalized when you publish.`),
            confirmLabel: isBrandTheme ? 'Apply Theme' : (trialMode ? `Use ${cost} Credits` : `Select theme · ${cost} Credits`),
        });
        if (!confirmed) return;

        try {
            const hasRealLogo = data.global_header?.logo_image_url && !String(data.global_header.logo_image_url).includes('your-logo.png');
            const brandThemeMatchesCurrentLogo = Boolean(
                isBrandTheme
                && hasRealLogo
                && globalSelections?.custom_brand_theme?.source_logo_url
                && String(globalSelections.custom_brand_theme.source_logo_url) === String(data.global_header.logo_image_url)
            );
            if (trialMode) {
                const response = await axios.post(route('trial-pages.theme.apply', { trial: trialToken, page: page.id }), {
                    theme,
                    sync_source: brandThemeMatchesCurrentLogo ? 'theme_to_logo' : 'manual_theme_change',
                });
                if (Number.isFinite(Number(response.data.credit_balance))) setCreditBalance(Number(response.data.credit_balance));
            }
            const cssLogoMatched = String(globalSelections?.logo_theme_sync_source || '') === 'css_logo_to_theme'
                || String(data.global_header?.logo_theme_match_mode || '') === 'css';
            const nextThemeFilter = cssLogoMatched ? logoFilterFor(theme) : null;

            setGlobalSelections((prev) => ({
                ...prev,
                primary: theme,
                ...(hasRealLogo ? (brandThemeMatchesCurrentLogo
                    ? { logo_theme_sync_state: 'synced', logo_theme_sync_source: 'theme_to_logo', logo_theme_synced_theme: theme }
                    : (cssLogoMatched
                        ? { logo_theme_sync_state: 'synced', logo_theme_sync_source: 'css_logo_to_theme', logo_theme_synced_theme: theme }
                        : { logo_theme_sync_state: 'theme_changed', logo_theme_sync_source: 'manual_theme_change', logo_theme_synced_theme: null })) : {}),
            }));

            if (hasRealLogo && cssLogoMatched && nextThemeFilter) {
                setData((current) => ({
                    ...current,
                    global_header: {
                        ...(current.global_header || {}),
                        logo_filter: nextThemeFilter,
                        logo_filter_key: theme,
                        logo_theme_match_mode: 'css',
                    },
                    global_footer: {
                        ...(current.global_footer || {}),
                        logo_filter: nextThemeFilter,
                        logo_filter_key: theme,
                        logo_theme_match_mode: 'css',
                    },
                }));
            }
            if (['stone', 'white'].includes(String(theme).toLowerCase()) && data.global_header?.overlay_header_on_banner) {
                updateHeader({ overlay_header_on_banner: false });
                showCosmicNotification({
                    title: 'Overlay Header turned off',
                    message: 'Overlay Header isn’t compatible with Warm Stone or Studio White. Choose another theme to enable it.',
                    tone: 'info',
                });
            }
            if (hasRealLogo) {
                setLogoSyncState((brandThemeMatchesCurrentLogo || cssLogoMatched) ? 'synced' : 'theme_changed');
                if (!brandThemeMatchesCurrentLogo && !cssLogoMatched) {
                    const selectedFamily = colorFamilies[theme] || colorFamilies.midnight;
                    const selectedPalette = theme === 'my-brand'
                        ? (globalSelections?.custom_brand_theme?.palette || selectedFamily?.palette || {})
                        : (selectedFamily?.palette || {});
                    setPendingThemeLogoAdapt({
                        theme,
                        themeName: selectedFamily?.name || (theme === 'my-brand' ? 'My Brand Theme' : theme),
                        logoUrl: data.global_header.logo_image_url,
                        palette: selectedPalette,
                    });
                }
            }
            setHasUnsavedTheme(true);
        } catch (error) {
            showCosmicNotification({ title: 'Theme change unavailable', message: error.response?.data?.message || 'Cosmic could not apply this theme.', tone: 'error' });
        }
    };

    const prepareLogoCropSource = async (url) => {
        // Make every raster enter the cropper with tight visible alpha bounds.
        // This is intentionally browser-side so Luna and computer uploads have
        // identical behavior even when the server has no GD/Imagick support.
        try {
            const image = new Image();
            image.crossOrigin = 'anonymous';
            image.src = url;
            await new Promise((resolve, reject) => {
                if (image.complete && image.naturalWidth) return resolve();
                image.onload = resolve;
                image.onerror = reject;
            });

            const width = image.naturalWidth;
            const height = image.naturalHeight;
            if (!width || !height) return url;

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d', { willReadFrequently: true });
            context.clearRect(0, 0, width, height);
            context.drawImage(image, 0, 0, width, height);

            const pixels = context.getImageData(0, 0, width, height);
            let left = width;
            let top = height;
            let right = -1;
            let bottom = -1;
            const alphaThreshold = 8;

            for (let y = 0; y < height; y += 1) {
                for (let x = 0; x < width; x += 1) {
                    const alpha = pixels.data[((y * width) + x) * 4 + 3];
                    if (alpha <= alphaThreshold) continue;
                    left = Math.min(left, x);
                    top = Math.min(top, y);
                    right = Math.max(right, x);
                    bottom = Math.max(bottom, y);
                }
            }

            if (right < left || bottom < top) return url;

            // Remove only the oversized generation canvas, then add deliberate
            // transparent breathing room. The cropper contract is CONTAIN, not
            // COVER: a freshly generated/matched logo must be fully visible at
            // 100% zoom without the user rescuing clipped edges.
            const visibleWidth = Math.max(1, right - left + 1);
            const visibleHeight = Math.max(1, bottom - top + 1);
            const paddingX = Math.max(8, Math.round(visibleWidth * 0.12));
            const paddingY = Math.max(8, Math.round(visibleHeight * 0.12));
            const tightWidth = visibleWidth + (paddingX * 2);
            const tightHeight = visibleHeight + (paddingY * 2);

            const tightCanvas = document.createElement('canvas');
            tightCanvas.width = tightWidth;
            tightCanvas.height = tightHeight;
            const tightContext = tightCanvas.getContext('2d');
            tightContext.clearRect(0, 0, tightWidth, tightHeight);
            tightContext.drawImage(canvas, left, top, visibleWidth, visibleHeight, paddingX, paddingY, visibleWidth, visibleHeight);
            return tightCanvas.toDataURL('image/png');
        } catch (error) {
            console.warn('Logo pre-crop alpha trim unavailable; using original raster.', error);
            return url;
        }
    };

    const openLogoCrop = async (url, companyName = null, options = {}) => {
        // Store the original URL for temp-file cleanup, but display a tight
        // alpha-bounded raster. This gives Luna exactly the same crop geometry
        // as a normal computer-uploaded logo.
        const preparedSource = await prepareLogoCropSource(url);
        setLogoCropOriginalSourceUrl(url);
        setLogoCropSource(preparedSource);
        setLogoCropSourceKind(options.sourceKind || 'upload');
        setLogoCropCompanyName(companyName || logoCompanyName || data.global_header?.logo_text || website?.name || 'Your Logo');
        setLogoCropEntryPrompt(Boolean(options.entryPrompt));
        setLogoCropAutoAdaptTheme(options.autoAdaptTheme !== false);
        setLogoCropZoom(1);
        setLogoCropX(0);
        setLogoCropY(0);
        setLogoCropNatural({ width: 0, height: 0 });
        setShowLogoModal(false);
        setShowLogoGenerateForm(false);
        setLogoCropOpen(true);
    };

    useEffect(() => {
        if (!trialMode || !trialToken || !trialEmailCaptured || trialLogoCropConfirmed) return;
        if (trialLogoCropPromptedRef.current || showTrialEmailModal || logoCropOpen) return;

        const logoUrl = trialExperience?.logo_url || data.global_header?.logo_image_url;
        const isInitialAiLogo = Boolean(
            logoUrl
            && !String(logoUrl).includes('your-logo.png')
            && ['ai', 'ai-neutral'].includes(String(trialExperience?.logo_source || ''))
            && String(trialExperience?.logo_theme_sync_source || '') === 'initial_trial_generation'
        );

        if (!isInitialAiLogo) return;

        trialLogoCropPromptedRef.current = true;
        openLogoCrop(
            logoUrl,
            trialExperience?.logo_company_name || data.global_header?.logo_text || website?.name,
            { entryPrompt: true, autoAdaptTheme: false },
        );
    }, [
        trialMode,
        trialToken,
        trialEmailCaptured,
        trialLogoCropConfirmed,
        showTrialEmailModal,
        logoCropOpen,
        trialExperience?.logo_url,
        trialExperience?.logo_source,
        trialExperience?.logo_theme_sync_source,
    ]);

    const cancelLogoCrop = async () => {
        if (logoCropSaving) return;

        // The automatic first-entry crop prompt is optional. Cancel means keep
        // the current contained logo and do not nag again on later visits.
        if (trialMode && trialToken && logoCropEntryPrompt) {
            setTrialLogoCropConfirmed(true);
            try {
                await axios.post(route('trial-branding.logo.crop-dismiss', trialToken));
            } catch (_) {
                // Closing the modal should never be blocked by a persistence error.
            }
        }

        setLogoCropOpen(false);
        setLogoCropSource('');
        setLogoCropOriginalSourceUrl('');
        setLogoCropSourceKind('upload');
        setLogoCropCompanyName('');
        setLogoCropEntryPrompt(false);
        setLogoCropAutoAdaptTheme(true);
        setLogoCropZoom(1);
        setLogoCropX(0);
        setLogoCropY(0);
        setLogoCropNatural({ width: 0, height: 0 });
    };

    const resetLogoCrop = () => {
        setLogoCropZoom(1);
        setLogoCropX(0);
        setLogoCropY(0);
    };

    const handleLogoCropPointerDown = (event) => {
        if (logoCropSaving) return;
        event.currentTarget.setPointerCapture?.(event.pointerId);
        logoCropDragRef.current = {
            x: event.clientX,
            y: event.clientY,
            startX: logoCropX,
            startY: logoCropY,
        };
    };

    const handleLogoCropPointerMove = (event) => {
        const drag = logoCropDragRef.current;
        if (!drag || logoCropSaving) return;
        setLogoCropX(drag.startX + (event.clientX - drag.x));
        setLogoCropY(drag.startY + (event.clientY - drag.y));
    };

    const handleLogoCropPointerUp = (event) => {
        event.currentTarget.releasePointerCapture?.(event.pointerId);
        logoCropDragRef.current = null;
    };

    const autoAdaptThemeFromUploadedLogo = async (logoUrl) => {
        if (!logoUrl) return false;

        // Uploading a brand asset is now the design-system trigger. Do not ask
        // the user to press a second "match" button and do not silently charge
        // AI credits for an action Cosmic performs automatically after upload.
        startLogoAiAction('theme_to_logo');
        setLogoBusy(true);
        try {
            const response = trialMode
                ? await axios.post(route('trial-branding.theme.match-logo', trialToken), {
                    logo_url: logoUrl,
                    automatic_upload: true,
                })
                : await axios.post(route('websites.theme.match-logo', website.id), {
                    logo_url: logoUrl,
                    automatic_upload: true,
                });

            const customTheme = response.data?.custom_theme;
            if (!customTheme) throw new Error('Cosmic AI did not return a usable brand theme.');

            const syncedCustomTheme = {
                ...customTheme,
                source_logo_url: logoUrl,
            };

            if (trialMode) {
                const applyResponse = await axios.post(
                    route('trial-pages.theme.apply', { trial: trialToken, page: page.id }),
                    { theme: 'my-brand', sync_source: 'theme_to_logo' }
                );
                if (Number.isFinite(Number(applyResponse.data?.credit_balance))) {
                    setCreditBalance(Number(applyResponse.data.credit_balance));
                }
            }

            installCustomBrandTheme(syncedCustomTheme);
            setGlobalSelections((current) => ({
                ...current,
                primary: 'my-brand',
                custom_brand_theme: syncedCustomTheme,
                brand_palette: syncedCustomTheme.palette || response.data?.palette,
                brand_source: 'logo',
                brand_original_logo_url: current.brand_original_logo_url || logoUrl,
                brand_active_logo_url: logoUrl,
                logo_theme_sync_state: 'synced',
                logo_theme_sync_source: 'theme_to_logo',
                logo_theme_synced_theme: 'my-brand',
            }));
            setLogoSyncState('synced');
            setHasUnsavedTheme(true);
            setThemeFromLogoPreview(null);
            setLogoAiStage('Your website now matches your logo.');
            finishLogoAiAction();
            showCosmicNotification({
                title: 'Brand theme applied',
                message: 'Cosmic AI matched the website colors to your uploaded logo automatically.',
                tone: 'success',
            });
            return true;
        } catch (error) {
            cancelLogoAiAction();
            const rateLimited = Number(error.response?.status) === 429
                || /too many attempts/i.test(String(error.response?.data?.message || error.message || ''));

            if (rateLimited) {
                try {
                    const rollback = trialMode
                        ? await axios.post(route('trial-branding.logo.rollback-upload', trialToken))
                        : await axios.post(route('websites.logo.rollback-upload', website.id));
                    const restoredUrl = rollback.data?.url;
                    if (restoredUrl) {
                        applyTrialLogo(restoredUrl, data.global_header?.logo_text);
                        setGlobalSelections((current) => ({
                            ...current,
                            brand_original_logo_url: restoredUrl,
                            brand_active_logo_url: restoredUrl,
                            logo_theme_sync_state: rollback.data?.sync_state ?? current.logo_theme_sync_state,
                            logo_theme_sync_source: rollback.data?.sync_source ?? current.logo_theme_sync_source,
                            logo_theme_synced_theme: rollback.data?.synced_theme ?? current.logo_theme_synced_theme,
                        }));
                        setLogoSyncState(rollback.data?.sync_state ?? null);
                    }
                } catch (rollbackError) {
                    console.error('Could not roll back throttled logo upload', rollbackError);
                }

                showCosmicNotification({
                    title: 'Too many attempts · previous logo restored',
                    message: 'The new logo was not applied. Your previous logo has been restored; try again after the cooldown.',
                    tone: 'warning',
                });
                return false;
            }

            showCosmicNotification({
                title: 'Logo saved · theme kept',
                message: error.response?.data?.message || error.message || 'Cosmic could not derive a brand theme from this logo, so your current theme was kept.',
                tone: 'warning',
            });
            return false;
        } finally {
            setLogoBusy(false);
        }
    };

    const saveLogoCrop = async () => {
        if (!logoCropSource || !logoCropNatural.width || !logoCropNatural.height || logoCropSaving) return;

        const workspace = logoCropFrameRef.current;
        const safeFrame = logoCropSafeFrameRef.current;
        if (!workspace || !safeFrame) return;

        const workspaceRect = workspace.getBoundingClientRect();
        const safeRect = safeFrame.getBoundingClientRect();
        const workspaceWidth = workspaceRect.width;
        const workspaceHeight = workspaceRect.height;
        const safeWidth = safeRect.width;
        const safeHeight = safeRect.height;

        // Fit against the actual green header frame, not the larger square
        // workspace. This makes 100% a true "fully contained" state and
        // prevents wide wordmarks from being clipped before the user even
        // touches the crop controls.
        const baseUiScale = Math.min(
            safeWidth / logoCropNatural.width,
            safeHeight / logoCropNatural.height
        );
        const drawUiWidth = logoCropNatural.width * baseUiScale * logoCropZoom;
        const drawUiHeight = logoCropNatural.height * baseUiScale * logoCropZoom;
        const drawUiX = ((workspaceWidth - drawUiWidth) / 2) + logoCropX;
        const drawUiY = ((workspaceHeight - drawUiHeight) / 2) + logoCropY;

        const safeLeft = safeRect.left - workspaceRect.left;
        const safeTop = safeRect.top - workspaceRect.top;
        const outputWidth = 650;
        const outputHeight = 200;
        const scaleX = outputWidth / safeWidth;
        const scaleY = outputHeight / safeHeight;
        const drawX = (drawUiX - safeLeft) * scaleX;
        const drawY = (drawUiY - safeTop) * scaleY;
        const drawWidth = drawUiWidth * scaleX;
        const drawHeight = drawUiHeight * scaleY;

        setLogoCropSaving(true);
        try {
            const image = new Image();
            image.crossOrigin = 'anonymous';
            image.src = logoCropSource;
            await new Promise((resolve, reject) => {
                if (image.complete && image.naturalWidth) return resolve();
                image.onload = resolve;
                image.onerror = reject;
            });

            const canvas = document.createElement('canvas');
            canvas.width = outputWidth;
            canvas.height = outputHeight;
            const context = canvas.getContext('2d', { willReadFrequently: true });
            context.clearRect(0, 0, outputWidth, outputHeight);
            context.drawImage(image, drawX, drawY, drawWidth, drawHeight);

            // IMPORTANT: 650x200 is only the cropper/header framing surface.
            // Do not persist that whole transparent canvas as the logo asset.
            // Tighten the raster to the actual visible alpha bounds here in the
            // browser so Luna-generated logos behave exactly like tight local
            // uploads even when the PHP host has no GD/Imagick extension.
            const pixels = context.getImageData(0, 0, outputWidth, outputHeight);
            let left = outputWidth;
            let top = outputHeight;
            let right = -1;
            let bottom = -1;
            const alphaThreshold = 8; // preserve anti-aliased/glow edge pixels

            for (let y = 0; y < outputHeight; y += 1) {
                for (let x = 0; x < outputWidth; x += 1) {
                    const alpha = pixels.data[((y * outputWidth) + x) * 4 + 3];
                    if (alpha <= alphaThreshold) continue;
                    left = Math.min(left, x);
                    top = Math.min(top, y);
                    right = Math.max(right, x);
                    bottom = Math.max(bottom, y);
                }
            }

            let exportCanvas = canvas;
            if (right >= left && bottom >= top) {
                // Preserve a real transparent safety area in the saved asset.
                // Previously this collapsed the user's crop back to ~6px and
                // effectively undid the cropper's 10–15% safe-area promise.
                const visibleWidth = Math.max(1, right - left + 1);
                const visibleHeight = Math.max(1, bottom - top + 1);
                const safetyX = Math.max(8, Math.round(visibleWidth * 0.12));
                const safetyY = Math.max(8, Math.round(visibleHeight * 0.12));
                const paddedWidth = visibleWidth + (safetyX * 2);
                const paddedHeight = visibleHeight + (safetyY * 2);

                // The saved PNG must stay inside the server/header contract.
                // Safety padding can otherwise make an already-wide 650x200
                // crop exceed either axis and trigger a 422 in the crop endpoint.
                const containScale = Math.min(
                    1,
                    outputWidth / paddedWidth,
                    outputHeight / paddedHeight
                );
                const tightWidth = Math.max(1, Math.round(paddedWidth * containScale));
                const tightHeight = Math.max(1, Math.round(paddedHeight * containScale));
                const scaledVisibleWidth = Math.max(1, Math.round(visibleWidth * containScale));
                const scaledVisibleHeight = Math.max(1, Math.round(visibleHeight * containScale));
                const scaledSafetyX = Math.max(0, Math.round(safetyX * containScale));
                const scaledSafetyY = Math.max(0, Math.round(safetyY * containScale));

                const tightCanvas = document.createElement('canvas');
                tightCanvas.width = tightWidth;
                tightCanvas.height = tightHeight;
                const tightContext = tightCanvas.getContext('2d');
                tightContext.clearRect(0, 0, tightWidth, tightHeight);
                tightContext.drawImage(
                    canvas,
                    left,
                    top,
                    visibleWidth,
                    visibleHeight,
                    scaledSafetyX,
                    scaledSafetyY,
                    scaledVisibleWidth,
                    scaledVisibleHeight
                );
                exportCanvas = tightCanvas;
            }

            const imageData = exportCanvas.toDataURL('image/png');

            const response = trialMode
                ? await axios.post(route('trial-branding.logo.crop', trialToken), {
                    image_data: imageData,
                    company_name: logoCropCompanyName,
                    source_url: logoCropOriginalSourceUrl || logoCropSource,
                    source_kind: logoCropSourceKind,
                })
                : await axios.post(route('websites.logo.crop', website.id), {
                    image_data: imageData,
                    source_url: logoCropOriginalSourceUrl || logoCropSource,
                });

            applyTrialLogo(response.data.url, logoCropCompanyName);
            setGlobalSelections((prev) => {
                if (logoCropSourceKind === 'theme_match') {
                    const activeTheme = prev.primary || 'midnight';
                    return {
                        ...prev,
                        brand_original_logo_url: prev.brand_original_logo_url || data.global_header?.logo_image_url || response.data.url,
                        brand_active_logo_url: response.data.url,
                        brand_logo_variants: { ...(prev.brand_logo_variants || {}), [activeTheme]: response.data.url },
                        logo_theme_sync_state: 'synced',
                        logo_theme_sync_source: 'logo_to_theme',
                        logo_theme_synced_theme: activeTheme,
                    };
                }
                return {
                    ...prev,
                    brand_original_logo_url: response.data.url,
                    brand_active_logo_url: response.data.url,
                    brand_logo_variants: {},
                };
            });
            if (logoCropSourceKind === 'theme_match') {
                setLogoSyncState('synced');
            }
            setLogoCropOpen(false);
            setLogoCropOriginalSourceUrl('');
            setHasUnsavedTheme(true);
            if (trialMode) setTrialLogoCropConfirmed(true);
            if (logoCropAutoAdaptTheme) {
                if (trialMode) {
                    await autoAdaptThemeFromUploadedLogo(response.data.url);
                } else if (logoCropSourceKind === 'upload' && logoSyncState !== 'synced') {
                    // Only a genuine new upload may offer "Match Theme to Logo".
                    // Theme-matched/generated crops are terminal sync operations
                    // and must never trigger the inverse prompt.
                    setPendingUploadedLogoThemeChoice(response.data.url);
                }
            }
            setLogoCropEntryPrompt(false);
            setLogoCropAutoAdaptTheme(true);
        } catch (error) {
            showCosmicNotification({
                title: 'Unable to save logo crop',
                message: error.response?.data?.message || 'The crop could not be saved. Try repositioning the logo or cancel and keep the current logo.',
                tone: 'error',
            });
        } finally {
            setLogoCropSaving(false);
        }
    };

    const applyTrialLogo = (url, companyName = null) => {
        const nextLogoText = companyName || data.global_header?.logo_text || website?.name || 'Your Logo';
        const nextHeader = {
            ...data.global_header,
            logo_image_url: url,
            logo_text: nextLogoText,
            logo_height: Math.max(60, Number(data.global_header?.logo_height || 0)),
            logo_max_width: Math.max(300, Number(data.global_header?.logo_max_width || 0)),
            logo_filter: 'none',
        };
        const nextFooter = {
            ...(data.global_footer || {}),
            logo_image_url: url,
            logo_text: nextLogoText,
            logo_filter: 'none',
            logo_filter_key: nextHeader.logo_filter_key || data.global_footer?.logo_filter_key || globalSelections?.primary || 'midnight',
        };

        // Header logo is the single source of truth. Every logo action updates
        // both shell locations immediately so preview/save/export never drift.
        setData((current) => ({
            ...current,
            global_header: {
                ...(current.global_header || {}),
                ...nextHeader,
            },
            global_footer: {
                ...(current.global_footer || {}),
                ...nextFooter,
            },
        }));
        setShowLogoModal(false);
        setShowLogoGenerateForm(false);
    };

    const uploadTrialLogo = async (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;

        const allowedTypes = ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showCosmicNotification({ title: 'Unsupported logo format', message: 'Upload an SVG, PNG, JPG, or WebP logo.', tone: 'error' });
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showCosmicNotification({ title: 'Logo is too large', message: 'Choose a logo smaller than 2 MB.', tone: 'error' });
            return;
        }

        setLogoReplacementStarted(true);

        // As soon as a real upload starts, stop tinting the default placeholder.
        // The placeholder follows the active theme only until the user begins
        // replacing it with their own brand asset.
        setData({
            ...data,
            global_header: {
                ...data.global_header,
                logo_filter: 'none',
            },
            global_footer: {
                ...(data.global_footer || {}),
                logo_filter: 'none',
            },
        });

        setLogoBusy(true);
        try {
            const form = new FormData();
            form.append('image', file);
            let response;
            if (trialMode) {
                if (!trialToken) return;
                response = await axios.post(route('trial-branding.logo.upload', trialToken), form);
            } else {
                form.append('website_id', website.id);
                response = await axios.post(route('websites.logo.upload'), form);
            }
            if (file.type === 'image/svg+xml') {
                applyTrialLogo(response.data.url);
                setLogoSyncState('logo_changed');
                setGlobalSelections((prev) => ({
                    ...prev,
                    brand_original_logo_url: response.data.url,
                    brand_active_logo_url: response.data.url,
                    brand_logo_variants: {},
                    logo_theme_sync_state: 'logo_changed',
                    logo_theme_sync_source: 'upload',
                    logo_theme_synced_theme: null,
                    ...(response.data?.is_majority_white ? { overlay_header_on_banner: true } : {}),
                }));

                // SVG uploads are free and keep the original artwork. If the
                // uploaded SVG is predominantly white/light, make it visible by
                // enabling header overlay locally. /start generation itself still
                // defaults overlay OFF; this only reacts to an explicit upload.
                if (response.data?.is_majority_white) {
                    setData((current) => ({
                        ...current,
                        global_header: {
                            ...(current.global_header || {}),
                            overlay_header_on_banner: true,
                        },
                    }));
                    showCosmicNotification({
                        title: 'White logo detected',
                        message: 'Overlay Header was enabled automatically so your light logo stays visible. You can turn it off anytime.',
                        tone: 'success',
                    });
                }

                setHasUnsavedTheme(true);
                // Never auto-spend credits after an SVG upload. Offer the user a
                // deliberate 50-credit Logo → Theme adaptation instead.
                setPendingSvgLogoMatch(response.data.url);
            } else {
                // Raster uploads remain user-controlled: crop first, then the
                // confirmed crop becomes the source for automatic theme analysis.
                setLogoSyncState('logo_changed');
                setGlobalSelections((prev) => ({ ...prev, logo_theme_sync_state: 'logo_changed', logo_theme_sync_source: 'upload', logo_theme_synced_theme: null }));
                setHasUnsavedTheme(true);
                await openLogoCrop(response.data.url, logoCompanyName || data.global_header?.logo_text, { sourceKind: 'upload' });
            }
        } catch (error) {
            showCosmicNotification({ title: 'Unable to upload logo', message: error.response?.data?.message || 'Please try another logo file.', tone: 'error' });
        } finally {
            setLogoBusy(false);
        }
    };

    const matchUploadedLogoToTheme = async () => {
        const logoUrl = pendingUploadedLogoThemeChoice;
        if (!logoUrl || trialMode || logoBusy) return;

        setPendingUploadedLogoThemeChoice(null);
        startLogoAiAction('theme_to_logo');
        setLogoBusy(true);
        try {
            const response = await axios.post(route('websites.theme.match-logo', website.id), {
                logo_url: logoUrl,
                post_upload_choice: true,
            });

            if (Number.isFinite(Number(response.data?.balance))) {
                setCreditBalance(Number(response.data.balance));
            }

            const customTheme = response.data?.custom_theme;
            if (!customTheme) throw new Error('Cosmic AI did not return a usable My Brand Theme.');
            const syncedCustomTheme = { ...customTheme, source_logo_url: logoUrl };

            installCustomBrandTheme(syncedCustomTheme);
            setGlobalSelections((current) => ({
                ...current,
                primary: 'my-brand',
                custom_brand_theme: syncedCustomTheme,
                brand_palette: syncedCustomTheme.palette || response.data?.palette,
                brand_source: 'logo',
                brand_original_logo_url: current.brand_original_logo_url || logoUrl,
                brand_active_logo_url: logoUrl,
                logo_theme_sync_state: 'synced',
                logo_theme_sync_source: 'theme_to_logo',
                logo_theme_synced_theme: 'my-brand',
            }));
            setLogoSyncState('synced');
            setHasUnsavedTheme(true);
            setLogoAiStage('Your website now matches your logo.');
            finishLogoAiAction();
            showCosmicNotification({
                title: 'My Brand Theme applied',
                message: 'Cosmic matched the website colors to your uploaded logo for 20 credits.',
                tone: 'success',
            });
        } catch (error) {
            cancelLogoAiAction();
            showCosmicNotification({
                title: 'Unable to match theme',
                message: error.response?.data?.message || error.message || 'Your logo is saved, but Cosmic could not build a matching theme. No theme change was applied.',
                tone: 'error',
            });
        } finally {
            setLogoBusy(false);
        }
    };

    const keepCurrentThemeAfterLogoUpload = () => {
        // Keep the uploaded logo + current visual theme, but preserve a durable
        // logo_changed state. This exposes the deferred “Match Theme to Logo”
        // action on My Brand Theme until the user runs it successfully.
        setPendingUploadedLogoThemeChoice(null);
        setLogoSyncState('logo_changed');
        setGlobalSelections((prev) => ({
            ...prev,
            logo_theme_sync_state: 'logo_changed',
            logo_theme_sync_source: 'upload_keep_theme',
            logo_theme_synced_theme: null,
        }));
        setHasUnsavedTheme(true);
        showCosmicNotification({
            title: 'Logo saved · theme match pending',
            message: 'Your current theme was kept. Open Themes and use Match Theme to Logo under My Brand Theme whenever you are ready.',
            tone: 'success',
        });
    };

    const startLogoAiAction = (action) => {
        const stages = {
            generate: 'Preparing your brand direction...',
            regenerate: 'Creating a fresh logo variation...',
            logo_to_theme: 'Adapting your logo to the active theme...',
            theme_to_logo: 'Analyzing your logo colors...',
        };
        setLogoAiAction(action);
        setLogoAiStage(stages[action] || 'Working with Cosmic AI...');
        setLogoAiProgress(6);
    };

    const finishLogoAiAction = () => {
        setLogoAiProgress(100);
        window.setTimeout(() => {
            setLogoAiAction(null);
            setLogoAiProgress(0);
            setLogoAiStage('');
        }, 280);
    };

    const cancelLogoAiAction = () => {
        setLogoAiAction(null);
        setLogoAiProgress(0);
        setLogoAiStage('');
    };

    useEffect(() => {
        if (!logoBusy || !logoAiAction) return undefined;

        const stageMap = {
            generate: [
                [12, 'Preparing your brand direction...'],
                [34, 'Choosing a balanced color treatment...'],
                [62, 'Cosmic AI is creating your logo...'],
                [86, 'Cleaning up the final artwork...'],
            ],
            regenerate: [
                [12, 'Reviewing your current brand...'],
                [34, 'Exploring a fresh logo variation...'],
                [62, 'Cosmic AI is regenerating your logo...'],
                [86, 'Cleaning up the final artwork...'],
            ],
            logo_to_theme: [
                [12, 'Reading the active website theme...'],
                [34, 'Mapping the brand palette...'],
                [62, 'Cosmic AI is adapting your logo...'],
                [86, 'Applying the exact theme colors...'],
            ],
            theme_to_logo: [
                [12, 'Inspecting your logo colors...'],
                [34, 'Identifying the strongest brand palette...'],
                [62, 'Building My Brand Theme...'],
                [86, 'Preparing your theme preview...'],
            ],
        };

        let progress = 6;
        const stages = stageMap[logoAiAction] || [];
        const timer = window.setInterval(() => {
            progress = Math.min(94, progress + (progress < 38 ? 4 : progress < 72 ? 2 : 1));
            setLogoAiProgress(progress);
            const stage = [...stages].reverse().find(([threshold]) => progress >= threshold);
            if (stage) setLogoAiStage(stage[1]);
        }, 420);

        return () => window.clearInterval(timer);
    }, [logoBusy, logoAiAction]);

    const generateTrialLogo = async () => {
        if (logoCompanyName.trim().length < 2) return;
        if (trialMode && !trialToken) return;
        const hasLogo = data.global_header?.logo_image_url && !String(data.global_header.logo_image_url).includes('your-logo.png');
        const cost = trialMode ? trialActionCosts.generate_logo : 50;
        const confirmed = await confirmCosmicAction({
            title: hasLogo ? 'Regenerate this logo?' : 'Generate this logo?',
            message: creditMessage(cost),
            confirmLabel: `Use ${cost} Credits`,
        });
        if (!confirmed) return;
        startLogoAiAction(hasLogo ? 'regenerate' : 'generate');
        setLogoBusy(true);
        try {
            const currentThemeKey = globalSelections?.primary || 'midnight';
            const currentThemeFamily = colorFamilies[currentThemeKey] || colorFamilies.midnight;
            const currentThemePalette = currentThemeKey === 'my-brand'
                ? (globalSelections?.custom_brand_theme?.palette || currentThemeFamily?.palette || {})
                : (currentThemeFamily?.palette || {});
            const currentPrimaryHex = currentThemePalette?.background || currentThemePalette?.primary || '#243447';

            const response = trialMode
                ? await axios.post(route('trial-branding.logo.generate', trialToken), {
                    company_name: logoCompanyName.trim(),
                    theme_key: currentThemeKey,
                    primary_hex: currentPrimaryHex,
                })
                : await axios.post(route('websites.logo.generate', website.id), {
                    company_name: logoCompanyName.trim(),
                    primary: currentThemeKey,
                    primary_hex: currentPrimaryHex,
                });
            if (trialMode) {
                if (Number.isFinite(Number(response.data.credit_balance))) setCreditBalance(Number(response.data.credit_balance));
            } else if (Number.isFinite(Number(response.data.balance))) {
                setCreditBalance(Number(response.data.balance));
            }
            setLogoAiStage('Your logo is ready.');
            finishLogoAiAction();

            // Generated/regenerated raster logos use the SAME crop path as computer
            // uploads. Do not pre-wrap them in a 650x200 canvas server-side;
            // the cropper owns contain/zoom/position and the saved result is tight.
            await openLogoCrop(response.data.url, response.data.company_name || logoCompanyName.trim(), {
                entryPrompt: false,
                autoAdaptTheme: false,
                sourceKind: 'ai',
            });

            showCosmicNotification({ title: 'Logo generated', message: trialMode ? 'Cosmic AI created your logo. Adjust the crop, then save it to apply it to your trial website.' : `Cosmic AI created your logo. Adjust the crop, then save it to apply it. ${response.data.cost || 50} credits used.`, tone: 'success' });
        } catch (error) {
            cancelLogoAiAction();
            showCosmicNotification({ title: 'Unable to generate logo', message: error.response?.data?.message || 'Cosmic AI could not create the logo. Please try again.', tone: 'error' });
        } finally {
            setLogoBusy(false);
        }
    };


    const keepCurrentLogoForSelectedTheme = () => {
        // Keeping the logo is a temporary visual choice, not a dismissal of
        // the mismatch. Preserve the pending state so the ACTIVE theme card
        // continues to offer “Match Logo to Theme” later.
        setLogoSyncState('theme_changed');
        setGlobalSelections((prev) => ({
            ...prev,
            logo_theme_sync_state: 'theme_changed',
            logo_theme_sync_source: 'manual_theme_change',
            logo_theme_synced_theme: null,
        }));
        setPendingThemeLogoAdapt(null);
    };

    const adaptLogoToSelectedTheme = async () => {
        if (!pendingThemeLogoAdapt || themeLogoAdaptBusy) return;
        setThemeLogoAdaptBusy(true);
        try {
            await matchLogoToTheme(true);
        } finally {
            setThemeLogoAdaptBusy(false);
        }
    };

    const restoreOriginalLogo = async () => {
        if (logoBusy) return;
        setLogoBusy(true);
        try {
            const response = trialMode
                ? await axios.post(route('trial-branding.logo.restore-original', trialToken))
                : await axios.post(route('websites.logo.restore-original', website.id));
            applyTrialLogo(response.data.url, data.global_header?.logo_text);
            setLogoSyncState('logo_changed');
            setGlobalSelections((prev) => ({
                ...prev,
                brand_active_logo_url: response.data.url,
                logo_theme_sync_state: 'logo_changed',
                logo_theme_sync_source: 'restore_original',
                logo_theme_synced_theme: null,
            }));
            setHasUnsavedTheme(true);
            showCosmicNotification({ title: 'Original logo restored', message: 'Header and footer now use your preserved original logo.', tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to restore logo', message: error.response?.data?.message || 'No preserved original logo is available yet.', tone: 'error' });
        } finally {
            setLogoBusy(false);
        }
    };

    const matchLogoToTheme = async (skipConfirmation = false) => {
        const logoUrl = data.global_header?.logo_image_url || '/storage/branding/your-logo.png';
        if (!logoUrl) return;

        const themeKey = pendingThemeLogoAdapt?.theme || globalSelections?.primary || 'midnight';
        const family = colorFamilies[themeKey] || colorFamilies.midnight;
        const themeFilter = logoFilterFor(themeKey);

        if (!skipConfirmation) {
            const matchConfirmed = await confirmCosmicAction({
                title: `Match logo to ${family?.name || 'current theme'}?`,
                message: 'Apply an instant CSS theme treatment to your logo. No AI generation and no Cosmic Credits are required.',
                confirmLabel: 'Match Logo · Free',
            });
            if (!matchConfirmed) return;
        }

        setPendingSvgLogoMatch(null);
        setPendingUploadedLogoThemeChoice(null);
        setPendingThemeLogoAdapt(null);

        setData((current) => ({
            ...current,
            global_header: {
                ...(current.global_header || {}),
                logo_filter: themeFilter,
                logo_filter_key: themeKey,
                logo_theme_match_mode: 'css',
            },
            global_footer: {
                ...(current.global_footer || {}),
                logo_filter: themeFilter,
                logo_filter_key: themeKey,
                logo_theme_match_mode: 'css',
            },
        }));
        setLogoSyncState('synced');
        setGlobalSelections((prev) => ({
            ...prev,
            logo_theme_sync_state: 'synced',
            logo_theme_sync_source: 'css_logo_to_theme',
            logo_theme_synced_theme: themeKey,
        }));
        setHasUnsavedTheme(true);
        showCosmicNotification({
            title: 'Logo matched instantly',
            message: `Your logo now follows ${family?.name || 'the current theme'} using CSS. No credits used.`,
            tone: 'success',
        });
    };

    const matchThemeToLogo = async () => {
        const logoUrl = data.global_header?.logo_image_url || '/storage/branding/your-logo.png';
        if (!logoUrl) return;

        // This action belongs to My Brand Theme, not Customize Logo.
        // Never allow the old logo modal to reappear during this transition.
        setShowLogoModal(false);
        setShowLogoGenerateForm(false);
        setThemeFromLogoPreview(null);

        const analysisCost = trialMode ? trialActionCosts.match_theme_to_logo : 50;
        const analysisConfirmed = await confirmCosmicAction({
            title: 'Match theme to this logo?',
            message: creditMessage(analysisCost),
            confirmLabel: `Use ${analysisCost} Credits`,
        });
        if (!analysisConfirmed) return;

        startLogoAiAction('theme_to_logo');
        setLogoBusy(true);
        try {
            const response = trialMode
                ? await axios.post(route('trial-branding.theme.match-logo', trialToken), { logo_url: logoUrl })
                : await axios.post(route('websites.theme.match-logo', website.id), { logo_url: logoUrl });

            if (trialMode && Number.isFinite(Number(response.data.credit_balance))) {
                setCreditBalance(Number(response.data.credit_balance));
            } else if (!trialMode && Number.isFinite(Number(response.data.balance))) {
                setCreditBalance(Number(response.data.balance));
            }
            setLogoAiStage('Your brand theme preview is ready.');
            setLogoAiProgress(100);

            // Transition order is intentional:
            // AI loading overlay -> close -> Apply Theme preview.
            window.setTimeout(() => {
                setShowLogoModal(false);
                setShowLogoGenerateForm(false);
                setLogoAiAction(null);
                setLogoAiProgress(0);
                setLogoAiStage('');
                setThemeFromLogoPreview(response.data);
            }, 320);
        } catch (error) {
            cancelLogoAiAction();
            setShowLogoModal(false);
            setShowLogoGenerateForm(false);
            showCosmicNotification({
                title: 'Unable to match My Brand Theme',
                message: error.response?.data?.message || 'Cosmic AI could not build My Brand Theme from this logo. No theme changes were applied.',
                tone: 'error',
            });
        } finally {
            setLogoBusy(false);
        }
    };

    const applyThemeFromLogo = async () => {
        const customTheme = themeFromLogoPreview?.custom_theme;
        if (!customTheme) return;
        const family = 'my-brand';
        try {
            if (trialMode) {
                const response = await axios.post(route('trial-pages.theme.apply', { trial: trialToken, page: page.id }), { theme: family, sync_source: 'theme_to_logo' });
                if (Number.isFinite(Number(response.data.credit_balance))) setCreditBalance(Number(response.data.credit_balance));
            }
            const syncedCustomTheme = {
                ...customTheme,
                source_logo_url: data.global_header?.logo_image_url || customTheme.source_logo_url,
            };
            installCustomBrandTheme(syncedCustomTheme);
            setGlobalSelections((current) => ({
                ...current,
                primary: family,
                custom_brand_theme: syncedCustomTheme,
                brand_palette: syncedCustomTheme.palette || themeFromLogoPreview.palette,
                brand_source: 'logo',
                logo_theme_sync_state: 'synced',
                logo_theme_sync_source: 'theme_to_logo',
                logo_theme_synced_theme: family,
            }));
            setLogoSyncState('synced');
            setHasUnsavedTheme(true);
            setThemeFromLogoPreview(null);
            setShowLogoModal(false);
            showCosmicNotification({
                title: 'My Brand Theme applied',
                message: 'Your custom logo colors are now the active website theme and are saved as My Brand Theme.',
                tone: 'success',
            });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to apply matched theme', message: error.response?.data?.message || 'Cosmic could not apply My Brand Theme.', tone: 'error' });
        }
    };

    // Update logic para sa mga blocks
    const updateBlockContent = (index, updatedFields) => {
        const updatedBlocks = [...data.blocks];
        updatedBlocks[index] = { ...updatedBlocks[index], ...updatedFields };
        setData('blocks', updatedBlocks);
    };

    // Bag-ong logic para ma-update ang global header
    const updateHeader = (updatedFields) => {
        // Trial users may control only the safe global overlay preference.
        // Full builder users retain the existing global-header editing controls.
        if (!capabilities.canEditGlobalShell) {
            if (trialMode && Object.keys(updatedFields || {}).every((key) => key === 'overlay_header_on_banner')) {
                setData('global_header', { ...data.global_header, ...updatedFields });
            }
            return;
        }
        setData('global_header', { ...data.global_header, ...updatedFields });
    };

    const updateFooter = (updatedFields) => {
        if (!capabilities.canEditGlobalShell) return;
        setData('global_footer', { ...data.global_footer, ...updatedFields });
    };

    const replaceBlocks = (newBlocks) => {
        // AI may replace Spark content/layouts, but customer-uploaded page media
        // is protected whenever the corresponding Spark still exists. Global
        // header/footer/navigation live outside this replacement path entirely.
        const protectedMerge = mergeAiBlocksWithProtectedMedia(
            data.blocks || [],
            newBlocks || [],
            website?.id
        );

        setData(
            "blocks",
            protectedMerge.blocks.map(block => ({
                ...block,
                _renderKey: createRenderKey()
            }))
        );

        if (protectedMerge.preservedCount > 0) {
            showCosmicNotification({
                title: 'Brand assets protected',
                message: `${protectedMerge.preservedCount} uploaded image${protectedMerge.preservedCount === 1 ? '' : 's'} were preserved while Cosmic AI refreshed the Sparks.`,
                tone: 'success',
            });
        }

        setIsModalOpen(false);
        return protectedMerge.preservedCount;
    };

    const addBlock = (block) => {
        const commerceBoundBlock = bindDefaultCommerceProduct(block, commerce);
        const newBlock = {
            ...commerceBoundBlock,
            theme: commerceBoundBlock.theme || "auto",
            _renderKey: createRenderKey(),
        };

        const blocks = [...data.blocks];

        if (sparkInsertTarget) {
            const insertionIndex = sparkInsertTarget.position === 'above'
                ? sparkInsertTarget.index
                : sparkInsertTarget.index + 1;
            blocks.splice(insertionIndex, 0, newBlock);
        } else {
            blocks.push(newBlock);
        }

        setData("blocks", blocks);
        setSparkInsertTarget(null);
        setIsModalOpen(false);
    };
    const removeBlock = (index) => {

        const updatedBlocks = data.blocks.filter((_, i) => i !== index);


        setData("blocks", updatedBlocks);

    };

    const generateWithAI = async (prompt) => {
        setAiLoading(true);
        try {
            const response = await axios.post('/ai/generate', { prompt });
            setAiResult(response.data);
        } catch (error) {
            showCosmicNotification({ title: 'Generation failed', message: 'Cosmic AI could not generate this block. Please try again.', tone: 'error' });
        } finally {
            setAiLoading(false);
        }
    };

    const saveDraft = async ({ skipEmailGate = false } = {}) => {
        if (trialMode && !trialEmailCaptured && !skipEmailGate) {
            setShowTrialEmailModal(true);
            return false;
        }
        setIsSaving(true);
        setSaveError('');
        lastSaveErrorRef.current = '';

        try {
            const saveUrl = trialMode
                ? `${route('pages.builder.save', page.id)}?token=${encodeURIComponent(trialToken)}`
                : route('pages.builder.save', page.id);

            const response = await axios.post(saveUrl, {
                blocks: stripClientBlockFields(data.blocks),
                global_header: data.global_header,
                global_footer: data.global_footer,
                theme_settings: globalSelections,
            });

            setPageStatus(response.data.page_status || 'draft');
            setCreditBalance(response.data.credit_balance);
            setPublishError('');
            setDefaults();
            setHasUnsavedTheme(false);
            if (trialMode) {
                // Give React one render cycle to clear isDirty before the browser
                // is allowed to evaluate beforeunload again. This prevents the
                // recurring native Reload/Leave dialog immediately after Save.
                trialUnloadSuppressUntilRef.current = Date.now() + 1500;
            }
            return true;
        } catch (error) {
            console.error(error);
            const validationMessage = error.response?.data?.errors
                ? Object.values(error.response.data.errors).flat().join(' ')
                : '';
            const message = error.response?.data?.message
                || validationMessage
                || 'Unable to save your changes. Please try again.';
            lastSaveErrorRef.current = typeof message === 'string'
                ? message
                : 'Unable to save your changes. Please try again.';
            setSaveError(lastSaveErrorRef.current);
            return false;
        } finally {
            setIsSaving(false);
        }
    };

    const saveAsTemplate = async ({ name, description }) => {
        if (trialMode || isSavingTemplate) return;
        if (!Array.isArray(data.blocks) || data.blocks.length === 0) {
            showCosmicNotification({ title: 'Nothing to save yet', message: 'Add at least one Spark before saving this page as a template.', tone: 'warning' });
            return;
        }

        setIsSavingTemplate(true);
        try {
            const response = await axios.post(route('page-templates.saved.store'), {
                website_id: website.id,
                page_id: page.id,
                name,
                description,
                blocks: stripClientBlockFields(data.blocks),
                metadata: {
                    page_style: currentPageStyle || 'balanced',
                    section_count: data.blocks.length,
                    tags: ['Saved', 'Builder'],
                },
            });
            setIsSaveTemplateOpen(false);
            showCosmicNotification({
                title: 'Template saved',
                message: response.data?.message || 'Your page is now available in Saved Templates.',
                tone: 'success',
            });
        } catch (error) {
            const validationMessage = error.response?.data?.errors
                ? Object.values(error.response.data.errors).flat().join(' ')
                : '';
            showCosmicNotification({
                title: 'Unable to save template',
                message: error.response?.data?.message || validationMessage || 'Please try again.',
                tone: 'error',
            });
        } finally {
            setIsSavingTemplate(false);
        }
    };

    const goToTrialPricing = () => {
        trialUnloadSuppressUntilRef.current = Date.now() + 5000;
        window.location.assign(`${route('pricing')}?token=${encodeURIComponent(trialToken)}`);
    };

    const handleBuyTrialWebsite = async (event) => {
        event?.preventDefault?.();
        if (!trialMode || !trialToken || isSaving || trialEmailSaving || trialPurchasePending) return;

        // Buying must never bypass the required trial email capture.
        if (!trialEmailCaptured) {
            setTrialPurchasePending(true);
            setShowTrialEmailModal(true);
            return;
        }

        setTrialPurchasePending(true);
        const saved = await saveDraft({ skipEmailGate: true });
        if (saved) {
            goToTrialPricing();
            return;
        }
        setTrialPurchasePending(false);
        showCosmicNotification({
            title: 'Unable to continue to pricing',
            message: lastSaveErrorRef.current || 'Your latest Builder changes could not be saved. Please try again.',
            tone: 'error',
        });
    };

    const captureTrialEmailAndSave = async (event) => {
        event.preventDefault();
        if (!trialEmail.trim()) return;
        setTrialEmailSaving(true);
        setSaveError('');
        try {
            await axios.post(route('trial-generations.email.capture', trialToken), { email: trialEmail.trim() });
            setTrialEmailCaptured(true);
            setShowTrialEmailModal(false);
            const saved = await saveDraft({ skipEmailGate: true });
            if (saved) {
                if (trialPurchasePending) {
                    goToTrialPricing();
                    return;
                }
                showCosmicNotification({
                    title: 'Your page is saved',
                    message: 'We sent your private editing link to your email.',
                    tone: 'success',
                });
            } else if (trialPurchasePending) {
                setTrialPurchasePending(false);
            }
        } catch (error) {
            setSaveError(error.response?.data?.message || 'We could not save your email. Please try again.');
        } finally {
            setTrialEmailSaving(false);
        }
    };

    useEffect(() => {
        if (!regenerating) {
            setRegenerateProgress(0);
            setRegenerateStage('Understanding your new direction...');
            return undefined;
        }

        const stages = [
            { at: 10, text: 'Understanding your new direction...' },
            { at: 24, text: 'Choosing a fresh theme and brand direction...' },
            { at: 42, text: 'Choosing a different layout and Sparks...' },
            { at: 64, text: 'Finding fresh images and writing new content...' },
            { at: 84, text: 'Generating your new logo and favicon...' },
            { at: 92, text: 'Rebuilding your full website...' },
        ];
        let progress = 4;
        setRegenerateProgress(progress);
        const timer = window.setInterval(() => {
            progress = Math.min(94, progress + (progress < 35 ? 4 : progress < 75 ? 2 : 1));
            setRegenerateProgress(progress);
            const stage = [...stages].reverse().find((item) => progress >= item.at);
            if (stage) setRegenerateStage(stage.text);
        }, 450);

        return () => window.clearInterval(timer);
    }, [regenerating]);

    const handleRegenerate = async (event) => {
        event.preventDefault();
        if (!regeneratePrompt.trim()) return;
        const regenCost = trialActionCosts.regenerate_page;
        const confirmed = await confirmCosmicAction({
            title: 'Regenerate this trial website?',
            message: `${creditMessage(regenCost)} This rebuilds everything — logo, favicon, theme, Sparks, images, content and layout. Your current page stays safe if the main generation fails; if only fresh logo generation fails, Cosmic safely keeps your previous logo.`,
            confirmLabel: `Use ${regenCost} Credits`,
        });
        if (!confirmed) return;

        // User has explicitly approved replacing this page. From this point,
        // regeneration navigation is intentional and must not trigger the
        // generic unsaved-changes browser prompt.
        intentionalRegenerateRef.current = true;
        setRegenerating(true);
        setSaveError('');
        try {
            const response = await axios.post(route('trial-generations.regenerate', trialToken), {
                prompt: regeneratePrompt.trim(),
                mode: 'full_trial',
            });
            setRegenerateProgress(100);
            setRegenerateStage('Your new website is ready.');

            // The server has already replaced the trial draft successfully.
            // Navigate straight to the regenerated Builder without a browser
            // "Leave site?" confirmation.
            window.setTimeout(() => {
                window.location.assign(response.data.redirect_url);
            }, 250);
        } catch (error) {
            // Nothing was overwritten on failure; restore the normal browser
            // protection for the user's existing unsaved Builder state.
            intentionalRegenerateRef.current = false;
            const message = error.response?.data?.message || 'Regeneration failed. Your current page was preserved.';
            setSaveError(message);
            showCosmicNotification({ title: 'Regeneration unavailable', message, tone: 'error' });
        } finally {
            setRegenerating(false);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        await saveDraft();
    };

    const performPublish = async () => {
        setIsPublishing(true);

        try {
            const response = await axios.post(route('pages.publish', page.id));

            setPageStatus(response.data.status || 'published');
            setCreditBalance(response.data.credit_balance);
            if (response.data.preview_url) setPreviewUrl(response.data.preview_url);
            if (response.data.preview_deployed_at) setPreviewDeployedAt(response.data.preview_deployed_at);
            setPreviewDeploymentError(response.data.preview_deployment_failed
                ? (response.data.preview_deployment_message || 'Preview deployment failed.')
                : '');
            setPublishError('');

            const postPublishHealth = response.data.health;
            const healthSuffix = postPublishHealth
                ? ` Website health: ${postPublishHealth.score}/100${postPublishHealth.summary?.critical > 0 ? ' · needs attention.' : postPublishHealth.summary?.warning > 0 ? ' · review warnings.' : '.'}`
                : '';

            showCosmicNotification({
                title: response.data.preview_deployment_failed ? 'Page published' : 'Published',
                message: (response.data.preview_deployment_failed
                    ? (response.data.preview_deployment_message || 'Published successfully, but the preview could not be refreshed.')
                    : 'Published and preview deployed successfully.') + healthSuffix,
                tone: response.data.preview_deployment_failed ? 'warning' : 'success',
            });
        } catch (error) {
            const message = error.response?.data?.message ||
                'Publishing failed. Your previous live version is still available.';
            setPublishError(message);
            showCosmicNotification({
                title: 'Publishing failed',
                message,
                tone: 'error',
            });
        } finally {
            setIsPublishing(false);
        }
    };

    const handlePublish = async () => {
        setPublishError('');
        setPublishHealthReview(null);

        // Always persist the exact Builder state first. Health checks then scan
        // the same saved draft that the publisher will use.
        const saved = await saveDraft();

        if (!saved) {
            showCosmicNotification({
                title: 'Save required before publishing',
                message: 'Your latest Builder changes could not be saved, so publishing was stopped.',
                tone: 'error',
            });
            return;
        }

        let needsReview = false;
        setIsCheckingHealth(true);
        try {
            const response = await axios.get(route('websites.health.show', website.id), {
                params: { page_id: page.id },
            });
            const health = response.data?.health;
            const issues = publishHealthIssues(health);
            if (issues.length > 0) {
                setPublishHealthReview(health);
                needsReview = true;
            }
        } catch (error) {
            // Health is advisory. A temporary scanner problem must not strand a
            // customer who has a valid saved draft ready to publish.
            showCosmicNotification({
                title: 'Health check unavailable',
                message: 'Cosmic could not complete the pre-publish check, so publishing will continue normally.',
                tone: 'warning',
            });
        } finally {
            setIsCheckingHealth(false);
        }

        if (needsReview) return;
        await performPublish();
    };

    const publishAfterHealthReview = async () => {
        setPublishHealthReview(null);
        await performPublish();
    };

    const moveBlock = (index, direction) => {
        const newBlocks = [...data.blocks];
        const targetIndex = direction === 'up' ? index - 1 : index + 1;

        // Check kung valid ang move
        if (targetIndex >= 0 && targetIndex < newBlocks.length) {
            [newBlocks[index], newBlocks[targetIndex]] = [newBlocks[targetIndex], newBlocks[index]];
            setData('blocks', newBlocks);
        }
    };

    const [globalTheme, setGlobalTheme] = useState(trialMode ? 'light' : 'dark');

    const [globalSelections, setGlobalSelections] = useState(() => {
        // 1. Define ang imong mga default
        const defaults = {
            primary: 'emerald',
            secondary: 'white',
            tertiary: 'stone',

            auto: true
        };

        let savedSettings = {};

        // 2. Sulayi og parse ang gikan sa DB
        if (typeof website.theme_settings === 'string') {
            try {
                savedSettings = JSON.parse(website.theme_settings);
            } catch (e) {
                console.error("Error parsing theme_settings:", e);
            }
        } else if (typeof website.theme_settings === 'object' && website.theme_settings !== null) {
            savedSettings = website.theme_settings;
        }

        // 3. I-merge ang defaults ug ang savedSettings
        // Ang savedSettings ang mo-override sa defaults kon naa na silay value
        return { ...defaults, ...savedSettings, secondary: 'white', tertiary: 'stone'};
    });

    // H20.1: brand-sync derived state must be declared only after
    // globalSelections has been initialized. Referencing it earlier triggers
    // JavaScript's temporal dead zone and crashes the entire Builder.
    const hasRealBrandLogo = Boolean(
        data.global_header?.logo_image_url
    );
    const customThemeMatchesCurrentLogo = Boolean(
        hasRealBrandLogo
        && globalSelections?.custom_brand_theme?.source_logo_url
        && String(globalSelections.custom_brand_theme.source_logo_url) === String(data.global_header.logo_image_url)
    );
    const brandMatchNeeded = Boolean(
        hasRealBrandLogo
        && logoSyncState === 'logo_changed'
    );
    // A manual theme change with an existing logo is a durable pending
    // logo-to-theme sync state. The backend already persists `theme_changed`,
    // so the active Theme card can offer a retry even after a refresh.
    const logoMatchPending = Boolean(
        hasRealBrandLogo
        && logoSyncState === 'theme_changed'
    );


    useEffect(() => {
        // Backward compatibility for older paid/trial records that have a
        // matching custom-theme source logo but no explicit sync-state field.
        if (
            logoSyncState === null
            && globalSelections?.primary === 'my-brand'
            && customThemeMatchesCurrentLogo
        ) {
            setLogoSyncState('synced');
            setGlobalSelections((current) => ({
                ...current,
                logo_theme_sync_state: 'synced',
                logo_theme_sync_source: current.logo_theme_sync_source || 'theme_to_logo',
                logo_theme_synced_theme: 'my-brand',
            }));
        }
    }, [logoSyncState, globalSelections?.primary, customThemeMatchesCurrentLogo]);


    useEffect(() => {
        if (globalSelections?.custom_brand_theme) {
            installCustomBrandTheme(globalSelections.custom_brand_theme);
        }
    }, [globalSelections?.custom_brand_theme]);


    useEffect(() => {
        const header = data.global_header;
        if (!header || logoReplacementStarted) return;

        const logoUrl = typeof header.logo_image_url === 'string' ? header.logo_image_url.trim() : '';
        const isPlaceholder = !logoUrl || logoUrl.includes('your-logo.png');
        if (!isPlaceholder) return;

        const activeThemeKey = globalSelections?.primary || 'midnight';
        if (header.logo_filter_key === activeThemeKey && header.logo_filter !== 'none') return;

        // The stock "Your Logo" artwork is monochrome, so its CSS filter can
        // safely follow the active color family during trial/theme changes.
        // A real uploaded/generated logo is handled separately and never tinted.
        setData('global_header', {
            ...header,
            logo_filter_key: activeThemeKey,
            logo_filter: undefined,
        });
    }, [
        data.global_header?.logo_image_url,
        data.global_header?.logo_filter_key,
        globalSelections?.primary,
        logoReplacementStarted,
    ]);

    useEffect(() => {
        const header = data.global_header;
        const footer = data.global_footer;
        if (!header || !footer) return;

        const rawHeaderLogoUrl = typeof header.logo_image_url === 'string' ? header.logo_image_url.trim() : '';
        const headerLogoUrl = rawHeaderLogoUrl || (trialMode ? '/storage/branding/your-logo.png' : '');
        const isPlaceholder = headerLogoUrl.includes('your-logo.png');
        const headerLogoText = isPlaceholder
            ? 'Your Logo'
            : (header.logo_text || website?.name || 'Your Logo');
        const headerLogoFilter = isPlaceholder ? header.logo_filter : 'none';
        const headerLogoFilterKey = header.logo_filter_key || globalSelections?.primary || 'midnight';

        if (
            footer.logo_image_url === headerLogoUrl &&
            footer.logo_text === headerLogoText &&
            footer.logo_filter === headerLogoFilter &&
            footer.logo_filter_key === headerLogoFilterKey
        ) return;

        setData('global_footer', {
            ...footer,
            logo_image_url: headerLogoUrl,
            logo_text: headerLogoText,
            logo_filter: headerLogoFilter,
            logo_filter_key: headerLogoFilterKey,
        });
    }, [
        data.global_header?.logo_image_url,
        data.global_header?.logo_text,
        data.global_header?.logo_filter,
        data.global_header?.logo_filter_key,
        globalSelections?.primary,
    ]);


    const resolveBlockTheme = (block, index) => {

        // Clean is a website-level light visual system. Ignore legacy per-Spark
        // theme assignments so its rhythm stays deterministic: white/surface.
        if (currentPageStyle !== 'clean' && block.theme && block.theme !== "auto") {
            return block.theme;
        }

        const activeStyle = styleOptions.find((style) => style.key === currentPageStyle) || styleOptions.find((style) => style.key === 'balanced');
        const pattern = activeStyle?.pattern || [
            "primary",
            "white",
            "surface",
            "white"
        ];

        return pattern[index % pattern.length];

    };

    const duplicateBlock = (index) => {

        const blocks = [...data.blocks];

        const duplicated = structuredClone
            ? structuredClone(blocks[index])
            : JSON.parse(JSON.stringify(blocks[index]));

        // keep auto as default if missing
        duplicated.theme = duplicated.theme || "auto";
        duplicated._renderKey = createRenderKey();

        // insert directly below current block
        blocks.splice(index + 1, 0, duplicated);

        setData("blocks", blocks);

    };

    const getCompatibleLayouts = (blockType) => {
        const blogSparkGroup = BLOG_SPARK_GROUPS[blockType];

        // Blog sections use three free visual variants within the same block type.
        // They are always available and do not depend on Spark ownership.
        if (blogSparkGroup) {
            return FREE_BLOG_SPARKS
                .filter((spark) => spark.group === blogSparkGroup)
                .map((spark) => ({
                    id: spark.id,
                    type: blockType,
                    title: spark.title,
                    layoutVariant: spark.payload.layout_variant,
                    description: spark.description,
                    preview: spark.preview,
                    kind: 'blog-variant',
                }));
        }

        const currentBlock = BlockRegistry[blockType];
        const category = currentBlock?.schema?.category;

        if (!category) {
            return [];
        }

        const catalogByKey = new Map(sparkCatalog.map((spark) => [spark.key, spark]));
        const marketplaceByType = new Map(MarketplaceSparkRegistry.map((spark) => [spark.type, spark]));

        // Change Layout is also a discovery surface: show every compatible Spark,
        // not only already-owned layouts. Ownership controls whether it can be
        // applied immediately; preview remains available before purchase.
        return Object.entries(BlockRegistry)
            .filter(([, registryItem]) => registryItem?.schema?.category === category)
            .map(([type, registryItem]) => {
                const catalogItem = catalogByKey.get(type);
                const marketplaceItem = marketplaceByType.get(type);
                const isCurrent = type === blockType;

                return {
                    id: type,
                    type,
                    title: registryItem.schema?.title || type.replaceAll('_', ' '),
                    description: catalogItem?.description || registryItem.schema?.description || '',
                    kind: 'block-type',
                    owned: isCurrent || Boolean(catalogItem?.owned),
                    credits: Number(catalogItem?.credits || 0),
                    canPreview: catalogItem?.can_preview !== false,
                    canInstall: isCurrent || catalogItem?.can_install !== false,
                    usageState: catalogItem?.usage_state || null,
                    catalogItem: catalogItem && marketplaceItem ? { ...catalogItem, registry: marketplaceItem } : null,
                };
            });
    };

    const isCurrentLayout = (block, layout) => layout.kind === 'blog-variant'
        ? (block.layout_variant || BlockRegistry[block.type]?.schema?.defaults?.layout_variant) === layout.layoutVariant
        : block.type === layout.type;

    const unlockLayoutSpark = async (layout) => {
        if (!layout?.type || layout.owned || layoutBusyKey) return;

        setLayoutBusyKey(layout.type);
        try {
            const { data: responseData } = await axios.post(`/sparks/${layout.type}/unlock`);
            setSparkCatalog((current) => current.map((spark) => spark.key === layout.type ? { ...spark, owned: true } : spark));
            setLayoutPreview((current) => current?.type === layout.type ? { ...current, owned: true, catalogItem: { ...current.catalogItem, owned: true } } : current);
            if (typeof responseData?.credit_balance !== 'undefined') {
                setCreditBalance(responseData.credit_balance);
            }
            showCosmicNotification({
                title: Number(layout.credits || 0) === 0 ? 'Spark added' : 'Spark purchased',
                message: responseData?.message || `${layout.title} is now available in Change Layout.`,
                tone: 'success',
            });
        } catch (error) {
            showCosmicNotification({
                title: 'Could not add Spark',
                message: error.response?.data?.message || 'Please try again.',
                tone: 'error',
            });
        } finally {
            setLayoutBusyKey(null);
        }
    };

    const changeBlockLayout = (index, layout) => {
        const currentBlock = data.blocks[index];

        if (!currentBlock || isCurrentLayout(currentBlock, layout)) {
            setLayoutMenu(null);
            return;
        }

        if (layout.kind !== 'blog-variant' && !layout.owned) {
            showCosmicNotification({ title: 'Purchase this Spark first', message: 'Preview or buy this layout before applying it to the page.', tone: 'warning' });
            return;
        }

        if (layout.kind === 'blog-variant') {
            setLayoutApplying(index);
            const blocks = [...data.blocks];
            blocks[index] = {
                ...currentBlock,
                layout_variant: layout.layoutVariant,
                _renderKey: createRenderKey(),
            };

            setData('blocks', blocks);
            setLayoutMenu(null);
            window.setTimeout(() => setLayoutApplying((activeIndex) => activeIndex === index ? null : activeIndex), 450);
            return;
        }

        const nextLayout = BlockRegistry[layout.type];
        const defaults = nextLayout?.schema?.defaults;

        if (!defaults) {
            setLayoutMenu(null);
            return;
        }

        // Keep only content keys that both layouts understand. This retains the
        // shared copy/CTA fields without carrying incompatible layout-specific data.
        const sharedContent = Object.keys(defaults).reduce((preserved, key) => {
            if (key !== 'type' && key !== 'theme' && Object.hasOwn(currentBlock, key)) {
                preserved[key] = currentBlock[key];
            }

            return preserved;
        }, {});

        const blocks = [...data.blocks];
        blocks[index] = {
            ...defaults,
            ...sharedContent,
            type: layout.type,
            theme: currentBlock.theme || 'auto',
            _renderKey: createRenderKey(),
        };

        setData('blocks', blocks);
        setLayoutMenu(null);
    };


    const firstBlock = data.blocks?.[0] || null;
    const firstBlockType = String(firstBlock?.type || '').toLowerCase();
    const firstBlockIsBanner = Boolean(firstBlock) && (
        firstBlockType === 'hero' ||
        firstBlockType.includes('hero') ||
        firstBlockType.includes('banner') ||
        (customWebsiteMode && firstBlockType === 'luna_custom_section' && String(firstBlock?.media_position || '').toLowerCase() === 'background')
    );
    const firstBlockResolvedTheme = firstBlock ? resolveBlockTheme(firstBlock, 0) : null;
    const activePrimaryThemeKey = String(globalSelections?.primary || 'midnight').toLowerCase();
    const normalizedPageStyle = ['balanced','clean','premium'].includes(String(currentPageStyle || '').toLowerCase()) ? String(currentPageStyle).toLowerCase() : 'balanced';
    const overlayThemeBlocked = ['stone', 'white'].includes(activePrimaryThemeKey);
    const overlayStyleBlocked = normalizedPageStyle === 'clean';
    const overlayHeaderCompatible = customWebsiteMode
        ? true
        : (['premium', 'balanced'].includes(normalizedPageStyle) && !overlayThemeBlocked);
    const overlayCompatibilityMessage = overlayStyleBlocked
        ? 'Overlay Header is available with Balanced or Premium page styles.'
        : overlayThemeBlocked
            ? 'Overlay Header isn’t compatible with the current theme. Choose another theme to enable it.'
            : 'Overlay Header is available with Balanced or Premium page styles.';
    const overlayHeaderActive = Boolean(data.global_header?.overlay_header_on_banner && firstBlockIsBanner && overlayHeaderCompatible);
    const overlayHeaderRef = useRef(null);
    const [overlayHeaderHeight, setOverlayHeaderHeight] = useState(80);

    useEffect(() => {
        if (!overlayHeaderActive) {
            setOverlayHeaderHeight(0);
            return undefined;
        }

        const headerShell = overlayHeaderRef.current;
        if (!headerShell) return undefined;

        const syncOverlayHeaderHeight = () => {
            const nextHeight = Math.ceil(headerShell.getBoundingClientRect().height || 0);
            if (nextHeight > 0) {
                setOverlayHeaderHeight((currentHeight) => currentHeight === nextHeight ? currentHeight : nextHeight);
            }
        };

        syncOverlayHeaderHeight();

        const observer = typeof ResizeObserver !== 'undefined'
            ? new ResizeObserver(syncOverlayHeaderHeight)
            : null;
        observer?.observe(headerShell);
        window.addEventListener('resize', syncOverlayHeaderHeight);

        return () => {
            observer?.disconnect();
            window.removeEventListener('resize', syncOverlayHeaderHeight);
        };
    }, [overlayHeaderActive, data.global_header?.type, data.global_header?.logo_height, data.global_header?.menu?.length]);

    // Overlay headers are contrast-aware instead of forcing one white treatment.
    // We read the first Spark's semantic surface plus whether it is media-led.
    // Dark/image heroes use light navigation/logo; light/surface heroes keep the
    // original logo and dark navigation. CTA prefers the site's primary brand
    // color and only falls back to a white surface when primary would disappear
    // into a same-primary hero.
    const firstResolvedThemeKey = String(firstBlockResolvedTheme || '').toLowerCase();
    // Header contrast is based on the *actual background surface* of the first
    // Spark. A normal content/side image must not turn a Clean white hero into
    // a light-on-light overlay header.
    const firstBlockUsesBackgroundMediaType = Boolean(firstBlock && (
        firstBlockType.includes('background')
        || firstBlockType.includes('parallax')
        || firstBlockType.includes('slider')
        || firstBlockType === 'image_cta_banner'
    ));
    const firstBlockHasHeroMedia = Boolean(firstBlock && (
        // Explicit section-level background fields always count.
        firstBlock?.background_image_url
        || firstBlock?.background_url
        || firstBlock?.backgroundImage
        // video_url/poster_image_url are content fields on split heroes such as
        // Hero Video Style, so only treat them as background media when the
        // Spark itself is explicitly a background/parallax/slider variant.
        || (firstBlockUsesBackgroundMediaType && (
            firstBlock?.poster_image_url
            || firstBlock?.video_url
            || firstBlock?.video_src
        ))
        || (firstBlockType.includes('slider') && Array.isArray(firstBlock?.slides) && firstBlock.slides.some((slide) => slide?.image_url || slide?.image || slide?.background_image || slide?.background_image_url))
    ));

    const overlaySurfaceThemeKey = firstResolvedThemeKey === 'primary' || firstResolvedThemeKey === 'accent'
        ? activePrimaryThemeKey
        : firstResolvedThemeKey === 'surface'
            ? 'stone'
            : (firstResolvedThemeKey || activePrimaryThemeKey);
    const overlaySurfacePalette = colorFamilies[overlaySurfaceThemeKey]?.palette || {};
    const overlaySurfaceHex = String(overlaySurfacePalette.background || '#243447');
    useEffect(() => {
        if (!customSparkBusy) return undefined;
        const timer = window.setInterval(() => {
            setCustomSparkProgress((current) => {
                if (current >= 89) return current;
                const increment = current < 35 ? 3 : current < 70 ? 2 : 1;
                return Math.min(89, current + increment);
            });
        }, 190);
        return () => window.clearInterval(timer);
    }, [customSparkBusy]);

    const overlayHexLuminance = (hex) => {
        const normalized = String(hex || '').replace('#', '');
        if (!/^[0-9a-f]{6}$/i.test(normalized)) return 0.25;
        const channels = [0, 2, 4].map((offset) => parseInt(normalized.slice(offset, offset + 2), 16) / 255)
            .map((value) => value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
        return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
    };
    // Final Page Style contract: overlay-header contrast is deterministic.
    // Premium/Balanced use a white header treatment when overlay is enabled,
    // except the intentionally light Warm Stone / Studio White theme families.
    // Clean (and every non-overlay state) keeps the normal dark/original header.
    const overlayUsesPremiumLightHeader = Boolean(overlayHeaderActive && overlayHeaderCompatible && (firstBlockHasHeroMedia || overlayHexLuminance(overlaySurfaceHex) < 0.46));
    const overlayHeaderTone = overlayUsesPremiumLightHeader ? 'light' : 'dark';
    const overlayLogoLight = overlayUsesPremiumLightHeader;
    // Premium overlay headers use the active theme's gradient for the CTA.
    // This keeps the nav/logo high-contrast while preventing the whole header
    // from becoming a flat white strip over cinematic hero media.
    const overlayCtaTreatment = overlayHeaderActive ? 'gradient' : 'primary';

    const generateCustomSparkFromScreenshot = async () => {
        if (!website?.id) return;
        const prompt = customSparkInstructions.trim();
        if (!customSparkFile && prompt.length < 10) {
            setCustomSparkError('Describe the website you want, or upload a full-page reference screenshot.');
            return;
        }

        setCustomSparkBusy(true);
        setCustomSparkError('');
        setCustomSparkProgress(1);
        setCustomSparkStage('Queueing Cosmic AI build…');

        const applyBuildResult = (response) => {
            const stamp = Date.now();
            const rebuilt = (response.blocks || []).map((block,index)=>({
                ...block,
                _renderKey:`custom-fullpage-${stamp}-${index}`,
            }));

            // Successful Full Page Build replaces the current body.
            setData('blocks', normalizeRenderKeys(rebuilt));

            if (response?.shell?.header_detected && response?.shell?.header) {
                const shellHeader = response.shell.header;
                setData('global_header', {
                    ...(data.global_header || {}),
                    custom_shell_mode: true,
                    overlay_header_on_banner: Boolean(shellHeader.overlay),
                    custom_style: {
                        ...((data.global_header || {}).custom_style || {}),
                        background_color: shellHeader.background_color || (shellHeader.overlay ? 'transparent' : '#ffffff'),
                        text_color: shellHeader.text_color || (shellHeader.overlay ? '#ffffff' : '#1f2937'),
                        nav_color: shellHeader.nav_color || shellHeader.text_color || (shellHeader.overlay ? '#ffffff' : '#1f2937'),
                        height: Number(shellHeader.height || 78),
                        padding_x: Number(shellHeader.padding_x || 56),
                        content_max_width: Number(shellHeader.content_max_width || 1528),
                        logo_tone: shellHeader.logo_tone || (shellHeader.overlay ? 'light' : 'dark'),
                        nav_size: Number(shellHeader.nav_size || 14),
                        nav_gap: Number(shellHeader.nav_gap || 30),
                        phone_color: shellHeader.phone_color || shellHeader.text_color || '#ffffff',
                        cta_background: shellHeader.cta_background || '#2F80FF',
                        cta_color: shellHeader.cta_color || '#ffffff',
                        cta_radius: Number(shellHeader.cta_radius || 8),
                    },
                    ...(Number(shellHeader.logo_height || 0) > 0 ? { logo_height:Number(shellHeader.logo_height) } : {}),
                    phone_enabled: Boolean(shellHeader.phone_enabled),
                    phone_text: String(shellHeader.phone_text || ''),
                    ...(String(shellHeader.cta_label || '').trim() ? { cta_label:String(shellHeader.cta_label).trim() } : {}),
                    ...(String(shellHeader.cta_url || '').trim() ? { cta_url:String(shellHeader.cta_url).trim() } : {}),
                    ...(Array.isArray(shellHeader.menu) && shellHeader.menu.length ? {
                        menu:shellHeader.menu.slice(0,8).map(item=>({
                            label:String(item.label||'').trim(),
                            url:item.url||'#',
                        })).filter(item=>item.label)
                    } : {}),
                });
            }

            if (response?.shell?.footer_detected && response?.shell?.footer) {
                const shellFooter = response.shell.footer;
                setData('global_footer', {
                    ...(data.global_footer || {}),
                    custom_shell_mode: true,
                    mega_enabled: true,
                    custom_style: {
                        ...((data.global_footer || {}).custom_style || {}),
                        background_color: shellFooter.background_color || '#071a33',
                        text_color: shellFooter.text_color || '#ffffff',
                        muted_color: shellFooter.muted_color || '#b8c4d6',
                        logo_tone: shellFooter.logo_tone || 'light',
                    },
                    copyright: shellFooter.copyright || data.global_footer?.copyright,
                    mega_footer: {
                        ...(data.global_footer?.mega_footer || {}),
                        enabled: true,
                        tagline: shellFooter.tagline || data.global_footer?.mega_footer?.tagline || '',
                        primary_label: shellFooter.cta_label || data.global_footer?.mega_footer?.primary_label || 'Get in touch',
                        primary_url: shellFooter.cta_url || data.global_footer?.mega_footer?.primary_url || '#contact',
                        columns: Array.isArray(shellFooter.columns)
                            ? shellFooter.columns.slice(0,4)
                            : (data.global_footer?.mega_footer?.columns || []),
                    },
                });
            }

            if (response.credits !== undefined) setCreditBalance(Number(response.credits));
            return rebuilt;
        };

        try {
            const form = new FormData();
            form.append('instructions', prompt || 'Reconstruct this full-page website screenshot faithfully as an editable responsive landing page.');
            if (customSparkFile) form.append('screenshot', customSparkFile);

            const { data: queued } = await axios.post(
                `/websites/${website.id}/custom-builds`,
                form,
                { headers: { 'Content-Type': 'multipart/form-data' } }
            );

            const buildId = queued?.build_id;
            if (!buildId) throw new Error('Cosmic AI did not return a build session.');

            updateCustomBuildProgress(queued.progress || 1);
            setCustomSparkStage(queued.stage || 'Queued for Cosmic AI…');

            const startedAt = Date.now();
            const maxWaitMs = 12 * 60 * 1000;
            let completed = null;

            while (Date.now() - startedAt < maxWaitMs) {
                await new Promise(resolve=>window.setTimeout(resolve, 1800));
                const { data: state } = await axios.get(`/websites/${website.id}/custom-builds/${encodeURIComponent(buildId)}`);

                updateCustomBuildProgress(state?.progress || 1);
                setCustomSparkStage(state?.stage || 'Cosmic AI is working…');

                if (state?.status === 'completed') {
                    completed = state?.result || null;
                    break;
                }
                if (state?.status === 'failed') {
                    throw new Error(state?.message || 'Cosmic AI could not finish this page.');
                }
            }

            if (!completed) {
                throw new Error('This build is still taking longer than expected. The background worker can continue, but this Builder session stopped waiting.');
            }

            const rebuilt = applyBuildResult(completed);
            updateCustomBuildProgress(100, true);
            setCustomSparkStage('Your editable page is ready.');

            showCosmicNotification({
                title:`Cosmic AI rebuilt ${rebuilt.length} sections`,
                message:completed?.visual_source === 'uploaded_screenshot'
                    ? 'Your full-page reference was segmented into editable Custom Sparks.'
                    : 'Cosmic AI privately designed a visual blueprint first, then reconstructed it into editable Custom Sparks.',
                tone:'success',
                mode:'toast',
                duration:5200,
            });

            await new Promise(resolve=>window.setTimeout(resolve,350));
            setCustomSparkOpen(false);
            setCustomSparkFile(null);
            setCustomSparkInstructions('');
            setCustomSparkStage('');
            setCustomSparkProgress(0);
        } catch (error) {
            const message =
                error?.response?.data?.message ||
                error?.response?.data?.errors?.instructions?.[0] ||
                error?.response?.data?.errors?.screenshot?.[0] ||
                error?.message ||
                'Unable to rebuild this page with Cosmic AI.';
            setCustomSparkError(message);
            setCustomSparkStage('');
            setCustomSparkProgress(0);
        } finally {
            setCustomSparkBusy(false);
        }
    };

    const openCustomSparkLibrary = async () => {
        if (!website?.id || customSparkLibraryBusy) return;
        setCustomSparkLibraryBusy(true);
        try {
            const { data: response } = await axios.get(`/websites/${website.id}/custom-sparks/saved-library`);
            setCustomSparkLibrary(response.sparks || []);
            setCustomSparkLibraryOpen(true);
        } catch (error) {
            showCosmicNotification({title:'Unable to load saved Sparks',message:error?.response?.data?.message || 'Please try again.',tone:'error',mode:'toast',duration:4200});
        } finally { setCustomSparkLibraryBusy(false); }
    };

    const duplicateSavedCustomSpark = async (spark) => {
        if (!spark?.key || customSparkLibraryBusy) return;
        setCustomSparkLibraryBusy(true);
        try {
            const { data: response } = await axios.post(`/websites/${website.id}/custom-sparks/by-key/${encodeURIComponent(spark.key)}/duplicate`);
            if (response.block) {
                setData('blocks', normalizeRenderKeys([...(data.blocks || []), response.block]));
                setSavedCustomSparkKeys((current)=>{const next=new Set(current);next.add(response.block.custom_spark_key);return next;});
            }
            setCustomSparkLibraryOpen(false);
            showCosmicNotification({title:'Saved Spark installed',message:'The saved Custom Spark was installed at the bottom of this page.',tone:'success',mode:'toast',duration:3600});
        } catch (error) {
            showCosmicNotification({title:'Unable to add Spark',message:error?.response?.data?.message || 'Please try again.',tone:'error',mode:'toast',duration:4200});
        } finally { setCustomSparkLibraryBusy(false); }
    };

    const saveCustomSpark = async (block) => {
        if (!block?.custom_spark_key || !website?.id || savingCustomSparkKey) return;
        setSavingCustomSparkKey(block.custom_spark_key);
        try {
            const { data: response } = await axios.post(`/websites/${website.id}/custom-sparks/by-key/${encodeURIComponent(block.custom_spark_key)}/save`);
            if (response.credits !== undefined) setCreditBalance(Number(response.credits));
            setSavedCustomSparkKeys((current) => { const next = new Set(current); next.add(block.custom_spark_key); return next; });
            setData('blocks', normalizeRenderKeys((data.blocks || []).map((candidate) => candidate.custom_spark_key === block.custom_spark_key ? { ...candidate, custom_spark_saved:true } : candidate)));
            showCosmicNotification({
                title: response.already_saved ? 'Spark already saved' : 'Custom Spark saved',
                message: response.already_saved ? 'This Spark is already permanent and ready to publish.' : '50 credits used. This Spark is now permanent and ready for normal publishing/export.',
                tone: 'success',
                mode: 'toast',
                duration: 4200,
            });
            setCosmicAiChat((current) => current.key === block.custom_spark_key ? {
                ...current,
                messages: response.spark?.metadata?.conversation || current.messages,
                saved: true,
            } : current);
        } catch (error) {
            showCosmicNotification({
                title: 'Unable to save Spark',
                message: error?.response?.data?.errors?.credits?.[0] || error?.response?.data?.message || 'Could not save this Custom Spark.',
                tone: 'error',
                mode: 'toast',
                duration: 5200,
            });
        } finally {
            setSavingCustomSparkKey(null);
        }
    };

    const openCosmicAiForSpark = async (block, index, target = null) => {
        if (!block?.custom_spark_key || !website?.id) return;
        setCosmicAiError('');
        setCosmicAiPrompt('');
        setCosmicAiAsset(null);
        const targetScope = target?.scope === 'element' ? 'element' : 'section';
        const targetKey = targetScope === 'element' ? String(target?.key || '') : '';
        setCosmicAiChat({ open:true, blockIndex:index, key:block.custom_spark_key, name:targetScope==='element' ? `${targetKey || 'Element'} · ${block.heading || 'Custom Spark'}` : (block.heading || 'Custom Spark'), messages:[], qa:null, saved:false, targetScope, targetKey });
        try {
            const { data: response } = await axios.get(`/websites/${website.id}/custom-sparks/by-key/${encodeURIComponent(block.custom_spark_key)}/chat`);
            setCosmicAiChat((current) => ({ ...current, messages: response.messages || [], qa: response.qa || null, name: response.spark?.name || current.name, saved:Boolean(response.spark?.metadata?.saved), revisions:response.revisions || [] }));
        } catch (error) {
            setCosmicAiError(error?.response?.data?.message || 'Could not load this Spark conversation. You can still try a new message.');
        }
    };

    const undoCosmicAiSpark = async () => {
        const index = cosmicAiChat.blockIndex;
        const currentBlock = data.blocks?.[index];
        if (!currentBlock || cosmicAiBusy || !cosmicAiChat.key) return;
        setCosmicAiBusy(true);
        setCosmicAiError('');
        try {
            const { data: response } = await axios.post(`/websites/${website.id}/custom-sparks/by-key/${encodeURIComponent(cosmicAiChat.key)}/undo`);
            if (response.block) {
                setData('blocks', normalizeRenderKeys((data.blocks || []).map((candidate,candidateIndex)=>candidateIndex===index?{...response.block,_renderKey:currentBlock._renderKey}:candidate)));
            }
            setCosmicAiChat((current)=>({
                ...current,
                messages:response.messages || current.messages,
                revisions:(current.revisions || []).slice(1),
            }));
            showCosmicNotification({title:'Previous Spark version restored',message:'Only this Custom Spark was rolled back.',tone:'success',mode:'toast',duration:3200});
        } catch (error) {
            setCosmicAiError(error?.response?.data?.errors?.revision?.[0] || error?.response?.data?.message || 'There is no earlier revision to restore.');
        } finally {
            setCosmicAiBusy(false);
        }
    };

    const sendCosmicAiMessage = async () => {
        const prompt = cosmicAiPrompt.trim();
        const index = cosmicAiChat.blockIndex;
        const currentBlock = data.blocks?.[index];
        if (!prompt || !currentBlock || cosmicAiBusy || !cosmicAiChat.key) return;
        setCosmicAiBusy(true);
        setCosmicAiError('');
        setCosmicAiChat((current) => ({ ...current, messages:[...(current.messages || []), { role:'user', text:prompt, pending:true }] }));
        setCosmicAiPrompt('');
        try {
            const form = new FormData();
            form.append('prompt', prompt);
            form.append('block', JSON.stringify(stripClientBlockFields([currentBlock])[0]));
            form.append('target_scope', cosmicAiChat.targetScope || 'section');
            if (cosmicAiChat.targetKey) form.append('target_key', cosmicAiChat.targetKey);
            if (cosmicAiAsset) form.append('asset', cosmicAiAsset);
            const { data: response } = await axios.post(`/websites/${website.id}/custom-sparks/by-key/${encodeURIComponent(cosmicAiChat.key)}/chat`, form, { headers:{ 'Content-Type':'multipart/form-data' } });
            if (response.action === 'update' && response.block) {
                setData('blocks', normalizeRenderKeys((data.blocks || []).map((candidate, candidateIndex) => candidateIndex === index ? { ...response.block, _renderKey: currentBlock._renderKey } : candidate)));
            }
            setCosmicAiChat((current) => ({ ...current, messages: response.messages || current.messages, revisions: response.action === 'update' ? [{id:`local-${Date.now()}`,reason:'Before Cosmic AI chat edit',at:new Date().toISOString()}, ...(current.revisions||[])] : (current.revisions||[]) }));
            setCosmicAiAsset(null);
            showCosmicNotification({ title: response.action === 'update' ? 'Cosmic AI updated this Spark' : 'Cosmic AI replied', message: response.reply || 'Done.', tone:'success', mode:'toast', duration:3600 });
        } catch (error) {
            setCosmicAiError(error?.response?.data?.message || error?.response?.data?.errors?.prompt?.[0] || error?.message || 'Cosmic AI could not complete that request.');
            setCosmicAiChat((current) => ({ ...current, messages:(current.messages || []).filter((message) => !message.pending) }));
            setCosmicAiPrompt(prompt);
        } finally {
            setCosmicAiBusy(false);
        }
    };

    const openLunaChat = (scope = { type:'page', blockIndex:null, label:'Whole Page' }) => {
        setLunaScope(scope);
        setPageAiError('');
        setPageAiPrompt('');
        setLunaChatOpen(true);
    };

    const undoLastLunaChange = () => {
        const snapshot = lunaUndoStack[0];
        if (!snapshot) return;
        setData('blocks', normalizeRenderKeys(snapshot.blocks || []));
        setData('global_header', snapshot.header || data.global_header);
        setData('global_footer', snapshot.footer || data.global_footer);
        if (snapshot.theme) {
            setGlobalSelections(snapshot.theme);
            setHasUnsavedTheme(true);
        }
        setLunaUndoStack((stack)=>stack.slice(1));
        setLunaMessages((messages)=>[...messages,{role:'assistant',text:'Undone — I restored the previous design.'}]);
    };

    const sendPageAiRequest = async () => {
        const prompt = pageAiPrompt.trim();
        if (!prompt || pageAiBusy || !website?.id) return;
        setPageAiBusy(true);
        setPageAiError('');
        setLunaMessages((messages)=>[...messages,{role:'user',text:prompt,scope:lunaScope.label}]);
        try {
            const form = new FormData();
            form.append('prompt', prompt);
            form.append('blocks', JSON.stringify(stripClientBlockFields(data.blocks || [])));
            form.append('header', JSON.stringify(data.global_header || {}));
            form.append('footer', JSON.stringify(data.global_footer || {}));
            form.append('theme', JSON.stringify(globalSelections || {}));
            form.append('target_scope', lunaScope.type === 'section' ? 'section' : 'page');
            if (lunaScope.type === 'section' && Number.isInteger(lunaScope.blockIndex)) {
                form.append('target_index', String(lunaScope.blockIndex));
            }
            const { data: response } = await axios.post(`/websites/${website.id}/custom-page-ai`, form, { headers:{'Content-Type':'multipart/form-data'} });
            setLunaUndoStack((stack)=>[{
                blocks: stripClientBlockFields(data.blocks || []),
                header: data.global_header || {},
                footer: data.global_footer || {},
                theme: globalSelections || {},
            }, ...stack].slice(0,10));
            if (Array.isArray(response.blocks)) setData('blocks', normalizeRenderKeys(response.blocks.map((block,index)=>({...block,_renderKey:data.blocks?.[index]?._renderKey || createRenderKey()}))));
            if (response.header) setData('global_header', response.header);
            if (response.footer) setData('global_footer', response.footer);
            if (response.theme_key) {
                setGlobalSelections((current)=>({...current,primary:response.theme_key}));
                setHasUnsavedTheme(true);
            }
            setPageAiPrompt('');
            setPageAiOpen(false);
            setLunaMessages((messages)=>[...messages,{role:'assistant',text:response.reply || 'Done.'}]);
            showCosmicNotification({title:'Luna updated the design',message:response.reply || 'Done.',tone:'success',mode:'toast',duration:3200});
        } catch (error) {
            const message = error?.response?.data?.message || error?.response?.data?.errors?.prompt?.[0] || error?.message || 'Luna could not update the page.';
            setPageAiError(message);
            setLunaMessages((messages)=>[...messages,{role:'assistant',text:`I couldn't complete that change: ${message}`}]);
        } finally {
            setPageAiBusy(false);
        }
    };

    const renderBlock = (block, index) => {

        const resolvedTheme = resolveBlockTheme(block, index);

        const blockProps = {

            block: {
                ...block,
                resolvedTheme
            },

            globalTheme: { ...(globalSelections || {}), pageStyle: currentPageStyle },

            onUpdate: (fields) => updateBlockContent(index, fields),
            onOpenCosmicAI: undefined,
            onSaveCustomSpark: customWebsiteMode && block?.custom_spark_key && block?.custom_spark_saved !== true && !savedCustomSparkKeys.has(block.custom_spark_key) ? () => saveCustomSpark(block) : undefined,
            savingCustomSpark: savingCustomSparkKey === block?.custom_spark_key,
            commerce,
            contentWorkspace,
            builderMode: true,

            blockIndex: index,
            blogPosts,
            blogWebsiteId: website.id,
            blogPageId: page.id,
            onBlogPostCreated: (post) => setBlogPosts((currentPosts) => [post, ...currentPosts]),
            onBlogPostUpdated: (post) => setBlogPosts((currentPosts) => currentPosts.map((currentPost) => currentPost.id === post.id ? post : currentPost)),
            onBlogPostDeleted: (postId) => setBlogPosts((currentPosts) => currentPosts.filter((post) => post.id !== postId)),

        };


        const registryItem = BlockRegistry[block.type];

        if (registryItem) {
            const Component = registryItem.component;

            return (
                <Component
                    key={block._renderKey || index}
                    {...blockProps}
                />
            );
        }

        switch (block.type) {

            case "hero":

                const heroClass =
                    globalSelections?.primary === "emerald"
                        ? "bg-emerald-900"
                        : "bg-slate-900";

                return (
                    <section
                        key={index}
                        className={`py-20 px-8 text-center text-white shadow-xl w-full ${heroClass}`}
                    >
                        <h1 className="text-5xl font-extrabold mb-4">
                            {block.heading}
                        </h1>

                        <p className="text-xl text-slate-200">
                            {block.subheading}
                        </p>

                    </section>
                );

            case "content":

                return (
                    <section
                        key={index}
                        className="py-10 px-8 bg-white border-b border-slate-200 w-full"
                    >
                        <p className="text-lg text-slate-700 leading-relaxed">
                            {block.text}
                        </p>
                    </section>
                );

            default:

                return (
                    <div
                        key={index}
                        className="p-4 text-center text-xs text-red-400"
                    >
                        Unknown Block: {block.type}
                    </div>
                );

        }

    };
    
    return (
        <>
            {customSparkOpen && <div className="fixed inset-0 z-[980] flex items-center justify-center p-4 sm:p-6">
  <button type="button" className="absolute inset-0 bg-black/75 backdrop-blur-sm" onClick={()=>!customSparkBusy&&setCustomSparkOpen(false)} aria-label="Close Cosmic AI builder"/>
  <section role="dialog" aria-modal="true" className="relative z-10 w-full max-w-2xl overflow-hidden rounded-3xl border border-white/10 bg-[#111116] text-white shadow-2xl shadow-black/70">
    {!customSparkBusy?<><header className="border-b border-white/10 px-6 py-5">
      <p className="text-[10px] font-bold uppercase tracking-[0.22em] text-emerald-300">Custom Website · Cosmic AI</p>
      <h3 className="mt-1 text-2xl font-semibold">✨ Build or Rebuild Full Page</h3>
      <p className="mt-2 text-sm leading-6 text-slate-400">Describe the page you want, or upload a complete landing-page screenshot. Cosmic AI will rebuild the whole current page as separate editable sections.</p>
    </header>

    <div className="p-6">
      <label className="block">
        <span className="text-xs font-semibold text-slate-300">Website brief <span className="text-slate-500">· optional when a screenshot is supplied</span></span>
        <textarea
          value={customSparkInstructions}
          onChange={e=>setCustomSparkInstructions(e.target.value)}
          rows={5}
          placeholder="e.g. Build a premium residential and commercial glass company website for Summit Line Glass. Use deep navy, architectural photography, project showcases, testimonials, a quote estimator and a strong enquiry CTA."
          className="mt-2 w-full rounded-xl border border-white/10 bg-black/25 px-3 py-3 text-sm leading-5"
        />
      </label>

      <label className="mt-4 flex min-h-28 cursor-pointer items-center justify-center rounded-2xl border border-dashed border-white/15 bg-white/[0.025] p-4 text-center text-sm text-slate-400 transition hover:border-emerald-300/40">
        <input type="file" accept="image/png,image/jpeg,image/webp" className="hidden" onChange={e=>setCustomSparkFile(e.target.files?.[0]||null)}/>
        {customSparkFile
          ? <span className="text-emerald-200"><b>Full-page reference:</b> {customSparkFile.name}</span>
          : <span><b className="text-slate-300">Full-page screenshot</b> · optional<br/><small>Without one, Cosmic AI privately designs a visual blueprint first, then reconstructs it.</small></span>}
      </label>

      <div className="mt-4 rounded-xl border border-violet-400/15 bg-violet-400/[0.06] p-4 text-xs leading-5 text-slate-400">
        <b className="text-violet-200">This replaces the current page body.</b> Header and footer are reconstructed as global shell elements when detected. Existing Templates and registered Sparks are not used as design references.
      </div>

      {customSparkError&&<p className="mt-3 text-sm text-rose-300">{customSparkError}</p>}

      <div className="mt-5 flex justify-end gap-2">
        <button type="button" onClick={()=>setCustomSparkOpen(false)} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">Cancel</button>
        <button
          type="button"
          disabled={!customSparkFile&&customSparkInstructions.trim().length<10}
          onClick={generateCustomSparkFromScreenshot}
          className="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-500 disabled:opacity-40"
        >
          ✨ Build Full Page · Free
        </button>
      </div>
    </div></>:<div className="cosmic-generate-page-progress px-8 py-12 text-center" role="status" aria-live="polite">
      <div className="relative mx-auto h-20 w-20" aria-hidden="true"><div className="cosmic-loading-spinner absolute inset-0 rounded-full"/><div className="absolute inset-[4px] grid place-items-center rounded-full bg-[#17171d] text-2xl text-cyan-300 shadow-lg shadow-violet-950/50">✦</div></div>
      <p className="mt-6 text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300">Cosmic AI</p>
      <h3 className="mt-2 text-2xl font-semibold tracking-tight text-white">Designing & rebuilding your page</h3>
      <p className="mt-3 min-h-6 text-sm text-slate-300">{customSparkStage || 'Understanding your brief…'}</p>
      <div className="mx-auto mt-7 grid max-w-xl grid-cols-2 gap-2 sm:grid-cols-4">
        {(customSparkFile
          ? [{label:'Read visual',threshold:18},{label:'Map regions',threshold:42},{label:'Build Sparks',threshold:72},{label:'Finish page',threshold:94}]
          : [{label:'Design mockup',threshold:18},{label:'Map regions',threshold:42},{label:'Build Sparks',threshold:72},{label:'Finish page',threshold:94}]
        ).map(step=>{const complete=customSparkProgress>=step.threshold;return <div key={step.label} className={`rounded-lg border px-2.5 py-2 text-[10px] font-medium sm:text-xs ${complete?'border-emerald-400/30 bg-emerald-400/10 text-emerald-200':'border-white/10 bg-white/[0.02] text-slate-500'}`}><span>{complete?'✓ ':''}{step.label}</span></div>})}
      </div>
      <div className="mx-auto mt-6 h-2 max-w-xl overflow-hidden rounded-full bg-white/10"><div className="h-full rounded-full bg-gradient-to-r from-violet-500 via-cyan-400 to-emerald-400 transition-[width] duration-700 ease-out" style={{width:`${customSparkProgress}%`}}/></div>
      <div className="mx-auto mt-3 flex max-w-xl items-center justify-between text-xs text-slate-500"><span>Visual-first Custom Build · Free</span><span>{Math.round(customSparkProgress)}%</span></div>
    </div>}
  </section>
</div>}

{customSparkLibraryOpen && <div className="fixed inset-0 z-[10020] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm" onClick={()=>setCustomSparkLibraryOpen(false)}><div className="w-full max-w-2xl rounded-2xl border border-white/10 bg-white p-5 shadow-2xl" onClick={e=>e.stopPropagation()}><div className="flex items-center justify-between"><div><h3 className="text-lg font-black text-slate-900">Saved Custom Sparks</h3><p className="text-xs text-slate-500">Reusable only inside this Custom Website.</p></div><button type="button" onClick={()=>setCustomSparkLibraryOpen(false)} className="h-8 w-8 rounded-lg text-slate-400 hover:bg-slate-100">×</button></div><div className="mt-4 grid max-h-[55vh] gap-3 overflow-auto sm:grid-cols-2">{customSparkLibrary.length?customSparkLibrary.map(spark=><button key={spark.key} type="button" onClick={()=>duplicateSavedCustomSpark(spark)} disabled={customSparkLibraryBusy} className="rounded-xl border border-slate-200 p-4 text-left hover:border-emerald-300 hover:bg-emerald-50/40 disabled:opacity-50"><div className="text-sm font-bold text-slate-900">{spark.name || 'Saved Custom Spark'}</div><div className="mt-1 text-[10px] text-slate-500">Install on this page</div></button>):<div className="col-span-2 rounded-xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">No saved Custom Sparks yet.</div>}</div></div></div>}
            {aiOnlyBuilder && !lunaChatOpen && <button
  type="button"
  onClick={()=>openLunaChat({type:'page',blockIndex:null,label:'Whole Page'})}
  className="fixed bottom-6 right-6 z-[960] inline-flex h-14 items-center gap-2 rounded-full border border-violet-300/30 bg-gradient-to-r from-violet-600 to-indigo-600 px-5 text-sm font-black text-white shadow-2xl shadow-violet-950/40 transition hover:-translate-y-0.5 hover:shadow-violet-950/60"
  title="Ask Luna about this website"
><span className="text-lg" aria-hidden="true">✦</span> Ask Luna</button>}

{aiOnlyBuilder && lunaChatOpen && <div className="fixed bottom-6 right-6 z-[970] flex max-h-[72vh] w-[420px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-violet-300/20 bg-[#111318]/95 text-white shadow-2xl backdrop-blur-xl">
  <div className="flex items-start justify-between border-b border-white/10 px-4 py-3">
    <div>
      <p className="text-[10px] font-black uppercase tracking-[.18em] text-violet-300">✦ Luna · {lunaScope.type==='section'?'Selected Section':'Whole Page'}</p>
      <h3 className="mt-1 max-w-[300px] truncate text-sm font-bold">{lunaScope.label}</h3>
    </div>
    <div className="flex items-center gap-1">
      <button type="button" onClick={undoLastLunaChange} disabled={!lunaUndoStack.length||pageAiBusy} className="rounded-lg border border-white/10 px-2 py-1.5 text-[10px] font-bold text-slate-300 hover:bg-white/10 disabled:opacity-30">↶ Undo</button>
      <button type="button" onClick={()=>setLunaChatOpen(false)} className="h-8 w-8 rounded-lg text-slate-400 hover:bg-white/10 hover:text-white">×</button>
    </div>
  </div>
  <div className="px-4 pt-4">
    <div className="rounded-xl border border-violet-300/10 bg-violet-400/[0.06] px-3 py-2 text-xs leading-5 text-slate-400">
      {lunaScope.type==='section'
        ? 'Luna is focused on this section. Ask for a new layout, video, different style, content, or another section below it.'
        : 'Luna is looking at the whole page. Ask for site-wide design, theme, structure, or content changes.'}
    </div>
  </div>
  <div className="max-h-64 space-y-2 overflow-y-auto px-4 pt-3">
    {lunaMessages.length ? lunaMessages.slice(-12).map((message,i)=><div key={`${i}-${message.text}`} className={`flex ${message.role==='user'?'justify-end':'justify-start'}`}><div className={`max-w-[86%] rounded-2xl px-3 py-2 text-xs leading-5 ${message.role==='user'?'bg-violet-500 text-white':'border border-white/10 bg-white/[0.05] text-slate-200'}`}>{message.text}</div></div>) : <div className="text-xs text-slate-500">Tell Luna what you want to change.</div>}
    {pageAiBusy?<div className="text-xs font-semibold text-violet-300">Luna is working…</div>:null}
  </div>
  <div className="p-4">
    <textarea
      value={pageAiPrompt}
      onChange={e=>setPageAiPrompt(e.target.value)}
      onKeyDown={e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendPageAiRequest();}}}
      rows={4}
      placeholder={lunaScope.type==='section'?'e.g. Change this section into a video hero':'e.g. Make the whole website feel more premium'}
      className="w-full resize-none rounded-xl border border-white/10 bg-black/25 px-3 py-3 text-sm leading-6 outline-none focus:border-violet-300/40"
    />
    {pageAiError?<div className="mt-3 rounded-lg bg-rose-500/10 px-3 py-2 text-xs text-rose-300">{pageAiError}</div>:null}
    <div className="mt-3 flex items-center justify-between">
      {lunaScope.type==='section'
        ? <button type="button" onClick={()=>setLunaScope({type:'page',blockIndex:null,label:'Whole Page'})} className="text-[11px] font-semibold text-slate-500 hover:text-violet-200">Switch to Whole Page</button>
        : <span className="text-[11px] text-slate-600">AI-only Builder</span>}
      <button type="button" onClick={sendPageAiRequest} disabled={pageAiBusy||!pageAiPrompt.trim()} className="rounded-lg bg-violet-500 px-4 py-2 text-xs font-bold text-white hover:bg-violet-400 disabled:opacity-40">{pageAiBusy?'Luna is working…':'Send'}</button>
    </div>
  </div>
</div>}

{!aiOnlyBuilder && pageAiOpen && <div className="fixed inset-0 z-[969] flex items-center justify-center bg-black/55 p-4 backdrop-blur-sm">
  <div className="w-full max-w-xl rounded-2xl border border-violet-300/20 bg-[#111318]/95 p-5 text-white shadow-2xl">
    <div className="flex items-start justify-between gap-4">
      <div>
        <p className="text-[10px] font-black uppercase tracking-[.18em] text-violet-300">✨ Cosmic AI · Whole Page</p>
        <h3 className="mt-1 text-lg font-bold">Ask Cosmic Page</h3>
        <p className="mt-2 text-xs leading-5 text-slate-400">Applies a coordinated change across this Custom Website page. Header/footer are only changed when your request needs them.</p>
      </div>
      <button type="button" onClick={()=>!pageAiBusy&&setPageAiOpen(false)} className="h-8 w-8 rounded-lg text-slate-400 hover:bg-white/10 hover:text-white">×</button>
    </div>
    <textarea value={pageAiPrompt} onChange={e=>setPageAiPrompt(e.target.value)} onKeyDown={e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendPageAiRequest();}}} rows={5} placeholder="e.g. Make all headings 32px, color #333333, reduce vertical spacing, and keep the current layouts." className="mt-4 w-full resize-none rounded-xl border border-white/10 bg-black/25 px-3 py-3 text-sm leading-6 outline-none focus:border-violet-300/40"/>
    {pageAiError?<div className="mt-3 rounded-lg bg-rose-500/10 px-3 py-2 text-xs text-rose-300">{pageAiError}</div>:null}
    <div className="mt-4 flex justify-end gap-2">
      <button type="button" onClick={()=>setPageAiOpen(false)} disabled={pageAiBusy} className="rounded-lg border border-white/10 px-3 py-2 text-xs font-bold text-slate-300 disabled:opacity-40">Cancel</button>
      <button type="button" onClick={sendPageAiRequest} disabled={pageAiBusy||!pageAiPrompt.trim()} className="rounded-lg bg-violet-500 px-4 py-2 text-xs font-bold text-white hover:bg-violet-400 disabled:opacity-40">{pageAiBusy?'Updating page…':'Apply with Cosmic AI'}</button>
    </div>
  </div>
</div>}
            {cosmicAiChat.open && <div className="fixed right-6 top-24 z-[970] flex max-h-[72vh] w-[420px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-emerald-300/20 bg-[#111318]/95 text-white shadow-2xl backdrop-blur-xl"><div className="flex items-start justify-between border-b border-white/10 px-4 py-3"><div><p className="text-[10px] font-black uppercase tracking-[.18em] text-emerald-300">✨ Cosmic AI · {cosmicAiChat.targetScope==='element' ? `Element: ${cosmicAiChat.targetKey || 'selected'}` : 'This Spark only'}</p><h3 className="mt-1 max-w-[300px] truncate text-sm font-bold">{cosmicAiChat.name}</h3>{cosmicAiChat.qa?.score?<><p className="mt-0.5 text-[10px] text-slate-500">Last visual QA: {cosmicAiChat.qa.score}%</p>{cosmicAiChat.qa?.breakdown?<div className="mt-2 grid grid-cols-3 gap-1.5">{Object.entries(cosmicAiChat.qa.breakdown).map(([label,score])=><div key={label} className="rounded-md border border-white/10 bg-white/5 px-2 py-1"><div className="truncate text-[8px] font-bold uppercase tracking-wide text-slate-500">{label}</div><div className="text-[10px] font-black text-emerald-300">{Number(score)||0}%</div></div>)}</div>:null}</>:null}</div><div className="flex items-center gap-1"><button type="button" onClick={undoCosmicAiSpark} disabled={cosmicAiBusy||!(cosmicAiChat.revisions||[]).length} className="rounded-lg border border-white/10 px-2 py-1.5 text-[10px] font-bold text-slate-300 hover:bg-white/10 disabled:opacity-30" title="Restore previous Cosmic AI version">↶ Undo AI</button><button type="button" onClick={()=>setCosmicAiChat((current)=>({...current,open:false}))} className="h-8 w-8 rounded-lg text-slate-400 hover:bg-white/10 hover:text-white">×</button></div></div><div className="min-h-28 flex-1 space-y-3 overflow-y-auto px-4 py-4">{!(cosmicAiChat.messages||[]).length?<div className="rounded-xl border border-dashed border-white/10 p-4 text-xs leading-5 text-slate-400">Ask Cosmic AI to adjust this selected Spark, compare it with the stored reference, or use a new source asset. Other sections will not be changed.</div>:(cosmicAiChat.messages||[]).map((message,i)=><div key={`${message.at||i}-${i}`} className={`flex ${message.role==='user'?'justify-end':'justify-start'}`}><div className={`max-w-[88%] rounded-2xl px-3 py-2 text-xs leading-5 ${message.role==='user'?'bg-emerald-500 text-white':'border border-white/10 bg-white/[0.05] text-slate-200'} ${message.pending?'opacity-60':''}`}>{message.text}</div></div>)}{cosmicAiBusy?<div className="text-xs text-emerald-300">Cosmic AI is working on this Spark…</div>:null}</div>{cosmicAiError?<div className="mx-4 mb-2 rounded-lg bg-rose-500/10 px-3 py-2 text-[11px] text-rose-300">{cosmicAiError}</div>:null}<div className="border-t border-white/10 p-3"><textarea value={cosmicAiPrompt} onChange={e=>setCosmicAiPrompt(e.target.value)} onKeyDown={e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendCosmicAiMessage();}}} rows={3} placeholder="e.g. Move the background image right and reduce the hero height…" className="w-full resize-none rounded-xl border border-white/10 bg-black/25 px-3 py-2 text-xs leading-5 outline-none focus:border-emerald-300/40"/><div className="mt-2 flex items-center justify-between gap-2"><label className="min-w-0 cursor-pointer rounded-lg border border-white/10 px-2.5 py-1.5 text-[10px] font-semibold text-slate-300 hover:bg-white/5"><span className="block max-w-[190px] truncate">📎 {cosmicAiAsset?.name || 'Attach source image'}</span><input type="file" accept="image/png,image/jpeg,image/webp" className="hidden" onChange={e=>setCosmicAiAsset(e.target.files?.[0]||null)}/></label><button type="button" onClick={sendCosmicAiMessage} disabled={cosmicAiBusy||!cosmicAiPrompt.trim()} className="rounded-lg bg-emerald-500 px-3 py-1.5 text-[11px] font-bold text-white disabled:opacity-40">{cosmicAiBusy?'Working…':'Send'}</button></div></div></div>}
            {trialMode && (
                <div className="border-b border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-cyan-50 px-4 py-3 text-center">
                    <p className="text-sm font-semibold text-slate-900">Love what you created?</p>
                    <p className="mt-0.5 text-xs text-slate-600">
                        Buy this website and continue building with Cosmic CMS.{' '}
                        <button
                            type="button"
                            onClick={handleBuyTrialWebsite}
                            disabled={isSaving || trialEmailSaving || trialPurchasePending}
                            className="font-bold text-emerald-700 hover:text-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {trialPurchasePending ? 'Saving…' : 'Buy This Website →'}
                        </button>
                    </p>
                </div>
            )}
            <Head title={`Builder — ${page.title}`} />
            <div className={`cosmic-builder-shell min-h-screen ${trialMode ? 'bg-slate-100 text-slate-900' : 'bg-[#09090b] text-slate-100'}`}>
                <header data-cosmic-builder-header className={`sticky top-0 z-[60] backdrop-blur-xl ${trialMode ? 'border-b border-slate-200 bg-white/95' : 'border-b border-white/10 bg-[#09090b]/95'}`}>
                    <div className={`mx-auto max-w-[1760px] px-4 py-3 sm:px-6 ${trialMode ? 'flex min-h-[76px] flex-wrap items-center justify-between gap-3 xl:grid xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center' : 'grid min-h-[64px] grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-4'}`}>
                        <div className="flex min-w-0 items-center gap-3">
                            {capabilities.canNavigateAway && (
                                <Link
                                    href={route('pages.index', website.id)}
                                    onClick={async (event) => {
                                        if (!hasUnsavedChanges) return;

                                        event.preventDefault();
                                        const shouldLeave = await confirmCosmicAction({
                                            title: 'Leave without saving?',
                                            message: 'You have unsaved Builder changes. They will be lost if you leave this page.',
                                            confirmLabel: 'Leave Builder',
                                            tone: 'error',
                                        });

                                        if (shouldLeave) {
                                            window.location.assign(route('pages.index', website.id));
                                        }
                                    }}
                                    className="inline-flex h-9 shrink-0 items-center gap-2 rounded-lg px-2.5 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400"
                                >
                                    <span aria-hidden="true">←</span>
                                    <span className="hidden sm:inline">Back to Pages</span>
                                </Link>
                            )}

                            <div className={`min-w-0 ${trialMode ? 'border-l border-slate-200 pl-3 sm:pl-4' : 'border-l border-white/10 pl-3 sm:pl-4'}`}>
                                <p className="truncate text-[11px] font-medium text-slate-500">{website.name || 'Cosmic CMS'}</p>
                                <div className="flex min-w-0 items-center gap-2 leading-tight">
                                    <span className={`truncate text-sm font-semibold ${trialMode ? 'text-slate-900' : 'text-white'}`}>{page.title || 'Untitled page'}</span>
                                    <span className="hidden text-[11px] text-slate-600 sm:inline">/{page.slug}</span>
                                </div>
                            </div>

                            {!customWebsiteMode && (trialMode || capabilities.canGenerateAi) && (
                                <PageStyleSelector
                                    pageId={page.id}
                                    currentStyle={currentPageStyle}
                                    suggestions={styleOptions}
                                    blocks={data.blocks}
                                    disabled={isSaving || isPublishing}
                                    trialMode={trialMode}
                                    trialToken={trialToken}
                                    creditBalance={creditBalance}
                                    creditCost={trialMode ? trialActionCosts.page_style : 20}
                                    onApplied={(response) => {
                                        setData('blocks', (response.blocks || []).map((block) => ({
                                            ...block,
                                            theme: 'auto',
                                            _renderKey: createRenderKey(),
                                        })));
                                        const appliedStyle = ['balanced','clean','premium'].includes(String(response.page_style || '').toLowerCase()) ? String(response.page_style).toLowerCase() : 'balanced';
                                        setCurrentPageStyle(appliedStyle);
                                        if (appliedStyle === 'clean' && data.global_header?.overlay_header_on_banner) {
                                            updateHeader({ overlay_header_on_banner: false });
                                            showCosmicNotification({
                                                title: 'Overlay Header turned off',
                                                message: 'Overlay Header is available with Balanced or Premium page styles.',
                                                tone: 'info',
                                            });
                                        }
                                        setStyleOptions(response.suggestions || styleOptions);
                                        setPageStatus(response.page_status || 'draft');
                                        setCreditBalance(response.credit_balance);
                                        setPublishError('');
                                    }}
                                />
                            )}

                            {!aiOnlyBuilder && !customWebsiteMode && !trialMode && capabilities.canGenerateAi && (
                                <button
                                    type="button"
                                    onClick={() => setIsGeneratePageOpen(true)}
                                    className="cosmic-generate-page-trigger hidden h-9 shrink-0 items-center gap-1.5 rounded-lg border border-violet-400/25 bg-violet-500/10 px-3 text-xs font-semibold text-violet-100 transition hover:border-violet-400/40 hover:bg-violet-500/20 focus:outline-none focus:ring-2 focus:ring-violet-400 lg:inline-flex"
                                >
                                    <span className="cosmic-generate-page-icon" aria-hidden="true">✦</span>
                                    Generate Page
                                </button>
                            )}

                            {customWebsiteMode && (
                                <div className="flex items-center gap-2">
                                    <button type="button" onClick={() => setCustomSparkOpen(true)} className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-emerald-300/30 bg-emerald-400/10 px-3 text-xs font-bold text-emerald-100 transition hover:bg-emerald-400/15">
                                        ✨ Build Full Page <span className="rounded bg-black/20 px-1.5 py-0.5 text-[10px]">FREE</span>
                                    </button>
                                    <button type="button" onClick={()=>{setPageAiError('');setPageAiOpen(true)}} className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-violet-300/25 bg-violet-400/10 px-3 text-xs font-bold text-violet-100 transition hover:bg-violet-400/15">
                                        ✨ Ask Cosmic Page
                                    </button>
                                    <button type="button" onClick={openCustomSparkLibrary} disabled={customSparkLibraryBusy} className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-cyan-300/25 bg-cyan-400/10 px-3 text-xs font-bold text-cyan-100 transition hover:bg-cyan-400/15 disabled:opacity-40">
                                        ✦ Saved Sparks
                                    </button>
                                </div>
                            )}

                            {!aiOnlyBuilder && !customWebsiteMode && (capabilities.canGenerateAi || trialMode) && (
                                <button
                                    type="button"
                                    onClick={() => setIsTemplatesOpen(true)}
                                    className="cosmic-templates-trigger hidden h-9 shrink-0 items-center gap-1.5 rounded-lg border border-fuchsia-300/35 bg-gradient-to-r from-violet-500/20 via-fuchsia-500/15 to-cyan-400/10 px-3 text-xs font-bold text-fuchsia-50 shadow-[0_0_20px_rgba(168,85,247,0.12)] transition hover:border-fuchsia-300/60 hover:from-violet-500/30 hover:via-fuchsia-500/25 focus:outline-none focus:ring-2 focus:ring-fuchsia-400 lg:inline-flex"
                                >
                                    <span aria-hidden="true">▣</span>
                                    Templates
                                    <span className="rounded-full bg-fuchsia-300 px-1.5 py-0.5 text-[8px] font-black tracking-wide text-fuchsia-950">NEW</span>
                                </button>
                            )}
                        </div>

                        <div className={`${trialMode ? 'order-2 flex items-center justify-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 xl:justify-self-end' : 'hidden items-center gap-1 rounded-xl border border-white/10 bg-white/[0.035] p-1 xl:flex'}`}>
                            <span title={publishError || saveError || undefined} className={`inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-[11px] font-medium ${isPublishing ? 'text-sky-200' : publishError ? 'text-red-200' : pageStatus === 'published' ? 'cosmic-published-status text-emerald-200' : 'text-amber-200'}`}>
                                <span className={`h-1.5 w-1.5 rounded-full ${isPublishing ? 'animate-pulse bg-sky-300' : publishError ? 'bg-red-300' : pageStatus === 'published' ? 'bg-emerald-300' : 'bg-amber-300'}`} />
                                {isPublishing ? 'Publishing…' : publishError ? 'Publish failed' : pageStatus === 'published' ? 'Published' : 'Draft'}
                            </span>
                            <span className={`h-4 w-px ${trialMode ? 'bg-slate-200' : 'bg-white/10'}`} aria-hidden="true" />
                            <span className={`inline-flex h-8 items-center rounded-lg px-2.5 text-[11px] font-medium ${trialMode ? 'text-slate-600' : 'text-slate-400'}`}>
                                {data.blocks.length} Sparks
                            </span>

                        </div>

                        <div className={`flex min-w-0 items-center justify-end gap-2 ${trialMode ? 'order-3 ml-0 flex w-full flex-wrap rounded-2xl border border-slate-200/80 bg-white/90 p-1.5 shadow-sm backdrop-blur xl:col-span-2 xl:flex-nowrap' : ''}`}>
                            {trialMode ? (
                                <span title="Guest Cosmic Credits" className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 text-xs font-extrabold text-amber-800">
                                    <span aria-hidden="true">⚡</span><span>{effectiveCreditBalance.toLocaleString()}</span><span className="hidden font-semibold sm:inline">Guest Credits</span>
                                </span>
                            ) : (
                                <CreditBalanceBadge
                                    balance={creditBalance}
                                    className="cosmic-builder-credit h-9 px-3"
                                />
                            )}

                            {!customWebsiteMode && (
                            <button
                                type="button"
                                onClick={() => {
                                    if (!overlayHeaderCompatible) {
                                        showCosmicNotification({
                                            title: 'Overlay Header unavailable',
                                            message: overlayCompatibilityMessage,
                                            tone: 'info',
                                        });
                                        return;
                                    }
                                    updateHeader({ overlay_header_on_banner: !Boolean(data.global_header?.overlay_header_on_banner) });
                                }}
                                className={`hidden h-9 shrink-0 items-center gap-2 rounded-lg border px-2.5 text-[11px] font-semibold lg:inline-flex ${!overlayHeaderCompatible ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'} ${trialMode ? 'border-slate-200 bg-white text-slate-700' : 'border-white/10 bg-white/[0.035] text-slate-300'}`}
                                title={overlayHeaderCompatible ? 'Place the global header over the banner.' : overlayCompatibilityMessage}
                                aria-pressed={Boolean(data.global_header?.overlay_header_on_banner && overlayHeaderCompatible)}
                            >
                                <span className={`relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition ${data.global_header?.overlay_header_on_banner && overlayHeaderCompatible ? 'bg-emerald-500' : (trialMode ? 'bg-slate-300' : 'bg-slate-600')}`}>
                                    <span className={`absolute left-0.5 top-0.5 block h-4 w-4 rounded-full bg-white shadow transition-transform ${data.global_header?.overlay_header_on_banner && overlayHeaderCompatible ? 'translate-x-4' : 'translate-x-0'}`} />
                                </span>
                                <span className="hidden 2xl:inline">Overlay Header on Banner</span>
                                <span className="2xl:hidden">Overlay Header</span>
                            </button>

                            )}

                            {!aiOnlyBuilder && !customWebsiteMode && capabilities.canChangeTheme && (
                                <ThemeSelector
                                    compact
                                    value={globalSelections.primary}
                                    themeAccess={themeAccess}
                                    signupUrl={trialMode && trialToken ? `${route('pricing')}?token=${encodeURIComponent(trialToken)}` : null}
                                    customTheme={globalSelections?.custom_brand_theme}
                                    hasLogo={hasRealBrandLogo}
                                    brandMatchNeeded={brandMatchNeeded}
                                    onMatchBrandToLogo={matchThemeToLogo}
                                    brandMatchBusy={logoBusy && logoAiAction === 'theme_to_logo'}
                                    onChange={handleThemeChange}
                                />
                            )}

                            {!aiOnlyBuilder && !customWebsiteMode && (capabilities.canGenerateAi || trialMode) && (
                                <button
                                    type="button"
                                    onClick={() => setIsModalOpen(true)}
                                    className="cosmic-add-spark-button inline-flex h-9 shrink-0 items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-400"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" className="mr-1.5 h-3.5 w-3.5 text-emerald-600">
                                        <path d="M10 2.5c.28 3.92 1.68 5.32 5.6 5.6-3.92.28-5.32 1.68-5.6 5.6-.28-3.92-1.68-5.32-5.6-5.6 3.92-.28 5.32-1.68 5.6-5.6Zm5.25 9.75c.1 1.4.6 1.9 2 2-1.4.1-1.9.6-2 2-.1-1.4-.6-1.9-2-2 1.4-.1 1.9-.6 2-2Z" />
                                    </svg>
                                    <span className="hidden sm:inline">Add Spark</span>
                                </button>
                            )}

                            {trialMode && trialToken && (
                                <button
                                    type="button"
                                    onClick={() => setShowRegenerateModal(true)}
                                    disabled={regenerating}
                                    className="cosmic-trial-regenerate inline-flex h-9 shrink-0 items-center justify-center rounded-lg border px-3 text-xs font-semibold transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed"
                                    title={`Regenerate page · ${trialActionCosts.regenerate_page} Cosmic Credits`}
                                >
                                    {regenerating ? 'Regenerating…' : 'Regenerate'}
                                </button>
                            )}

                            {capabilities.canSave && (!capabilities.canPublish || trialMode) && (
                                <form onSubmit={handleSubmit} className={trialMode ? 'xl:ml-auto' : ''}>
                                    <button
                                        type="submit"
                                        disabled={isSaving || isPublishing}
                                        className={`cosmic-builder-save ${trialMode ? 'cosmic-trial-save' : ''} inline-flex h-9 shrink-0 items-center justify-center rounded-lg border px-4 text-sm font-semibold shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 disabled:cursor-not-allowed`}
                                    >
                                        {isSaving ? 'Saving…' : trialMode ? 'Save changes' : 'Save Draft'}
                                    </button>
                                </form>
                            )}

                            {capabilities.canPurchase && trialToken && (
                                <button
                                    type="button"
                                    onClick={handleBuyTrialWebsite}
                                    disabled={isSaving || trialEmailSaving || trialPurchasePending}
                                    className="cosmic-trial-buy inline-flex h-10 shrink-0 items-center justify-center rounded-xl px-5 text-sm font-bold shadow-sm transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {trialPurchasePending ? 'Saving…' : 'Buy This Website'}
                                </button>
                            )}

                            {capabilities.canPublish && (
                                <div className="relative inline-flex h-9 shrink-0 items-stretch">
                                    <button
                                        type="button"
                                        onClick={handlePublish}
                                        disabled={isSaving || isPublishing || isCheckingHealth}
                                        className={`cosmic-primary-action inline-flex min-w-[88px] items-center justify-center bg-emerald-600 px-4 text-xs font-bold text-white transition hover:bg-emerald-500 focus:z-10 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-50 ${websiteAccessRole === 'website_editor' ? 'rounded-lg' : 'rounded-l-lg'}`}
                                    >
                                        {isCheckingHealth ? 'Checking…' : isPublishing ? 'Publishing…' : 'Publish'}
                                    </button>

                                    {websiteAccessRole !== 'website_editor' && <details className="group relative">
                                        <summary
                                            className="cosmic-publish-menu-trigger inline-flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-r-lg border-l border-emerald-500 bg-emerald-600 text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-300 [&::-webkit-details-marker]:hidden"
                                            aria-label="More publish actions"
                                            title="More actions"
                                        >
                                            <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4 transition-transform group-open:rotate-180">
                                                <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.1 1.02l-4.25 4.5a.75.75 0 0 1-1.1 0l-4.25-4.5a.75.75 0 0 1 .02-1.04Z" clipRule="evenodd" />
                                            </svg>
                                        </summary>

                                        <div className="cosmic-publish-menu absolute right-0 z-[10020] mt-2 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-2xl">
                                            {capabilities.canSave && (
                                                <button
                                                    type="button"
                                                    onClick={() => saveDraft()}
                                                    disabled={isSaving || isPublishing}
                                                    className="cosmic-publish-menu-item flex w-full items-center rounded-lg px-3 py-2 text-left text-xs font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    {isSaving ? 'Saving…' : 'Save Draft'}
                                                </button>
                                            )}

                                            {capabilities.canSave && (
                                                <button
                                                    type="button"
                                                    onClick={() => setIsSaveTemplateOpen(true)}
                                                    disabled={isSavingTemplate || !data.blocks?.length}
                                                    className="cosmic-publish-menu-item cosmic-publish-template-item flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-xs font-semibold text-slate-700 transition hover:bg-violet-50 hover:text-violet-700 disabled:cursor-not-allowed disabled:opacity-40"
                                                >
                                                    <span>Save as Template</span>
                                                    <span aria-hidden="true" className="text-violet-400">▣</span>
                                                </button>
                                            )}

                                            <a
                                                href={route('dashboard', { tab: 'health', website: website.id })}
                                                className="cosmic-publish-menu-item flex w-full items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                            >
                                                <span className="inline-flex items-center gap-2"><span className="text-emerald-500">✚</span> Website Health</span>
                                                <span aria-hidden="true" className="text-slate-400">↗</span>
                                            </a>

                                            {previewUrl ? (
                                                <a
                                                    href={previewUrl}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    title={previewDeploymentError || (previewIsStale ? 'Publish your latest changes to refresh this preview.' : 'Open the latest deployed preview in a new tab.')}
                                                    className="cosmic-publish-menu-item flex w-full items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                                >
                                                    <span className="inline-flex items-center gap-2">
                                                        <span className={`h-1.5 w-1.5 rounded-full ${previewDeploymentError ? 'bg-red-400' : previewIsStale ? 'bg-amber-400' : 'bg-emerald-500'}`} />
                                                        Preview
                                                    </span>
                                                    <span aria-hidden="true" className="text-slate-400">↗</span>
                                                </a>
                                            ) : (
                                                <span
                                                    title="Publish once to create a preview link."
                                                    className="cosmic-publish-menu-item cosmic-publish-menu-disabled flex w-full cursor-not-allowed items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-slate-400"
                                                >
                                                    <span className="h-1.5 w-1.5 rounded-full bg-slate-300" />
                                                    Preview
                                                </span>
                                            )}
                                        </div>
                                    </details>}
                                </div>
                            )}
                            {websiteAccessRole === 'website_editor' && (
                                <Link method="post" as="button" href={route('logout')} className="inline-flex h-9 shrink-0 items-center justify-center rounded-lg border border-white/10 px-3 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white">Log out</Link>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center justify-between gap-3 border-t border-white/[0.06] px-4 py-1.5 sm:px-6 xl:hidden">
                        <div className="flex min-w-0 items-center gap-2 text-[11px]">
                            <span className={publishError ? 'text-red-300' : pageStatus === 'published' ? 'text-emerald-300' : 'text-amber-200'}>
                                {publishError ? 'Publish failed' : pageStatus === 'published' ? 'Published' : 'Draft'}
                            </span>
                            <span className="text-slate-600">•</span>
                            <span className="text-slate-500">{data.blocks.length} Sparks</span>
                            {hasUnsavedChanges && <span className="hidden text-amber-200 sm:inline">• Unsaved changes</span>}
                        </div>
                        {!trialMode && (
                            <CreditBalanceBadge
                                balance={creditBalance}
                                className="h-7 shrink-0"
                            />
                        )}
                    </div>

                    {(saveError || publishError) && (
                        <div className="border-t border-red-400/10 bg-red-400/[0.04] px-4 py-1.5 sm:px-6">
                            <p className="text-[11px] text-red-300">{saveError || publishError}</p>
                        </div>
                    )}
                </header>

                <main className="px-4 py-5 sm:px-6 sm:py-8">
                    <style>{`
                        html.cosmic-header-menu-active .cosmic-block-toolbar { opacity: 0 !important; pointer-events: none !important; }
                        /* Overlay spacing must target the rendered Spark, not the builder toolbar. */
                        .cosmic-overlay-first-spark > .cosmic-builder-spark > :first-child {
                            padding-top: calc(var(--cosmic-overlay-header-height, 80px) + var(--cosmic-overlay-first-spark-padding, clamp(5.25rem, 7vw, 7.5rem))) !important;
                        }
                        @media (max-width: 639px) {
                            .cosmic-overlay-first-spark > .cosmic-builder-spark > :first-child {
                                padding-top: calc(var(--cosmic-overlay-header-height, 72px) + var(--cosmic-overlay-first-spark-padding-mobile, 4rem)) !important;
                            }
                        }
                        /* Sparks live/export visual contract: keep Builder spacing and overflow
                           aligned with published HTML (50px mobile, 80px tablet+). */
                        .cosmic-builder-spark {
                            width: 100%;
                            max-width: 100%;
                            min-width: 0;
                            overflow-x: clip;
                        }
                        .cosmic-builder-spark > section {
                            box-sizing: border-box;
                            max-width: 100%;
                            padding-top: 50px !important;
                            padding-bottom: 50px !important;
                        }
                        .cosmic-builder-spark :is(img,video,iframe,svg,canvas) {
                            max-width: 100%;
                        }
                        .cosmic-builder-spark :is(h1,h2,h3,h4,h5,h6,p,a,button,label) {
                            overflow-wrap: anywhere;
                        }
                        .cosmic-builder-spark .grid > * { min-width: 0; }
                        .cosmic-builder-spark :is(input,select,textarea,button) { max-width: 100%; }
                        @media (min-width: 640px) {
                            .cosmic-builder-spark > section {
                                padding-top: 80px !important;
                                padding-bottom: 80px !important;
                            }
                        }
                        @media (max-width: 639px) {
                            .cosmic-builder-spark table {
                                display: block;
                                width: 100%;
                                max-width: 100%;
                                overflow-x: auto;
                                -webkit-overflow-scrolling: touch;
                            }
                        }
                        /* Clean Page Style button contract: primary actions always use the
                           website primary color, even when a Spark's Tailwind arbitrary-color
                           class was supplied dynamically by the active theme. */
                        .cosmic-builder-canvas[data-cosmic-page-style='clean'] .cosmic-builder-spark :is(a,button)[class*='bg-[#'],
                        .cosmic-builder-canvas[data-cosmic-page-style='clean'] .cosmic-builder-spark :is(a,button)[class*='bg-primary'],
                        .cosmic-builder-canvas[data-cosmic-page-style='clean'] .cosmic-builder-spark :is(a,button)[class*='bg-[var(--p)]'],
                        .cosmic-builder-canvas[data-cosmic-page-style='clean'] .cosmic-builder-spark :is(a,button).cosmic-brand-bg {
                            background: var(--p, var(--cosmic-brand-bg, #243447)) !important;
                            background-color: var(--p, var(--cosmic-brand-bg, #243447)) !important;
                            border-color: var(--p, var(--cosmic-brand-bg, #243447)) !important;
                            color: #fff !important;
                            -webkit-text-fill-color: #fff !important;
                            opacity: 1 !important;
                        }
                    `}</style>
                    <div ref={builderCanvasRef} data-cosmic-page-style={normalizedPageStyle} className={`cosmic-builder-canvas mx-auto w-full max-w-[1560px] overflow-visible rounded-xl bg-white shadow-2xl lg:w-[min(86vw,1560px)] ${trialMode ? 'border border-slate-200 shadow-slate-300/60' : 'border border-white/10 shadow-black/30'}`}>
                        <div
                            className="relative flex w-full flex-col items-stretch overflow-hidden rounded-[11px]"
                            style={{
                                '--cosmic-overlay-header-height': `${overlayHeaderHeight || 80}px`,
                                '--cosmic-header-height': `${overlayHeaderHeight || 80}px`,
                                '--cosmic-builder-viewport-budget': `${builderViewportBudget}px`,
                                '--cosmic-hero-fold-height': overlayHeaderActive
                                    ? `${builderViewportBudget}px`
                                    : `${Math.max(360, builderViewportBudget - (overlayHeaderHeight || 80))}px`,
                            }}
                        >
                    
                    {/* GI-PASSED ANG UPDATED STATE UG FUNCTION SA HEADER */}
                    {data.global_header && (
                        <div ref={overlayHeaderRef} className={`group/header-ai w-full z-40 ${overlayHeaderActive ? 'absolute inset-x-0 top-0 border-b-0 bg-transparent shadow-none' : 'relative bg-white'}`}>
                            {aiOnlyBuilder && <button type="button" onClick={()=>{openLunaChat({type:'page',blockIndex:null,label:'Global Header'});setPageAiPrompt('Update the global header only: ')}} className="absolute right-4 top-3 z-[90] hidden rounded-full border border-violet-300/25 bg-slate-950/85 px-3 py-1.5 text-[10px] font-bold text-violet-200 shadow-xl backdrop-blur group-hover/header-ai:block">✦ Ask Luna</button>}
                            {customWebsiteMode && <button type="button" onClick={()=>{setPageAiPrompt('Update the global header only: ');setPageAiError('');setPageAiOpen(true)}} className="absolute right-4 top-3 z-[90] hidden rounded-full border border-violet-300/25 bg-slate-950/85 px-3 py-1.5 text-[10px] font-bold text-violet-200 shadow-xl backdrop-blur group-hover/header-ai:block">✨ Ask Cosmic Header</button>}
                            {data.global_header.type === 'dark_cyan_header' && (
                                <DarkCyanHeader block={data.global_header} overlay={overlayHeaderActive} overlayTone={overlayHeaderTone} overlayLogoLight={overlayLogoLight} globalTheme={globalSelections} onUpdate={updateHeader} pageTargets={websitePages} onLogoClick={() => setShowLogoModal(true)} />
                            )}
                            {data.global_header.type === 'glassmorphism_header' && (
                                <GlassmorphismHeader
                                    block={data.global_header}
                                    overlay={overlayHeaderActive}
                                    overlayTone={overlayHeaderTone}
                                    overlayLogoLight={overlayLogoLight}
                                    overlayCtaTreatment={overlayCtaTreatment}
                                    onUpdate={updateHeader}
                                    globalTheme={globalSelections}
                                    pageTargets={websitePages}
                                    onLogoClick={() => setShowLogoModal(true)}
                                />
                            )}
                        </div>
                    )}

                    {data.blocks.map((block, index) => (

                        <div
                            key={block._renderKey || index}
                            className={`relative group w-full transition-all duration-300 ${overlayHeaderActive && index === 0 ? 'cosmic-overlay-first-spark' : ''}`}
                        >

                            {/* AI-only Hover Toolbar */}
                            {capabilities.canManageBlocks && (
                                <div className="cosmic-block-toolbar absolute top-5 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-all duration-300 z-[70]">
                                    <div className="flex items-center gap-2 rounded-full border border-violet-300/20 bg-slate-950/90 px-2.5 py-2 shadow-2xl backdrop-blur-xl">
                                        <span className="max-w-[180px] truncate px-2 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">
                                            {BlockRegistry[block.type]?.schema?.title || block.type.replaceAll("_"," ")}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={()=>openLunaChat({type:'section',blockIndex:index,label:BlockRegistry[block.type]?.schema?.title || block.heading || 'Selected Section'})}
                                            className="inline-flex h-8 items-center gap-1.5 rounded-full bg-gradient-to-r from-violet-500 to-indigo-500 px-3 text-[11px] font-black text-white shadow-lg shadow-violet-950/30 transition hover:scale-[1.03]"
                                            title="Ask Luna about this section"
                                        >
                                            <span aria-hidden="true">✦</span> Ask Luna
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* Selected Outline */}

                            <div data-custom-spark-key={block.custom_spark_key || undefined} className={`cosmic-builder-spark group-hover:ring-2 group-hover:ring-violet-500/40 transition-all duration-500 ${layoutApplying === index ? "scale-[0.997] opacity-80 ring-2 ring-violet-400/40" : "opacity-100"}`}>

                                {renderBlock(block,index)}

                            </div>

                        </div>

                    ))}

                    {data.blocks.length === 0 && (
                        <section id="cosmic-unbuilt-page" className="flex min-h-[340px] items-center justify-center border-y border-slate-200 bg-slate-100 px-6 py-12 text-center">
                            <div className="w-full max-w-xl rounded-2xl border border-slate-200 bg-white/90 px-6 py-8 shadow-sm">
                                <span className="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-lg text-white" aria-hidden="true">✦</span>
                                <h2 className="mt-4 text-lg font-semibold text-slate-900">This page is ready to build</h2>
                                <p className="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500">Your page, navigation, global header and footer are already connected. Choose how you want to create the content.</p>

                                {!trialMode && (
                                    <div className="mt-6 flex items-center justify-center">
                                        <button type="button" onClick={()=>openLunaChat({type:'page',blockIndex:null,label:'Whole Page'})} className="inline-flex h-10 items-center justify-center gap-2 rounded-full bg-gradient-to-r from-violet-600 to-indigo-600 px-5 text-sm font-bold text-white shadow-lg transition hover:-translate-y-0.5">
                                            <span aria-hidden="true">✦</span> Ask Luna to build this page
                                        </button>
                                    </div>
                                )}

                                {trialMode && capabilities.canGenerateAi && (
                                    <button type="button" onClick={() => setIsModalOpen(true)} className="mt-5 inline-flex h-10 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-violet-400">
                                        Add your first Spark
                                    </button>
                                )}
                            </div>
                        </section>
                    )}

                    {/* FOOTER RENDERER */}
                    {data.global_footer && (
                        <div className="relative group/footer w-full mt-auto">
                            {aiOnlyBuilder && <button type="button" onClick={()=>{openLunaChat({type:'page',blockIndex:null,label:'Global Footer'});setPageAiPrompt('Update the global footer only: ')}} className="absolute right-4 top-4 z-[90] hidden rounded-full border border-violet-300/25 bg-slate-950/85 px-3 py-1.5 text-[10px] font-bold text-violet-200 shadow-xl backdrop-blur group-hover/footer:block">✦ Ask Luna</button>}
                            {customWebsiteMode && <button type="button" onClick={()=>{setPageAiPrompt('Update the global footer only: ');setPageAiError('');setPageAiOpen(true)}} className="absolute right-4 top-4 z-[90] hidden rounded-full border border-violet-300/25 bg-slate-950/85 px-3 py-1.5 text-[10px] font-bold text-violet-200 shadow-xl backdrop-blur group-hover/footer:block">✨ Ask Cosmic Footer</button>}
                            {!aiOnlyBuilder && capabilities.canEditGlobalShell && (
                                <div className="cosmic-block-toolbar pointer-events-none absolute left-1/2 top-5 z-[70] -translate-x-1/2 opacity-0 transition-all duration-300 group-hover/footer:opacity-100 focus-within:opacity-100">
                                    <div className="pointer-events-auto flex items-center gap-2 rounded-full border border-slate-700 bg-slate-900/90 px-3 py-2 shadow-2xl backdrop-blur-xl">
                                        <div className="flex flex-col px-2 leading-none">
                                            <span className="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Mega Footer</span>
                                            <span className="mt-1 text-[9px] font-bold uppercase tracking-[0.2em] text-amber-300">
                                                {(data.global_footer?.mega_footer?.theme || 'auto') === 'auto' && '✨ Auto'}
                                                {(data.global_footer?.mega_footer?.theme || 'auto') === 'primary' && '🟦 Primary'}
                                                {(data.global_footer?.mega_footer?.theme || 'auto') === 'white' && '⬜ White'}
                                                {(data.global_footer?.mega_footer?.theme || 'auto') === 'surface' && '🩶 Surface'}
                                            </span>
                                        </div>
                                        <div className="h-5 w-px bg-slate-700" />
                                        <button type="button" title="Add Spark above footer" aria-label="Add Spark above footer" onClick={() => { setSparkInsertTarget({ index: data.blocks.length, position: 'above' }); setIsModalOpen(true); }} className="h-8 w-8 rounded-lg text-emerald-300 transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-400">↑</button>
                                        <div className="relative">
                                            <button type="button" title="Footer theme" aria-label="Footer theme" onClick={() => setFooterThemeMenu((value) => !value)} className="h-8 w-8 rounded-lg text-slate-300 transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-violet-400">🎨</button>
                                            {footerThemeMenu && (
                                                <div className="absolute right-0 top-10 w-44 overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
                                                    {[['auto','✨ Auto'],['primary','🟦 Primary'],['white','⬜ White'],['surface','🩶 Surface']].map(([value,label]) => (
                                                        <button key={value} type="button" onClick={() => { updateFooter({ mega_footer: { ...(data.global_footer?.mega_footer || {}), enabled: Boolean(data.global_footer?.mega_enabled ?? data.global_footer?.mega_footer?.enabled), theme: value } }); setFooterThemeMenu(false); }} className="w-full px-4 py-3 text-left text-sm text-slate-200 transition hover:bg-slate-800">{label}</button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                        <button type="button" role="switch" title="Enable or disable Mega Footer" aria-label="Enable or disable Mega Footer" aria-checked={Boolean(data.global_footer?.mega_enabled ?? data.global_footer?.mega_footer?.enabled)} onClick={() => { const enabled = !Boolean(data.global_footer?.mega_enabled ?? data.global_footer?.mega_footer?.enabled); updateFooter({ mega_enabled: enabled, mega_footer: { ...(data.global_footer?.mega_footer || {}), enabled, theme: data.global_footer?.mega_footer?.theme || 'auto' } }); }} className={`relative h-7 w-12 shrink-0 rounded-full transition ${Boolean(data.global_footer?.mega_enabled ?? data.global_footer?.mega_footer?.enabled) ? 'bg-emerald-500' : 'bg-slate-700'}`}><span className={`absolute top-1 h-5 w-5 rounded-full bg-white shadow transition ${Boolean(data.global_footer?.mega_enabled ?? data.global_footer?.mega_footer?.enabled) ? 'left-6' : 'left-1'}`} /></button>
                                    </div>
                                </div>
                            )}
                            <MinimalFooter block={normalizeGlobalFooterBlock(data.global_footer)} onUpdate={updateFooter} editorMode={Boolean(capabilities.canEditGlobalShell && (data.global_footer?.mega_enabled ?? data.global_footer?.mega_footer?.enabled))} resolvedTheme={resolveBlockTheme({ theme: data.global_footer?.mega_footer?.theme || 'auto' }, data.blocks.length)} />
                        </div>
                    )}
                </div>


                    </div>
                </main>

                <div className="hidden max-w-7xl w-full mx-auto px-6 mt-10">

                    <div className="bg-white border border-slate-200 rounded-2xl shadow-lg px-6 py-5">

                        <div className="flex flex-wrap items-center justify-between gap-5">

                            {/* Theme */}
                            {/*<div className="flex items-center gap-3 min-w-[260px]">
                                <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
                                    🎨 Theme
                                </span>

                                <select
                                    className="flex-1 bg-slate-100 border border-slate-300 rounded-xl px-4 py-2.5 font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    value={globalSelections.primary}
                                    onChange={(e) => {
                                        setGlobalSelections(prev => ({
                                            ...prev,
                                            primary: e.target.value
                                        }));
                                    }}
                                >
                                    <option value="midnight">Midnight Blue</option>
                                    <option value="obsidian">Obsidian Black</option>
                                    <option value="terracotta">Terracotta</option>
                                    <option value="asphalt">Asphalt Grey</option>
                                    <option value="espresso">Espresso Brown</option>
                                    <option value="navy">Classic Navy</option>
                                    <option value="void">Void Deep Blue</option>
                                    <option value="emerald">Emerald Forest</option>
                                    <option value="coffee">Coffee Bean</option>
                                    <option value="rose">Rose Bloom</option>
                                    <option value="indigo">Royal Indigo</option>
                                    <option value="amber">Golden Amber</option>
                                    <option value="charcoal">Charcoal Grey</option>
                                    <option value="violet">Deep Violet</option>
                                    <option value="teal">Coastal Teal</option>
                                    <option value="ruby">Ruby Red</option>
                                    <option value="forest">Moss Forest</option>
                                    <option value="sapphire">Sapphire Blue</option>
                                    <option value="plum">Royal Plum</option>
                                    <option value="olive">Olive Grove</option>
                                </select>
                            </div>*/}

                            {!customWebsiteMode && <ThemeSelector
                                value={globalSelections.primary}
                                themeAccess={themeAccess}
                                customTheme={globalSelections?.custom_brand_theme}
                                hasLogo={hasRealBrandLogo}
                                brandMatchNeeded={brandMatchNeeded}
                                onMatchBrandToLogo={matchThemeToLogo}
                                brandMatchBusy={logoBusy && logoAiAction === 'theme_to_logo'}
                                onChange={(theme) => {

                                    setGlobalSelections(prev => ({
                                        ...prev,
                                        primary: theme
                                    }));

                                    setHasUnsavedTheme(true);

                                }}
                            />}

                            {/* AI */}
                            {!customWebsiteMode && <button
                                type="button"
                                onClick={() => { setSparkInsertTarget(null); setIsModalOpen(true); }}
                                className="px-6 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:scale-[1.02] hover:shadow-xl transition text-white font-bold"
                            >
                                ✨ Add Spark
                            </button>}

                            {/* Builder */}
                            <div className="text-sm">
                                <div className="text-slate-400 uppercase tracking-wider text-[10px]">
                                    Builder Node
                                </div>

                                <div className="font-bold text-indigo-600">
                                    /{page.slug}
                                </div>
                            </div>

                            {/* Save */}
                            <form onSubmit={handleSubmit}>
                                <button
                                    type="submit"
                                    disabled={isSaving || isPublishing}
                                    className="px-8 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-lg transition disabled:opacity-50"
                                >
                                    {isSaving
                                        ? "Saving..."
                                        : "Save Draft"}
                                </button>
                            </form>

                        </div>

                    </div>

                </div>


            </div>
            
            {layoutPreview?.catalogItem && (
                <GlobalSparkPreviewModal
                    spark={layoutPreview.catalogItem}
                    previewVariant="primary"
                    websiteTheme={globalSelections}
                    commerce={commerce}
                    contentWorkspace={contentWorkspace}
                    eyebrow="Layout Preview"
                    title={layoutPreview.title}
                    onClose={() => setLayoutPreview(null)}
                    footerRight={
                        <div className="flex gap-2">
                            <button type="button" onClick={() => setLayoutPreview(null)} className="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300">Close</button>
                            {layoutPreview.owned ? (
                                <button
                                    type="button"
                                    onClick={() => {
                                        changeBlockLayout(layoutPreview.blockIndex, layoutPreview);
                                        setLayoutPreview(null);
                                    }}
                                    className="cosmic-layout-use-button rounded-xl !bg-white px-4 py-2 text-sm font-bold !text-slate-950 hover:!bg-violet-100"
                                    style={{ color: '#0f172a', WebkitTextFillColor: '#0f172a', opacity: 1, filter: 'none' }}
                                >
                                    Use layout
                                </button>
                            ) : layoutPreview.canInstall ? (
                                <button
                                    type="button"
                                    disabled={layoutBusyKey === layoutPreview.type}
                                    onClick={() => unlockLayoutSpark(layoutPreview)}
                                    className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-500 disabled:opacity-50"
                                >
                                    {layoutBusyKey === layoutPreview.type ? 'Buying…' : Number(layoutPreview.credits || 0) === 0 ? 'Add Free Spark' : `Buy Spark · ⚡ ${layoutPreview.credits}`}
                                </button>
                            ) : null}
                        </div>
                    }
                />
            )}

            {!aiOnlyBuilder && (capabilities.canGenerateAi || trialMode) && (
                <AddSectionModal
                    open={isModalOpen}
                    onClose={() => { setIsModalOpen(false); setSparkInsertTarget(null); }}
                    onAdd={addBlock}
                    hasBlocks={(data.blocks?.length ?? 0) > 0}
                    hasWebsiteContent={hasWebsiteContent}
                    websiteContext={websiteContext}
                    websiteId={website?.id}
                    headerOverlayEnabled={Boolean(data.global_header?.overlay_header_on_banner)}
                    trialMode={trialMode}
                    trialToken={trialToken}
                    cosmicPricing={cosmicPricing}
                    websiteTheme={globalSelections}
                    commerce={commerce}
                    contentWorkspace={contentWorkspace}
                    preloadedCatalog={sparkCatalog}
                    preloadedCatalogLoading={sparkCatalogLoading}
                    preloadedCatalogLoaded={sparkCatalogLoaded}
                    preparedVisibleCount={preparedSparkCount}
                    ownedOnly={false}
                    contextLabel={sparkInsertTarget ? `Insert Spark ${sparkInsertTarget.position}` : 'Add Spark'}
                    onOwnershipChanged={(sparkKey) => setSparkCatalog((current) => current.map((spark) => spark.key === sparkKey ? { ...spark, owned: true } : spark))}
                />
            )}

            {!trialMode && (
                <SavePageTemplateModal
                    open={isSaveTemplateOpen}
                    onClose={() => !isSavingTemplate && setIsSaveTemplateOpen(false)}
                    onSave={saveAsTemplate}
                    pageTitle={page.title || 'Untitled Page'}
                    saving={isSavingTemplate}
                />
            )}

            {(capabilities.canGenerateAi || trialMode) && (
                <PageTemplatesModal
                    open={isTemplatesOpen}
                    onClose={() => setIsTemplatesOpen(false)}
                    onInstall={(blocks) => {
                        replaceBlocks(blocks);
                        setIsTemplatesOpen(false);
                        return true;
                    }}
                    websiteContext={websiteContext}
                    websiteId={website?.id}
                    headerOverlayEnabled={Boolean(data.global_header?.overlay_header_on_banner)}
                    trialMode={trialMode}
                    trialToken={trialToken}
                    websiteTheme={globalSelections}
                    themeValue={globalSelections.primary}
                    onThemeChange={handleThemeChange}
                    themeAccess={themeAccess}
                    customTheme={globalSelections?.custom_brand_theme}
                    preloadedCatalog={pageTemplateCatalog}
                    preloadedCatalogLoading={pageTemplateCatalogLoading}
                    preloadedCatalogLoaded={pageTemplateCatalogLoaded}
                    preparedVisibleCount={preparedTemplateCount}
                    hasLogo={hasRealBrandLogo}
                    brandMatchNeeded={brandMatchNeeded}
                    onMatchBrandToLogo={matchThemeToLogo}
                    brandMatchBusy={logoBusy && logoAiAction === 'theme_to_logo'}
                />
            )}

            {capabilities.canGenerateAi && (
                <GeneratePageModal
                    open={isGeneratePageOpen}
                    onClose={() => setIsGeneratePageOpen(false)}
                    onReplace={replaceBlocks}
                    websiteContext={websiteContext}
                    websiteId={website?.id}
                    headerOverlayEnabled={Boolean(data.global_header?.overlay_header_on_banner)}
                    creditCost={Number(cosmicPricing?.actions?.generate_page || 50)}
                />
            )}

            {/* AI MODAL INJECTOR CONFIG */}
            
            {pendingThemeLogoAdapt && (
                <div className="fixed inset-0 z-[245] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <div className="border-b border-slate-200 px-6 py-5">
                            <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-600">Instant Brand Adaptation</p>
                            <h3 className="mt-1 text-xl font-bold text-slate-900">Adapt logo to {pendingThemeLogoAdapt.themeName}?</h3>
                            <p className="mt-2 text-sm leading-6 text-slate-500">Your new theme is already selected. Cosmic can instantly tint your existing logo to the active theme using CSS and update both the header and footer automatically.</p>
                        </div>
                        <div className="p-6">
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p className="text-sm font-semibold text-slate-800">Keep the original or match it instantly</p>
                                <p className="mt-1 text-xs leading-5 text-slate-500">Both options are free. Matching uses a reversible CSS treatment, so your original logo file is preserved.</p>
                            </div>
                            <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                <button type="button" disabled={themeLogoAdaptBusy} onClick={keepCurrentLogoForSelectedTheme} className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">Keep Current Logo</button>
                                <button type="button" disabled={themeLogoAdaptBusy} onClick={adaptLogoToSelectedTheme} className="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">{themeLogoAdaptBusy ? 'Matching…' : 'Match Logo · Free'}</button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {logoCropOpen && (
                <div className="fixed inset-0 z-[10100] flex items-center justify-center bg-slate-950/80 p-3 backdrop-blur-sm sm:p-6">
                    <div className="flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
                        <div className="border-b border-slate-200 px-5 py-4 sm:px-7 sm:py-5">
                            <p className="text-xs font-bold uppercase tracking-[0.2em] text-emerald-600">{logoCropEntryPrompt ? 'Logo check' : 'Logo framing'}</p>
                            <h3 className="mt-1 text-xl font-bold text-slate-950 sm:text-2xl">{logoCropEntryPrompt ? 'Make your logo fit the header' : 'Crop & position your logo'}</h3>
                            <p className="mt-1 text-sm text-slate-500">{logoCropEntryPrompt ? 'Your complete generated logo is contained inside the 650 × 200 header frame with safety padding. Adjust only if you want to fine-tune it, then save.' : 'Your full logo is contained inside the 650 × 200 header frame at 100%. Drag or zoom only if you want to fine-tune its position.'}</p>
                        </div>

                        <div className="min-h-0 overflow-y-auto p-4 sm:p-6">
                            <div
                                ref={logoCropFrameRef}
                                onPointerDown={handleLogoCropPointerDown}
                                onPointerMove={handleLogoCropPointerMove}
                                onPointerUp={handleLogoCropPointerUp}
                                onPointerCancel={handleLogoCropPointerUp}
                                className="relative mx-auto aspect-square w-full max-w-[560px] cursor-grab touch-none overflow-hidden rounded-2xl border border-slate-300 bg-slate-100 shadow-inner active:cursor-grabbing"
                            >
                                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(45deg,#f8fafc_25%,transparent_25%),linear-gradient(-45deg,#f8fafc_25%,transparent_25%),linear-gradient(45deg,transparent_75%,#f8fafc_75%),linear-gradient(-45deg,transparent_75%,#f8fafc_75%)] bg-[length:22px_22px] bg-[position:0_0,0_11px,11px_-11px,-11px_0px]" />
                                <div
                                    className="pointer-events-none absolute left-1/2 top-1/2 grid w-[90%] -translate-x-1/2 -translate-y-1/2 place-items-center"
                                    style={{ aspectRatio: '650 / 200' }}
                                >
                                    <img
                                        src={logoCropSource}
                                        alt="Logo crop preview"
                                        crossOrigin="anonymous"
                                        onLoad={(event) => {
                                            const naturalWidth = event.currentTarget.naturalWidth;
                                            const naturalHeight = event.currentTarget.naturalHeight;
                                            setLogoCropNatural({ width: naturalWidth, height: naturalHeight });
                                            setLogoCropZoom(1);
                                            setLogoCropX(0);
                                            setLogoCropY(0);
                                        }}
                                        draggable={false}
                                        className="max-h-full max-w-full select-none object-contain drop-shadow-sm"
                                        style={{
                                            transform: `translate(${logoCropX}px, ${logoCropY}px) scale(${logoCropZoom})`,
                                            transformOrigin: 'center center',
                                        }}
                                    />
                                </div>

                                <div className="pointer-events-none absolute inset-0 bg-slate-950/30" />
                                <div
                                    ref={logoCropSafeFrameRef}
                                    className="pointer-events-none absolute left-1/2 top-1/2 w-[90%] -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-xl border-2 border-emerald-400 shadow-[0_0_0_999px_rgba(15,23,42,.18),0_0_0_1px_rgba(255,255,255,.7)]"
                                    style={{ aspectRatio: '650 / 200' }}
                                >
                                    <div className="absolute inset-0 bg-white/5" />
                                </div>
                                <div className="pointer-events-none absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-slate-950/70 px-3 py-1 text-[10px] font-semibold text-white">
                                    Final header crop: 650 × 200
                                </div>
                            </div>

                            <div className="mx-auto mt-5 max-w-[700px]">
                                <div className="flex items-center gap-4">
                                    <span className="w-12 text-xs font-semibold text-slate-600">Zoom</span>
                                    <input
                                        type="range"
                                        min="0.5"
                                        max="4"
                                        step="0.05"
                                        value={logoCropZoom}
                                        onChange={(event) => setLogoCropZoom(Number(event.target.value))}
                                        disabled={logoCropSaving}
                                        className="w-full accent-emerald-600"
                                    />
                                    <span className="w-12 text-right text-xs font-semibold text-slate-600">{Math.round(logoCropZoom * 100)}%</span>
                                </div>
                                <p className="mt-2 text-center text-xs text-slate-500">100% always starts fully contained inside the green frame. The original image stays untouched; only the final 650 × 200 header crop is saved.</p>
                            </div>

                            <div className="mx-auto mt-5 flex max-w-[700px] flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-5">
                                <button
                                    type="button"
                                    onClick={resetLogoCrop}
                                    disabled={logoCropSaving}
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                >
                                    Reset
                                </button>
                                <div className="flex flex-wrap justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={cancelLogoCrop}
                                        disabled={logoCropSaving}
                                        className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        onClick={saveLogoCrop}
                                        disabled={logoCropSaving || !logoCropNatural.width}
                                        className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {logoCropSaving ? 'Saving Crop…' : 'Save Logo'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {pendingSvgLogoMatch && !logoBusy && (
                <div className="fixed inset-0 z-[10045] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
                    <div id="cosmic-svg-logo-match-modal" className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                        <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-600">SVG logo uploaded</p>
                        <h3 className="mt-2 text-xl font-bold text-slate-950">Match this logo to the current theme?</h3>
                        <p className="mt-2 text-sm leading-6 text-slate-600">Your original SVG is already saved. Keep it exactly as uploaded, or apply an instant reversible CSS color treatment for the active website theme.</p>
                        <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">Match Logo to Theme is instant and free. Your original SVG remains unchanged.</div>
                        <div className="mt-6 grid gap-3 sm:grid-cols-2">
                            <button type="button" onClick={() => { setPendingSvgLogoMatch(null); setLogoSyncState('logo_changed'); }} className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">Keep Original · Free</button>
                            <button type="button" onClick={() => matchLogoToTheme(true)} className="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700">✦ Match Logo · Free</button>
                        </div>
                    </div>
                </div>
            )}

            {!trialMode && pendingUploadedLogoThemeChoice && !logoBusy && (
                <div className="fixed inset-0 z-[10040] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                        <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-600">Logo saved</p>
                        <h3 className="mt-2 text-xl font-bold text-slate-950">Match your website to this logo?</h3>
                        <p className="mt-2 text-sm leading-6 text-slate-600">Your new logo is already saved. You can keep the current theme for free, or let Cosmic build and apply My Brand Theme from the logo colors.</p>
                        <div className="mt-6 grid gap-3 sm:grid-cols-2">
                            <button type="button" onClick={keepCurrentThemeAfterLogoUpload} className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">Keep Current Theme</button>
                            <button type="button" onClick={matchUploadedLogoToTheme} className="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700">✦ Match Theme · 20 Credits</button>
                        </div>
                    </div>
                </div>
            )}

            {publishHealthReview && (() => {
                const issues = publishHealthIssues(publishHealthReview);
                const critical = issues.filter((finding) => finding.status === 'critical');
                const warnings = issues.filter((finding) => finding.status === 'warning');
                return (
                    <div className="fixed inset-0 z-[10120] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm">
                        <div id="cosmic-publish-health-review" className="w-full max-w-2xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="cosmic-publish-health-title">
                            <div className="border-b border-slate-200 px-6 py-5 sm:px-7">
                                <div className="flex items-start justify-between gap-5">
                                    <div>
                                        <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-600">Pre-publish health check</p>
                                        <h2 id="cosmic-publish-health-title" className="mt-2 text-xl font-bold text-slate-950">Review website health before publishing</h2>
                                        <p className="mt-2 text-sm leading-6 text-slate-600">Your draft is saved. These checks do not change your content and you can still publish if you have reviewed the findings.</p>
                                    </div>
                                    <div className="shrink-0 rounded-2xl bg-slate-950 px-4 py-3 text-center text-white">
                                        <p className="text-2xl font-extrabold">{publishHealthReview.score}</p>
                                        <p className="text-[9px] font-bold uppercase tracking-[0.16em] text-slate-400">Health</p>
                                    </div>
                                </div>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {critical.length > 0 && <span className="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700">{critical.length} critical</span>}
                                    {warnings.length > 0 && <span className="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">{warnings.length} warning{warnings.length === 1 ? '' : 's'}</span>}
                                    <span className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Page is saved</span>
                                </div>
                            </div>

                            <div className="max-h-[52vh] space-y-2 overflow-y-auto px-6 py-5 sm:px-7">
                                {issues.slice(0, 8).map((finding) => (
                                    <div key={finding.id} className={`rounded-2xl border p-4 ${finding.status === 'critical' ? 'border-rose-200 bg-rose-50/70' : 'border-amber-200 bg-amber-50/70'}`}>
                                        <div className="flex items-center gap-2">
                                            <span className={`h-2 w-2 rounded-full ${finding.status === 'critical' ? 'bg-rose-500' : 'bg-amber-500'}`} />
                                            <span className={`text-[10px] font-extrabold uppercase tracking-[0.14em] ${finding.status === 'critical' ? 'text-rose-700' : 'text-amber-700'}`}>{finding.status}</span>
                                            <span className="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">{finding.category}</span>
                                        </div>
                                        <p className="mt-2 text-sm font-bold text-slate-900">{finding.title}</p>
                                        <p className="mt-1 text-xs leading-5 text-slate-600">{finding.message}</p>
                                    </div>
                                ))}
                                {issues.length > 8 && <p className="px-1 pt-1 text-xs text-slate-500">+ {issues.length - 8} more finding{issues.length - 8 === 1 ? '' : 's'} in Website Health.</p>}
                            </div>

                            <div className="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                                <a href={route('dashboard', { tab: 'health', website: website.id })} className="text-center text-sm font-bold text-violet-700 hover:text-violet-800">Open Health Center →</a>
                                <div className="flex flex-col-reverse gap-2 sm:flex-row">
                                    <button type="button" onClick={() => setPublishHealthReview(null)} className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100">Cancel</button>
                                    <button type="button" onClick={publishAfterHealthReview} disabled={isPublishing} className={`rounded-xl px-5 py-2.5 text-sm font-extrabold text-white disabled:opacity-50 ${critical.length > 0 ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'}`}>{isPublishing ? 'Publishing…' : critical.length > 0 ? 'Publish anyway' : 'Publish with warnings'}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                );
            })()}

            {logoBusy && logoAiAction && (
                <div className="fixed inset-0 z-[10050] grid place-items-center bg-white/80 px-5 text-center backdrop-blur-md" role="status" aria-live="polite" aria-busy="true">
                    <div className="w-full max-w-xl rounded-[28px] border border-emerald-200/90 bg-white/95 px-6 py-8 shadow-[0_35px_100px_-30px_rgba(15,23,42,.35)] ring-1 ring-white sm:px-9 sm:py-10">
                        <div className="relative mx-auto h-16 w-16" aria-hidden="true">
                            <div className="absolute inset-0 animate-spin rounded-full border-[3px] border-violet-200 border-t-violet-500 border-r-cyan-300 border-b-emerald-400" />
                            <div className="absolute inset-[3px] grid place-items-center rounded-full bg-white text-xl text-emerald-600 shadow-lg">✦</div>
                        </div>
                        <p className="mt-5 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Cosmic AI</p>
                        <h3 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">
                            {logoAiAction === 'generate' && 'Creating your logo'}
                            {logoAiAction === 'regenerate' && 'Regenerating your logo'}
                            {logoAiAction === 'logo_to_theme' && 'Matching logo to theme'}
                            {logoAiAction === 'theme_to_logo' && 'Matching theme to logo'}
                        </h3>
                        <p className="mt-3 min-h-5 text-sm text-slate-600">{logoAiStage}</p>

                        <div className="mt-7 grid grid-cols-3 gap-2">
                            {[
                                ['Analyze', 12],
                                ['Create', 48],
                                ['Finish', 86],
                            ].map(([label, threshold], index) => {
                                const complete = logoAiProgress >= threshold;
                                return (
                                    <div key={label} className={`flex items-center justify-center gap-2 rounded-lg border px-2.5 py-2 text-[10px] font-medium sm:text-xs ${complete ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-500'}`}>
                                        <span className={`grid h-4 w-4 shrink-0 place-items-center rounded-full text-[9px] ${complete ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500'}`}>{complete ? '✓' : index + 1}</span>
                                        <span>{label}</span>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="mt-6 h-2 overflow-hidden rounded-full bg-slate-200">
                            <div className="h-full rounded-full bg-gradient-to-r from-violet-500 via-cyan-400 to-emerald-400 transition-[width] duration-300" style={{ width: `${logoAiProgress}%` }} />
                        </div>
                        <div className="mt-3 flex items-center justify-between text-xs text-slate-600">
                            <span>Keep this window open while Cosmic AI works.</span>
                            <span>{logoAiProgress}%</span>
                        </div>
                    </div>
                </div>
            )}

            <MediaPickerModal
                open={logoMediaLibraryOpen}
                websiteId={website?.id}
                title="Choose a logo from Media Library"
                kind="logo"
                onClose={() => setLogoMediaLibraryOpen(false)}
                onSelect={(asset) => {
                    if (!asset?.url) return;
                    setLogoMediaLibraryOpen(false);
                    openLogoCrop(asset.url, logoCompanyName || data.global_header?.logo_text || website?.name, { sourceKind:'upload' });
                }}
            />

            {showLogoModal && (
                <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
                    <div id="cosmic-logo-customize-modal" className="cosmic-logo-modal cosmic-logo-customize-modal w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <div className="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                            <div>
                                <h3 className="text-lg font-bold text-slate-900">Customize your logo</h3>
                                <p className="mt-1 text-sm text-slate-500">{data.global_header?.logo_image_url && !String(data.global_header.logo_image_url).includes('your-logo.png') ? 'Regenerate with Cosmic AI or replace your current logo.' : 'Generate a logo with Cosmic AI or upload your existing brand logo.'}</p>
                            </div>
                            <button type="button" onClick={() => { if (!logoBusy) { setShowLogoModal(false); setShowLogoGenerateForm(false); } }} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close logo dialog">✕</button>
                        </div>

                        <div className="space-y-4 p-6">
                            {!showLogoGenerateForm ? (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <button type="button" disabled={logoBusy} onClick={() => setShowLogoGenerateForm(true)} className="cosmic-logo-generate-card min-h-[104px] rounded-xl bg-emerald-600 px-5 py-5 text-left text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60">
                                        <span className="block text-base font-bold">✨ {data.global_header?.logo_image_url && !String(data.global_header.logo_image_url).includes('your-logo.png') ? 'Regenerate Logo' : 'Generate Logo'}</span>
                                        <span className="mt-1 block text-xs text-emerald-50">Let Cosmic AI create a logo for this website · {trialActionCosts.generate_logo} Credits.</span>
                                    </button>
                                    <button type="button" disabled={logoBusy} onClick={() => trialMode ? logoUploadRef.current?.click() : setLogoMediaLibraryOpen(true)} className="cosmic-logo-upload-card min-h-[104px] rounded-xl border-2 border-slate-300 bg-white px-5 py-5 text-left text-slate-950 shadow-sm transition hover:border-emerald-400 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 disabled:opacity-70">
                                        <span className="cosmic-logo-upload-title block text-base font-bold">↑ {data.global_header?.logo_image_url && !String(data.global_header.logo_image_url).includes('your-logo.png') ? 'Replace Logo' : 'Upload Logo'}</span>
                                        <span className="cosmic-logo-upload-help mt-1 block text-xs font-medium text-slate-600">{trialMode ? 'SVG, PNG, JPG or WebP up to 2 MB.' : 'Choose an existing logo or upload a new one in Media Library.'}</span>
                                    </button>
                                    <input ref={logoUploadRef} type="file" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp" onChange={uploadTrialLogo} className="hidden" />
                                    {globalSelections?.brand_original_logo_url && String(globalSelections.brand_original_logo_url) !== String(data.global_header?.logo_image_url || '') && (
                                        <button type="button" disabled={logoBusy} onClick={restoreOriginalLogo} className="cosmic-logo-restore-card sm:col-span-2 min-h-[72px] w-full rounded-xl border px-5 py-4 text-left transition">
                                            <span className="cosmic-logo-restore-title block text-sm font-bold">↶ Restore Original Logo</span>
                                            <span className="cosmic-logo-restore-help mt-1 block text-xs">Return to the preserved source logo. Header and footer update together.</span>
                                        </button>
                                    )}
                                    {logoMatchPending && (
                                        <button
                                            type="button"
                                            disabled={logoBusy}
                                            onClick={() => {
                                                setShowLogoModal(false);
                                                matchLogoToTheme();
                                            }}
                                            className="cosmic-logo-match-pending sm:col-span-2 min-h-[84px] w-full rounded-xl border px-5 py-4 text-left transition disabled:cursor-not-allowed"
                                        >
                                            <span className="cosmic-logo-match-pending-title block text-sm font-extrabold">✨ Match Logo to Theme</span>
                                            <span className="cosmic-logo-match-pending-help mt-1.5 block text-xs leading-5">Pending after keeping the original logo. Apply a free CSS color treatment to the active theme without changing the saved crop or size.</span>
                                        </button>
                                    )}
                                    <p className="sm:col-span-2 text-xs text-slate-500">{trialMode ? `AI logo actions use Guest Cosmic Credits. Current balance: ${Number.isFinite(Number(creditBalance)) ? Number(creditBalance) : 500} credits. Upload/replace is free.` : `AI logo generation costs 50 credits. Match Logo to Theme is free and instant. Current balance: ${Number.isFinite(Number(creditBalance)) ? Number(creditBalance) : 0} credits. Upload/replace is free.`}</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <div>
                                        <label className="cosmic-logo-company-label mb-2 block text-sm font-semibold text-slate-800">Company name</label>
                                        <input type="text" maxLength={80} value={logoCompanyName} onChange={(event) => setLogoCompanyName(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter' && !logoBusy && logoCompanyName.trim().length >= 2) generateTrialLogo(); }} className="cosmic-logo-company-input w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-emerald-500 focus:ring-emerald-500" placeholder="e.g. Northstar Construction" autoFocus />
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <button type="button" disabled={logoBusy} onClick={() => setShowLogoGenerateForm(false)} className="cosmic-logo-back-button rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-50">Back</button>
                                        <button type="button" disabled={logoBusy || logoCompanyName.trim().length < 2} onClick={generateTrialLogo} className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">{logoBusy ? 'Cosmic AI is creating…' : `${data.global_header?.logo_image_url && !String(data.global_header.logo_image_url).includes('your-logo.png') ? 'Regenerate Logo' : 'Generate Logo'} · ${trialActionCosts.generate_logo} Credits`}</button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}


            {themeFromLogoPreview && (
                <div className="fixed inset-0 z-[230] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-600">Cosmic AI Brand Match</p>
                                <h3 className="mt-1 text-xl font-bold text-slate-900">My Brand Theme preview</h3>
                                <p className="mt-1 text-sm text-slate-500">Cosmic AI built a complete custom color family from your logo. Applying it updates the saved My Brand Theme instead of creating duplicates.</p>
                            </div>
                            <button type="button" onClick={() => setThemeFromLogoPreview(null)} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close theme preview">✕</button>
                        </div>

                        <div className="mt-5 grid grid-cols-3 gap-3">
                            {[['Primary', themeFromLogoPreview.palette?.primary], ['Secondary', themeFromLogoPreview.palette?.secondary], ['Accent', themeFromLogoPreview.palette?.accent]].map(([label, hex]) => (
                                <div key={label} className="rounded-xl border border-slate-200 p-3">
                                    <div className="h-12 rounded-lg border border-black/5" style={{ backgroundColor: hex || '#ffffff' }} />
                                    <p className="mt-2 text-xs font-semibold text-slate-700">{label}</p>
                                    <p className="text-xs text-slate-500">{hex}</p>
                                </div>
                            ))}
                        </div>

                        <div className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <p className="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Custom Brand Theme</p>
                            <div className="mt-2 flex items-center gap-3">
                                <div className="h-12 w-12 rounded-xl border border-black/5" style={{ backgroundColor: themeFromLogoPreview.custom_theme?.palette?.background || themeFromLogoPreview.palette?.background || '#1e293b' }} />
                                <div>
                                    <p className="font-bold text-slate-900">{themeFromLogoPreview.custom_theme?.name || 'My Brand Theme'}</p>
                                    <p className="text-xs text-slate-600">{themeFromLogoPreview.reason}</p>
                                </div>
                            </div>
                        </div>

                        <p className="mt-4 text-xs leading-5 text-slate-500">Your exact HEX palette is saved as one reusable My Brand Theme. Running Match to Logo again updates this same My Brand Theme instead of adding another card.</p>

                        <div className="mt-6 flex justify-end gap-3">
                            <button type="button" onClick={() => setThemeFromLogoPreview(null)} className="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                            <button type="button" onClick={applyThemeFromLogo} className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Apply Theme</button>
                        </div>
                    </div>
                </div>
            )}

            {showTrialEmailModal && (
                <div className="fixed inset-0 z-[200] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm">
                    <form onSubmit={captureTrialEmailAndSave} className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                        <p className="text-xs font-bold uppercase tracking-[0.22em] text-emerald-600">Save your website</p>
                        <h2 className="mt-2 text-xl font-bold text-slate-950">Enter your email to save this trial</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-500">Your email is required when saving. We’ll also use it for your private Builder link. If you close this now, we’ll only ask again when you click Save.</p>
                        <input type="email" required autoFocus value={trialEmail} onChange={(event) => setTrialEmail(event.target.value)} placeholder="you@business.com" className="mt-5 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-950 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                        <div className="mt-5 flex justify-end gap-3">
                            <button type="button" onClick={() => { setShowTrialEmailModal(false); setTrialPurchasePending(false); }} className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Close</button>
                            <button type="submit" disabled={trialEmailSaving} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50">{trialEmailSaving ? 'Saving…' : 'Save'}</button>
                        </div>
                    </form>
                </div>
            )}

            {regenerating && (
                <div className="fixed inset-0 z-[260] grid place-items-center bg-white/75 px-5 text-center backdrop-blur-sm" role="status" aria-live="polite">
                    <div className="w-full max-w-2xl rounded-[28px] border border-emerald-200/90 bg-white/95 px-6 py-8 shadow-[0_35px_100px_-30px_rgba(15,23,42,.35)] ring-1 ring-white sm:px-10 sm:py-10">
                        <div className="relative mx-auto h-16 w-16" aria-hidden="true">
                            <div className="absolute inset-0 animate-spin rounded-full border-[3px] border-violet-200 border-t-violet-500 border-r-cyan-300 border-b-emerald-400" />
                            <div className="absolute inset-[3px] grid place-items-center rounded-full bg-white text-xl text-emerald-600 shadow-lg">✦</div>
                        </div>
                        <p className="mt-5 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Cosmic AI</p>
                        <h3 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Regenerating your page</h3>
                        <p className="mt-3 text-sm text-slate-600">{regenerateStage}</p>
                        <div className="mt-7 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            {[
                                ['Understand brief', 10],
                                ['Plan new layout', 30],
                                ['Create content', 68],
                                ['Build page', 94],
                            ].map(([label, threshold], index) => {
                                const complete = regenerateProgress >= threshold;
                                return (
                                    <div key={label} className={`flex items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-[10px] font-medium sm:text-xs ${complete ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-500'}`}>
                                        <span className={`grid h-4 w-4 shrink-0 place-items-center rounded-full text-[9px] ${complete ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500'}`}>{complete ? '✓' : index + 1}</span>
                                        <span className="leading-4">{label}</span>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="mt-6 h-2 overflow-hidden rounded-full bg-slate-200">
                            <div className="h-full rounded-full bg-gradient-to-r from-violet-500 via-cyan-400 to-emerald-400 transition-[width] duration-300" style={{ width: `${regenerateProgress}%` }} />
                        </div>
                        <div className="mt-3 flex items-center justify-between text-xs text-slate-600">
                            <span>Generating a fresh layout...</span>
                            <span>{regenerateProgress}%</span>
                        </div>
                    </div>
                </div>
            )}

            {showRegenerateModal && (
                <div className="fixed inset-0 z-[200] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm">
                    <form onSubmit={handleRegenerate} className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                        <p className="text-xs font-bold uppercase tracking-[0.22em] text-emerald-600">Regenerate trial website</p>
                        <h2 className="mt-2 text-xl font-bold text-slate-900">Describe the new direction</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-500">This rebuilds the trial from your new prompt: Sparks, content, images, brand direction, theme and navigation. The logo resets to Your Logo. Your current website is preserved if generation fails.</p>
                        <textarea required minLength={10} value={regeneratePrompt} onChange={(event) => setRegeneratePrompt(event.target.value)} rows={5} placeholder="Create a premium AI automation company for small businesses…" className="mt-5 w-full resize-none rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                        <div className="mt-5 flex justify-end gap-3">
                            <button type="button" onClick={() => setShowRegenerateModal(false)} className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</button>
                            <button type="submit" disabled={regenerating} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">{regenerating ? 'Regenerating…' : `Regenerate website · ${trialActionCosts.regenerate_page} Credits`}</button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}
