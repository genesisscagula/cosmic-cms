import { useEffect, useRef, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { confirmCosmicAction, showCosmicNotification } from '../../Components/CosmicNotification';
import CreditBalanceBadge from '../../Components/CosmicCredits/CreditBalanceBadge';
import { useCreditBalance } from '@/Hooks/useCreditBalance';

import AddSectionModal from "./Components/AddSectionModal";
import GeneratePageModal from "./Components/GeneratePageModal";

import ThemeSelector from "./Theme/ThemeSelector";
import { colorFamilies, installCustomBrandTheme } from "../../theme/colorFamilies";
import PageStyleSelector from "./PageStyle/PageStyleSelector";

import { BlockRegistry } from "./BlockRegistry";
import { BLOG_SPARK_GROUPS, FREE_BLOG_SPARKS } from "./Sparks/Blog";
import { DarkCyanHeader, GlassmorphismHeader } from './GenerateHeader';

import { MinimalFooter, DetailedFooter } from './GenerateFooter';

const MEDIA_FIELD_PATTERN = /(image|photo|avatar|poster|logo|video|media)/i;

const isWebsiteUploadedMedia = (value, websiteId) => {
    if (typeof value !== 'string' || !websiteId) return false;
    const normalized = value.trim();
    if (!normalized) return false;
    return normalized.includes(`/storage/websites/${websiteId}/`);
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


export default function Builder({ page, website, blogPosts: initialBlogPosts = [], hasWebsiteContent = false, websiteContext = "", websitePages = [], trialMode = false, trialToken = null, trialExperience = null, websiteMediaPack = null, trialCapabilities = {}, cosmicPricing = {}, pageStyle = 'auto', pageStyleOptions = [], themeAccess: builderThemeAccess = null }) {
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
        blocks: normalizeRenderKeys(page.blocks || []),
        global_header: props.globalHeaderBlock || page.website?.global_header || defaultHeader,
        global_footer: props.globalFooterBlock || page.website?.global_footer || { 
            type: 'minimal_footer',
            theme: 'white',
            logo_text: trialMode ? 'Your Logo' : (website?.name || 'Your Website'),
            logo_image_url: '/storage/branding/your-logo.png',
            logo_height: 36,
            logo_filter_key: 'midnight',
            copyright: '© 2026. All rights reserved.'
        }
    });

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isGeneratePageOpen, setIsGeneratePageOpen] = useState(false);
    const [aiResult, setAiResult] = useState(null);
    const [aiLoading, setAiLoading] = useState(false);

    const [themeMenu, setThemeMenu] = useState(null);
    const [layoutMenu, setLayoutMenu] = useState(null);
    const [layoutApplying, setLayoutApplying] = useState(null);
    const [sparkCatalog, setSparkCatalog] = useState([]);
    const [sparkInsertTarget, setSparkInsertTarget] = useState(null);
    const [isSaving, setIsSaving] = useState(false);
    const [isPublishing, setIsPublishing] = useState(false);
    const [saveError, setSaveError] = useState('');
    const [hasUnsavedTheme, setHasUnsavedTheme] = useState(false);
    const [showTrialEmailModal, setShowTrialEmailModal] = useState(false);
    const [trialEmail, setTrialEmail] = useState(trialExperience?.email || '');
    const [trialEmailCaptured, setTrialEmailCaptured] = useState(Boolean(trialExperience?.email_captured));
    const [trialEmailSaving, setTrialEmailSaving] = useState(false);
    const [showRegenerateModal, setShowRegenerateModal] = useState(false);
    const [regeneratePrompt, setRegeneratePrompt] = useState('');
    const [regenerating, setRegenerating] = useState(false);
    const [regenerateProgress, setRegenerateProgress] = useState(0);
    const [regenerateStage, setRegenerateStage] = useState('Understanding your new direction...');
    const [regenerationUsed, setRegenerationUsed] = useState(Number(trialExperience?.regenerations_used || 0));
    const [showLogoModal, setShowLogoModal] = useState(false);
    const [showLogoGenerateForm, setShowLogoGenerateForm] = useState(false);
    const [logoCompanyName, setLogoCompanyName] = useState(trialExperience?.logo_company_name || website?.name || '');
    const [logoBusy, setLogoBusy] = useState(false);
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
    const logoUploadRef = useRef(null);
    const [pageStatus, setPageStatus] = useState(page.status || 'draft');
    const [publishError, setPublishError] = useState(page.publish_error || '');
    const [blogPosts, setBlogPosts] = useState(initialBlogPosts);
    const [currentPageStyle, setCurrentPageStyle] = useState(pageStyle || 'auto');
    const [styleOptions, setStyleOptions] = useState(pageStyleOptions || []);
    const hasUnsavedChanges = isDirty || hasUnsavedTheme;

    useEffect(() => {
        const serverBalance = Number(cosmicPricing?.balance);
        if (Number.isFinite(serverBalance)) {
            setCreditBalance(serverBalance);
        }
    }, [cosmicPricing?.balance, setCreditBalance]);

    useEffect(() => {
        const warnBeforeLeaving = (event) => {
            if (!hasUnsavedChanges || isSaving || isPublishing) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', warnBeforeLeaving);

        return () => window.removeEventListener('beforeunload', warnBeforeLeaving);
    }, [hasUnsavedChanges, isPublishing, isSaving]);

    useEffect(() => {
        if (!capabilities.canManageBlocks) return;

        axios.get('/sparks/catalog')
            .then(({ data: responseData }) => setSparkCatalog(responseData.sparks || []))
            .catch(() => setSparkCatalog([]));
    }, [capabilities.canManageBlocks]);

    const trialActionCosts = {
        page_style: Number(cosmicPricing?.trial_actions?.page_style || 20),
        regenerate_page: Number(cosmicPricing?.trial_actions?.regenerate_page || 50),
        generate_logo: Number(cosmicPricing?.trial_actions?.generate_logo || 50),
        match_logo_to_theme: Number(cosmicPricing?.trial_actions?.match_logo_to_theme || 50),
        match_theme_to_logo: Number(cosmicPricing?.trial_actions?.match_theme_to_logo || 50),
    };

    const creditMessage = (cost) => `Cost: ${cost} Cosmic Credits. Balance: ${creditBalance} → ${Math.max(0, Number(creditBalance || 0) - cost)}.`;

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
            setGlobalSelections((prev) => ({
                ...prev,
                primary: theme,
                ...(hasRealLogo ? (brandThemeMatchesCurrentLogo
                    ? { logo_theme_sync_state: 'synced', logo_theme_sync_source: 'theme_to_logo', logo_theme_synced_theme: theme }
                    : { logo_theme_sync_state: 'theme_changed', logo_theme_sync_source: 'manual_theme_change', logo_theme_synced_theme: null }) : {}),
            }));
            if (hasRealLogo) setLogoSyncState(brandThemeMatchesCurrentLogo ? 'synced' : 'theme_changed');
            setHasUnsavedTheme(true);
        } catch (error) {
            showCosmicNotification({ title: 'Theme change unavailable', message: error.response?.data?.message || 'Cosmic could not apply this theme.', tone: 'error' });
        }
    };

    const applyTrialLogo = (url, companyName = null) => {
        const nextLogoText = companyName || data.global_header?.logo_text || website?.name || 'Your Logo';
        const nextHeader = {
            ...data.global_header,
            logo_image_url: url,
            logo_text: nextLogoText,
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
        setData({
            ...data,
            global_header: nextHeader,
            global_footer: nextFooter,
        });
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
            applyTrialLogo(response.data.url);
            setLogoSyncState('logo_changed');
            setGlobalSelections((prev) => ({ ...prev, logo_theme_sync_state: 'logo_changed', logo_theme_sync_source: 'upload', logo_theme_synced_theme: null }));
            setHasUnsavedTheme(true);
            showCosmicNotification({ title: 'Logo uploaded', message: trialMode ? 'Your logo is now applied to this trial website.' : 'Your logo is now applied. Save the Builder to keep it.', tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to upload logo', message: error.response?.data?.message || 'Please try another logo file.', tone: 'error' });
        } finally {
            setLogoBusy(false);
        }
    };

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
        setLogoBusy(true);
        try {
            const response = trialMode
                ? await axios.post(route('trial-branding.logo.generate', trialToken), { company_name: logoCompanyName.trim() })
                : await axios.post(route('websites.logo.generate', website.id), {
                    company_name: logoCompanyName.trim(),
                    primary: globalSelections?.primary,
                    primary_hex: globalSelections?.primary === 'my-brand' ? globalSelections?.custom_brand_theme?.palette?.background : undefined,
                });
            applyTrialLogo(response.data.url, response.data.company_name);
            setLogoSyncState('synced');
            setGlobalSelections((prev) => ({ ...prev, logo_theme_sync_state: 'synced', logo_theme_sync_source: 'generated_from_theme', logo_theme_synced_theme: prev.primary }));
            setHasUnsavedTheme(true);
            if (trialMode) {
                setLogoRegenerationsUsed(Number(response.data.regenerations_used_today || logoRegenerationsUsed));
                if (Number.isFinite(Number(response.data.credit_balance))) setCreditBalance(Number(response.data.credit_balance));
            } else if (Number.isFinite(Number(response.data.balance))) {
                setCreditBalance(Number(response.data.balance));
            }
            showCosmicNotification({ title: 'Logo generated', message: trialMode ? 'Cosmic AI created and applied a logo for your trial website.' : `Cosmic AI created and applied your logo. ${response.data.cost || 50} credits used.`, tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to generate logo', message: error.response?.data?.message || 'Cosmic AI could not create the logo. Please try again.', tone: 'error' });
        } finally {
            setLogoBusy(false);
        }
    };


    const matchLogoToTheme = async () => {
        const logoUrl = data.global_header?.logo_image_url;
        if (!logoUrl || String(logoUrl).includes('your-logo.png')) return;
        const themeKey = globalSelections?.primary || 'midnight';
        const family = colorFamilies[themeKey] || colorFamilies.midnight;
        const payload = {
            logo_url: logoUrl,
            theme_name: family?.name || themeKey,
            primary_hex: family?.palette?.background || '#243447',
            accent_hex: family?.palette?.accent || family?.palette?.background || '#60A5FA',
        };

        const matchCost = trialMode ? trialActionCosts.match_logo_to_theme : 50;
        const matchConfirmed = await confirmCosmicAction({
            title: 'Match logo to this theme?',
            message: creditMessage(matchCost),
            confirmLabel: `Use ${matchCost} Credits`,
        });
        if (!matchConfirmed) return;

        setLogoBusy(true);
        try {
            const response = trialMode
                ? await axios.post(route('trial-branding.logo.match-theme', trialToken), payload)
                : await axios.post(route('websites.logo.match-theme', website.id), payload);

            applyTrialLogo(response.data.url);
            setLogoSyncState('synced');
            setGlobalSelections((prev) => ({ ...prev, logo_theme_sync_state: 'synced', logo_theme_sync_source: 'logo_to_theme', logo_theme_synced_theme: prev.primary }));
            setHasUnsavedTheme(true);
            if (trialMode) {
                setLogoRegenerationsUsed(Number(response.data.regenerations_used_today || logoRegenerationsUsed));
                if (Number.isFinite(Number(response.data.credit_balance))) setCreditBalance(Number(response.data.credit_balance));
            } else if (Number.isFinite(Number(response.data.balance))) {
                setCreditBalance(Number(response.data.balance));
            }
            showCosmicNotification({
                title: 'Logo matched to theme',
                message: trialMode
                    ? `Your logo now matches ${family?.name || 'the current theme'}.`
                    : `Your logo now matches ${family?.name || 'the current theme'}. ${response.data.cost || 50} credits used.`,
                tone: 'success',
            });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to match logo', message: error.response?.data?.message || 'Cosmic AI could not match this logo to the current theme.', tone: 'error' });
        } finally {
            setLogoBusy(false);
        }
    };

    const matchThemeToLogo = async () => {
        const logoUrl = data.global_header?.logo_image_url;
        if (!logoUrl || String(logoUrl).includes('your-logo.png')) return;

        const analysisCost = trialMode ? trialActionCosts.match_theme_to_logo : 50;
        const analysisConfirmed = await confirmCosmicAction({
            title: 'Match theme to this logo?',
            message: creditMessage(analysisCost),
            confirmLabel: `Use ${analysisCost} Credits`,
        });
        if (!analysisConfirmed) return;

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
            setThemeFromLogoPreview(response.data);
        } catch (error) {
            showCosmicNotification({ title: 'Unable to analyze logo', message: error.response?.data?.message || 'Cosmic AI could not build a theme from this logo.', tone: 'error' });
        } finally {
            setLogoBusy(false);
        }
    };

    const applyThemeFromLogo = async () => {
        const customTheme = themeFromLogoPreview?.custom_theme;
        if (!customTheme) return;
        const family = 'my-brand';
        try {
            installCustomBrandTheme(customTheme);
            if (trialMode) {
                const response = await axios.post(route('trial-pages.theme.apply', { trial: trialToken, page: page.id }), { theme: family, sync_source: 'theme_to_logo' });
                if (Number.isFinite(Number(response.data.credit_balance))) setCreditBalance(Number(response.data.credit_balance));
            }
            setGlobalSelections((current) => ({
                ...current,
                primary: family,
                custom_brand_theme: customTheme,
                brand_palette: customTheme.palette || themeFromLogoPreview.palette,
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
        if (!capabilities.canEditGlobalShell) return;
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
        const newBlock = {
            ...block,
            theme: block.theme || "auto",
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

        console.log("Before:", data.blocks.length);
        console.log("After:", updatedBlocks.length);

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
            return true;
        } catch (error) {
            console.error(error);
            setSaveError(
                error.response?.data?.message ||
                'Unable to save your changes. Please try again.'
            );
            return false;
        } finally {
            setIsSaving(false);
        }
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
                showCosmicNotification({
                    title: 'Your page is saved',
                    message: 'We sent your private editing link to your email.',
                    tone: 'success',
                });
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
            { at: 30, text: 'Choosing a different layout and Sparks...' },
            { at: 68, text: 'Writing fresh page content...' },
            { at: 88, text: 'Rebuilding your page...' },
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
        if (!trialEmailCaptured) {
            setShowRegenerateModal(false);
            setShowTrialEmailModal(true);
            return;
        }
        if (!regeneratePrompt.trim()) return;
        const regenCost = trialActionCosts.regenerate_page;
        const confirmed = await confirmCosmicAction({
            title: 'Regenerate this page?',
            message: `${creditMessage(regenCost)} Your current page stays safe if generation fails.`,
            confirmLabel: `Use ${regenCost} Credits`,
        });
        if (!confirmed) return;
        setRegenerating(true);
        setSaveError('');
        try {
            const response = await axios.post(route('trial-generations.regenerate', trialToken), { prompt: regeneratePrompt.trim() });
            setRegenerationUsed((value) => value + 1);
            setRegenerateProgress(100);
            setRegenerateStage('Your new layout is ready.');
            window.setTimeout(() => window.location.assign(response.data.redirect_url), 250);
        } catch (error) {
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

    const handlePublish = async () => {
        setPublishError('');

        // Always persist the exact Builder state first. This keeps publishing
        // reliable after AI generation, adding/removing blocks, layout changes,
        // header/footer edits, and theme changes—even when React's dirty state
        // has not finished updating yet.
        const saved = await saveDraft();

        if (!saved) {
            showCosmicNotification({
                title: 'Save required before publishing',
                message: 'Your latest Builder changes could not be saved, so publishing was stopped.',
                tone: 'error',
            });
            return;
        }

        setIsPublishing(true);

        try {
            const response = await axios.post(route('pages.publish', page.id));

            setPageStatus(response.data.status || 'published');
            setCreditBalance(response.data.credit_balance);
            setPublishError('');
            showCosmicNotification({
                title: 'Page published',
                message: 'The latest Builder version is now live.',
                tone: 'success',
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

    useEffect(() => {
        if (globalSelections?.custom_brand_theme) {
            installCustomBrandTheme(globalSelections.custom_brand_theme);
        }
    }, [globalSelections?.custom_brand_theme]);


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

        if (block.theme && block.theme !== "auto") {
            return block.theme;
        }

        const activeStyle = currentPageStyle === 'auto'
            ? null
            : styleOptions.find((style) => style.key === currentPageStyle);
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
        const ownedKeys = new Set(
            sparkCatalog.filter((spark) => spark.owned).map((spark) => spark.key)
        );

        if (!category) {
            return [];
        }

        return Object.entries(BlockRegistry)
            .filter(([type, registryItem]) =>
                registryItem?.schema?.category === category &&
                (type === blockType || ownedKeys.has(type))
            )
            .map(([type, registryItem]) => ({
                id: type,
                type,
                title: registryItem.schema?.title || type.replaceAll('_', ' '),
                kind: 'block-type',
            }));
    };

    const isCurrentLayout = (block, layout) => layout.kind === 'blog-variant'
        ? (block.layout_variant || BlockRegistry[block.type]?.schema?.defaults?.layout_variant) === layout.layoutVariant
        : block.type === layout.type;

    const changeBlockLayout = (index, layout) => {
        const currentBlock = data.blocks[index];

        if (!currentBlock || isCurrentLayout(currentBlock, layout)) {
            setLayoutMenu(null);
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


    const renderBlock = (block, index) => {

        const resolvedTheme = resolveBlockTheme(block, index);

        const blockProps = {

            block: {
                ...block,
                resolvedTheme
            },

            globalTheme: globalSelections,

            onUpdate: (fields) => updateBlockContent(index, fields),

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
            {trialMode && (
                <div className="border-b border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-cyan-50 px-4 py-3 text-center">
                    <p className="text-sm font-semibold text-slate-900">Turn this landing page into a complete website.</p>
                    <p className="mt-0.5 text-xs text-slate-600">Sign up to generate more pages, unlock premium tools, and publish your business online. <a href={`${route('pricing')}?token=${encodeURIComponent(trialToken)}`} className="font-bold text-emerald-700 hover:text-emerald-800">Create free account →</a></p>
                </div>
            )}
            <Head title={`Builder — ${page.title}`} />
            <div className={`cosmic-builder-shell min-h-screen ${trialMode ? 'bg-slate-100 text-slate-900' : 'bg-[#09090b] text-slate-100'}`}>
                <header data-cosmic-builder-header className={`sticky top-0 z-[60] backdrop-blur-xl ${trialMode ? 'border-b border-slate-200 bg-white/95' : 'border-b border-white/10 bg-[#09090b]/95'}`}>
                    <div className={`mx-auto max-w-[1760px] px-4 py-3 sm:px-6 ${trialMode ? 'flex min-h-[76px] flex-wrap items-center justify-between gap-3' : 'grid min-h-[64px] grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-4'}`}>
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

                            {(trialMode || capabilities.canGenerateAi) && (
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
                                        setCurrentPageStyle(response.page_style || 'auto');
                                        setStyleOptions(response.suggestions || styleOptions);
                                        setPageStatus(response.page_status || 'draft');
                                        setCreditBalance(response.credit_balance);
                                        setPublishError('');
                                    }}
                                />
                            )}

                            {!trialMode && capabilities.canGenerateAi && (
                                <button
                                    type="button"
                                    onClick={() => setIsGeneratePageOpen(true)}
                                    className="cosmic-generate-page-trigger hidden h-9 shrink-0 items-center gap-1.5 rounded-lg border border-violet-400/25 bg-violet-500/10 px-3 text-xs font-semibold text-violet-100 transition hover:border-violet-400/40 hover:bg-violet-500/20 focus:outline-none focus:ring-2 focus:ring-violet-400 lg:inline-flex"
                                >
                                    <span className="cosmic-generate-page-icon" aria-hidden="true">✦</span>
                                    Generate Page
                                </button>
                            )}
                        </div>

                        <div className={`${trialMode ? 'order-3 flex w-full items-center justify-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 sm:order-none sm:w-auto' : 'hidden items-center gap-1 rounded-xl border border-white/10 bg-white/[0.035] p-1 xl:flex'}`}>
                            <span title={publishError || saveError || undefined} className={`inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-[11px] font-medium ${isPublishing ? 'text-sky-200' : publishError ? 'text-red-200' : pageStatus === 'published' ? 'cosmic-published-status text-emerald-200' : 'text-amber-200'}`}>
                                <span className={`h-1.5 w-1.5 rounded-full ${isPublishing ? 'animate-pulse bg-sky-300' : publishError ? 'bg-red-300' : pageStatus === 'published' ? 'bg-emerald-300' : 'bg-amber-300'}`} />
                                {isPublishing ? 'Publishing…' : publishError ? 'Publish failed' : pageStatus === 'published' ? 'Published' : 'Draft'}
                            </span>
                            <span className={`h-4 w-px ${trialMode ? 'bg-slate-200' : 'bg-white/10'}`} aria-hidden="true" />
                            <span className={`inline-flex h-8 items-center rounded-lg px-2.5 text-[11px] font-medium ${trialMode ? 'text-slate-600' : 'text-slate-400'}`}>
                                {data.blocks.length} Sparks
                            </span>

                        </div>

                        <div className={`flex min-w-0 items-center justify-end gap-2 ${trialMode ? 'ml-auto' : ''}`}>
                            {trialMode ? (
                                <span title="Guest Cosmic Credits" className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 text-xs font-extrabold text-amber-800">
                                    <span aria-hidden="true">⚡</span><span>{Number(creditBalance || 0).toLocaleString()}</span><span className="hidden font-semibold sm:inline">Guest Credits</span>
                                </span>
                            ) : (
                                <CreditBalanceBadge
                                    balance={creditBalance}
                                    className="cosmic-builder-credit h-9 px-3"
                                />
                            )}

                            {capabilities.canChangeTheme && (
                                <ThemeSelector
                                    compact
                                    value={globalSelections.primary}
                                    themeAccess={themeAccess}
                                    signupUrl={trialMode && trialToken ? `${route('pricing')}?token=${encodeURIComponent(trialToken)}` : null}
                                    customTheme={globalSelections?.custom_brand_theme}
                                    onChange={handleThemeChange}
                                />
                            )}

                            {capabilities.canGenerateAi && (
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
                                    disabled={regenerating || regenerationUsed >= 2}
                                    className="cosmic-trial-regenerate inline-flex h-9 shrink-0 items-center justify-center rounded-lg border px-3 text-xs font-semibold transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed"
                                    title={`${Math.max(0, 2 - regenerationUsed)} free regenerations remaining this week`}
                                >
                                    {regenerating ? 'Regenerating…' : `Regenerate (${Math.max(0, 2 - regenerationUsed)} left)`}
                                </button>
                            )}

                            {capabilities.canSave && (
                                <form onSubmit={handleSubmit}>
                                    <button
                                        type="submit"
                                        disabled={isSaving || isPublishing}
className={`cosmic-builder-save ${trialMode ? 'cosmic-trial-save' : ''} inline-flex h-9 shrink-0 items-center justify-center rounded-lg border px-4 text-sm font-semibold shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 disabled:cursor-not-allowed`}                                    >
                                        {isSaving ? 'Saving…' : trialMode ? 'Save changes' : 'Save'}
                                    </button>
                                </form>
                            )}

                            {capabilities.canPurchase && trialToken && (
                                <a
                                    href={`${route('pricing')}?token=${encodeURIComponent(trialToken)}`}
                                    className="cosmic-trial-buy inline-flex h-10 shrink-0 items-center justify-center rounded-xl px-5 text-sm font-bold shadow-sm transition focus:outline-none focus:ring-2"
                                >
                                    Buy website
                                </a>
                            )}

                            {capabilities.canPublish && (
                                <button
                                    type="button"
                                    onClick={handlePublish}
                                    disabled={isSaving || isPublishing}
                                    className="cosmic-primary-action inline-flex h-9 shrink-0 items-center rounded-lg bg-emerald-600 px-3.5 text-xs font-bold text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {isPublishing ? 'Publishing…' : 'Publish'}
                                </button>
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
                    <style>{`html.cosmic-header-menu-active .cosmic-block-toolbar { opacity: 0 !important; pointer-events: none !important; }`}</style>
                    <div className={`cosmic-builder-canvas mx-auto w-full max-w-[1560px] overflow-visible rounded-xl bg-white shadow-2xl lg:w-[min(86vw,1560px)] ${trialMode ? 'border border-slate-200 shadow-slate-300/60' : 'border border-white/10 shadow-black/30'}`}>
                        <div className="flex w-full flex-col items-stretch overflow-hidden rounded-[11px]">
                    
                    {/* GI-PASSED ANG UPDATED STATE UG FUNCTION SA HEADER */}
                    {data.global_header && (
                        <div className="w-full bg-white z-40">
                            {data.global_header.type === 'dark_cyan_header' && (
                                <DarkCyanHeader block={data.global_header} onUpdate={updateHeader} pageTargets={websitePages} onLogoClick={() => setShowLogoModal(true)} />
                            )}
                            {data.global_header.type === 'glassmorphism_header' && (
                                <GlassmorphismHeader
                                    block={data.global_header}
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
                            className="relative group w-full transition-all duration-300 focus-within:z-20"
                        >

                            {/* Hover Toolbar */}

                            {capabilities.canManageBlocks && (
                            <div className="cosmic-block-toolbar absolute top-5 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-all duration-300 z-50">

                                <div className="flex items-center gap-2 rounded-full bg-slate-900/90 backdrop-blur-xl border border-slate-700 shadow-2xl px-3 py-2">

                                    {/* Block Label */}

                                    <div className="flex flex-col leading-none px-2">

                                        <span className="text-[10px] uppercase tracking-[0.2em] text-slate-400 font-bold">

                                            {block.type.replaceAll("_"," ")}

                                        </span>

                                        <span className="mt-1 text-[9px] uppercase tracking-[0.2em] font-bold text-amber-300">

                                            {block.theme === "auto" && "✨ Auto"}
                                            {block.theme === "primary" && "🟦 Primary"}
                                            {block.theme === "white" && "⬜ White"}
                                            {block.theme === "surface" && "🩶 Surface"}
                                            {block.theme === "accent" && "🟪 Accent"}

                                        </span>

                                    </div>

                                    <div className="w-px h-5 bg-slate-700" />

                                    {/* Move Up */}

                                    <button
                                        type="button"
                                        aria-label="Move block up"
                                        onClick={() => moveBlock(index,"up")}
                                        disabled={index===0}
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 disabled:opacity-30 transition focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        ↑
                                    </button>

                                    {/* Move Down */}

                                    <button
                                        type="button"
                                        aria-label="Move block down"
                                        onClick={() => moveBlock(index,"down")}
                                        disabled={index===data.blocks.length-1}
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 disabled:opacity-30 transition focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        ↓
                                    </button>

                                    {/* Duplicate */}

                                    <button
                                        type="button"
                                        aria-label="Duplicate block"
                                        onClick={() => duplicateBlock(index)}
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 transition focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        ⧉
                                    </button>

                                    {/* Change Spark */}

                                    <button
                                        type="button"
                                        aria-label={`Change ${BlockRegistry[block.type]?.schema?.category || 'section'} Spark`}
                                        title={getCompatibleLayouts(block.type).length > 1
                                            ? `Change ${BlockRegistry[block.type]?.schema?.category || 'section'} Spark`
                                            : 'Add another owned Spark in this category to enable changing'}
                                        disabled={getCompatibleLayouts(block.type).length < 2}
                                        onClick={() => {
                                            setThemeMenu(null);
                                            setLayoutMenu(layoutMenu === index ? null : index);
                                        }}
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 transition focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-30"
                                    >
                                        <span aria-hidden="true">&#8644;</span>
                                    </button>

                                    {layoutMenu === index && (
                                        <div className="absolute top-12 right-10 z-50 w-64 overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
                                            <div className="border-b border-slate-700 px-4 py-3">
                                                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Change Spark</p>
                                                <p className="mt-1 text-xs font-semibold text-white">{BLOG_SPARK_GROUPS[block.type] ? 'Choose from 3 free Blog Sparks' : `Owned ${BlockRegistry[block.type]?.schema?.category || 'Section'} Sparks`}</p>
                                            </div>

                                            <div className="max-h-64 overflow-y-auto py-1 [scrollbar-color:rgb(100_116_139)_transparent] [scrollbar-width:thin] [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-700 hover:[&::-webkit-scrollbar-thumb]:bg-violet-500/70">
                                                {getCompatibleLayouts(block.type).map((layout) => {
                                                    const current = isCurrentLayout(block, layout);

                                                    return (
                                                        <button
                                                            key={layout.id}
                                                            type="button"
                                                            disabled={current}
                                                            onClick={() => changeBlockLayout(index, layout)}
                                                            aria-current={current ? "true" : undefined}
                                                            className={`group/layout flex w-full items-center gap-3 border-l-2 px-4 py-3 text-left text-sm transition-all duration-200 focus:outline-none ${current
                                                                ? 'cursor-default border-violet-400 bg-violet-500/10 text-white'
                                                                : 'border-transparent text-slate-200 hover:border-violet-400/60 hover:bg-slate-800 focus:bg-slate-800'}`}
                                                        >
                                                            {layout.kind === 'blog-variant' && (
                                                                <span className={`grid h-10 w-12 shrink-0 gap-1 rounded-lg border p-1.5 transition ${current ? 'border-violet-400/40 bg-violet-400/10' : 'border-slate-700 bg-slate-950 group-hover/layout:border-slate-500'}`} aria-hidden="true">
                                                                    <span className={`rounded-sm ${layout.preview === 'center' || layout.preview === 'compact' ? 'mx-auto w-7' : 'w-full'} bg-slate-500/70`} />
                                                                    <span className={`rounded-sm bg-slate-700 ${layout.preview === 'split' || layout.preview === 'stacked' ? 'w-2/3' : 'w-full'}`} />
                                                                    <span className={`rounded-sm bg-slate-700 ${layout.preview === 'magazine' || layout.preview === 'cards' ? 'grid grid-cols-2 gap-0.5' : ''}`} />
                                                                </span>
                                                            )}
                                                            <span className="min-w-0 flex-1">
                                                                <span className="block truncate font-semibold">{layout.title}</span>
                                                                {layout.kind === 'blog-variant' && (
                                                                    <span className="mt-0.5 block text-[10px] leading-4 text-slate-500">{layout.description || 'Free Blog Spark'}</span>
                                                                )}
                                                            </span>
                                                            {current && (
                                                                <span className="rounded-full bg-violet-400/15 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-violet-300">Current</span>
                                                            )}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}

                                    {/* Insert owned Spark above */}
                                    <button
                                        type="button"
                                        aria-label="Insert Spark above"
                                        title="Insert owned Spark above"
                                        onClick={() => {
                                            setThemeMenu(null);
                                            setLayoutMenu(null);
                                            setSparkInsertTarget({ index, position: 'above' });
                                            setIsModalOpen(true);
                                        }}
                                        className="w-8 h-8 rounded-lg hover:bg-emerald-500/15 text-emerald-300 transition focus:outline-none focus:ring-2 focus:ring-emerald-400"
                                    >
                                        <span aria-hidden="true">↥</span>
                                    </button>

                                    {/* Insert owned Spark below */}
                                    <button
                                        type="button"
                                        aria-label="Insert Spark below"
                                        title="Insert owned Spark below"
                                        onClick={() => {
                                            setThemeMenu(null);
                                            setLayoutMenu(null);
                                            setSparkInsertTarget({ index, position: 'below' });
                                            setIsModalOpen(true);
                                        }}
                                        className="w-8 h-8 rounded-lg hover:bg-violet-500/15 text-violet-300 transition focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        <span aria-hidden="true">↧</span>
                                    </button>

                                    {/* Theme */}

                                    <button
                                        type="button"
                                        aria-label="Change block theme"
                                        onClick={() => {
                                            setLayoutMenu(null);
                                            setThemeMenu(themeMenu === index ? null : index);
                                        }}
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 transition focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        🎨
                                    </button>

                                    {
                                    themeMenu === index && (

                                        <div className="absolute top-12 right-0 w-48 rounded-xl bg-slate-900 border border-slate-700 shadow-2xl overflow-hidden">

                                            {[
                                                ["auto","✨ Auto"],
                                                ["primary","🟦 Primary"],
                                                ["white","⬜ White"],
                                                ["surface","🩶 Surface"],
                                                ["accent","🟪 Accent"]
                                            ].map(([value,label]) => (

                                                <button

                                                    key={value}
                                                    type="button"

                                                    onClick={() => {

                                                        const blocks=[...data.blocks];

                                                        blocks[index]={
                                                            ...blocks[index],
                                                            theme:value
                                                        };

                                                        setData("blocks",blocks);

                                                        setThemeMenu(null);

                                                    }}

                                                    className="w-full text-left px-4 py-3 hover:bg-slate-800 text-sm text-slate-200 transition focus:outline-none focus:bg-slate-800"

                                                >

                                                    {label}

                                                </button>

                                            ))}

                                        </div>

                                    )
                                }

                                    {/* Delete */}

                                    <button
                                        type="button"
                                        aria-label="Delete block"
                                        onClick={() => removeBlock(index)}
                                        className="w-8 h-8 rounded-lg hover:bg-red-500/20 text-red-400 transition focus:outline-none focus:ring-2 focus:ring-red-400"
                                    >
                                        🗑
                                    </button>

                                </div>

                            </div>
                            )}

                            {/* Selected Outline */}

                            <div className={`cosmic-builder-spark group-hover:ring-2 group-hover:ring-violet-500/40 transition-all duration-500 ${layoutApplying === index ? "scale-[0.997] opacity-80 ring-2 ring-violet-400/40" : "opacity-100"}`}>

                                {renderBlock(block,index)}

                            </div>

                        </div>

                    ))}

                    {data.blocks.length === 0 && (
                        <section className="flex min-h-[300px] items-center justify-center border-y border-slate-200 bg-slate-100 px-6 py-12 text-center">
                            <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white/75 px-6 py-7 shadow-sm">
                                <span className="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-lg text-white" aria-hidden="true">+</span>
                                <h2 className="mt-4 text-lg font-semibold text-slate-900">Start building this page</h2>
                                <p className="mt-2 text-sm leading-6 text-slate-500">Add an owned Spark with quick content, or personalize it with Cosmic AI. Your global header and footer are already in place.</p>
                                {capabilities.canGenerateAi && (
                                    <button type="button" onClick={() => setIsModalOpen(true)} className="mt-5 inline-flex h-10 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-violet-400">
                                        Add your first Spark
                                    </button>
                                )}
                            </div>
                        </section>
                    )}

                    {/* FOOTER RENDERER */}
                    {data.global_footer && (
                        <div className="relative group w-full mt-auto">
                            
                            {/* Footer Theme Dropdown (Upper Left) */}

                            {/* Footer Components */}
                            {data.global_footer.type === 'minimal_footer' && (
                                <MinimalFooter block={data.global_footer} onUpdate={updateFooter} />
                            )}
                            {data.global_footer.type === 'detailed_footer' && (
                                <DetailedFooter block={data.global_footer} onUpdate={updateFooter} />
                            )}
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

                            <ThemeSelector
                                value={globalSelections.primary}
                                themeAccess={themeAccess}
                                customTheme={globalSelections?.custom_brand_theme}
                                onChange={(theme) => {

                                    setGlobalSelections(prev => ({
                                        ...prev,
                                        primary: theme
                                    }));

                                    setHasUnsavedTheme(true);

                                }}
                            />

                            {/* AI */}
                            <button
                                type="button"
                                onClick={() => setIsModalOpen(true)}
                                className="px-6 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:scale-[1.02] hover:shadow-xl transition text-white font-bold"
                            >
                                ✨ Add Spark
                            </button>

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
            
            {capabilities.canGenerateAi && (
                <AddSectionModal
                    open={isModalOpen}
                    onClose={() => { setIsModalOpen(false); setSparkInsertTarget(null); }}
                    onAdd={addBlock}
                    hasBlocks={(data.blocks?.length ?? 0) > 0}
                    hasWebsiteContent={hasWebsiteContent}
                    websiteContext={websiteContext}
                    websiteId={website?.id}
                    cosmicPricing={cosmicPricing}
                    websiteTheme={globalSelections}
                    ownedOnly={Boolean(sparkInsertTarget)}
                    contextLabel={sparkInsertTarget ? `Insert Spark ${sparkInsertTarget.position}` : null}
                    onOwnershipChanged={(sparkKey) => setSparkCatalog((current) => current.map((spark) => spark.key === sparkKey ? { ...spark, owned: true } : spark))}
                />
            )}

            {capabilities.canGenerateAi && (
                <GeneratePageModal
                    open={isGeneratePageOpen}
                    onClose={() => setIsGeneratePageOpen(false)}
                    onReplace={replaceBlocks}
                    websiteContext={websiteContext}
                    websiteId={website?.id}
                />
            )}

            {/* AI MODAL INJECTOR CONFIG */}
            
            {showLogoModal && (
                <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
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
                                    <button type="button" disabled={logoBusy} onClick={() => setShowLogoGenerateForm(true)} className="rounded-xl bg-emerald-600 px-5 py-5 text-left text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60">
                                        <span className="block text-base font-bold">✨ {data.global_header?.logo_image_url && !String(data.global_header.logo_image_url).includes('your-logo.png') ? 'Regenerate Logo' : 'Generate Logo'}</span>
                                        <span className="mt-1 block text-xs text-emerald-50">Let Cosmic AI create a logo for this website.</span>
                                    </button>
                                    <button type="button" disabled={logoBusy} onClick={() => logoUploadRef.current?.click()} className="rounded-xl border border-slate-200 bg-slate-50 px-5 py-5 text-left text-slate-900 transition hover:border-emerald-300 hover:bg-emerald-50 disabled:opacity-60">
                                        <span className="block text-base font-bold">↑ {data.global_header?.logo_image_url && !String(data.global_header.logo_image_url).includes('your-logo.png') ? 'Replace Logo' : 'Upload Logo'}</span>
                                        <span className="mt-1 block text-xs text-slate-500">SVG, PNG, JPG or WebP up to 2 MB.</span>
                                    </button>
                                    <input ref={logoUploadRef} type="file" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp" onChange={uploadTrialLogo} className="hidden" />
                                    {data.global_header?.logo_image_url && !String(data.global_header.logo_image_url).includes('your-logo.png') && logoSyncState && logoSyncState !== 'synced' && (
                                        <div className="sm:col-span-2 mt-1 border-t border-slate-200 pt-4">
                                            <p className="mb-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Brand Matching</p>
                                            {logoSyncState === 'theme_changed' ? (
                                                <button type="button" disabled={logoBusy || (trialMode && logoRegenerationsUsed >= 2)} onClick={matchLogoToTheme} className="w-full rounded-xl bg-slate-900 px-5 py-4 text-left text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">
                                                    <span className="block text-sm font-bold">🎨 Match Logo to Theme</span>
                                                    <span className="mt-1 block text-xs text-slate-300">You changed the website theme. Update the logo to the exact active theme color.</span>
                                                </button>
                                            ) : (
                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    <button type="button" disabled={logoBusy} onClick={matchThemeToLogo} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 text-left text-slate-900 transition hover:border-emerald-300 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50">
                                                        <span className="block text-sm font-bold">✨ Match Theme to Logo</span>
                                                        <span className="mt-1 block text-xs text-slate-500">Build or update My Brand Theme from this logo and save it to your website.</span>
                                                    </button>
                                                    <button type="button" disabled={logoBusy || (trialMode && logoRegenerationsUsed >= 2)} onClick={matchLogoToTheme} className="w-full rounded-xl bg-slate-900 px-5 py-4 text-left text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">
                                                        <span className="block text-sm font-bold">🎨 Match Logo to Theme</span>
                                                        <span className="mt-1 block text-xs text-slate-300">Keep this website theme and recolor the logo to its exact primary HEX.</span>
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                    <p className="sm:col-span-2 text-xs text-slate-500">{trialMode ? `AI logo actions use Guest Cosmic Credits. ${Math.max(0, 2 - logoRegenerationsUsed)} of 2 logo regenerations remain today. Current balance: ${creditBalance} credits. Upload/replace is free.` : `AI logo generation and brand matching cost 50 credits per action. Current balance: ${creditBalance} credits. Upload/replace is free.`}</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <div>
                                        <label className="mb-2 block text-sm font-semibold text-slate-800">Company name</label>
                                        <input type="text" maxLength={80} value={logoCompanyName} onChange={(event) => setLogoCompanyName(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter' && !logoBusy && logoCompanyName.trim().length >= 2) generateTrialLogo(); }} className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-emerald-500 focus:ring-emerald-500" placeholder="e.g. Northstar Construction" autoFocus />
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <button type="button" disabled={logoBusy} onClick={() => setShowLogoGenerateForm(false)} className="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-50">Back</button>
                                        <button type="button" disabled={logoBusy || logoCompanyName.trim().length < 2} onClick={generateTrialLogo} className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">{logoBusy ? 'Cosmic AI is creating…' : (data.global_header?.logo_image_url && !String(data.global_header.logo_image_url).includes('your-logo.png') ? 'Regenerate Logo' : 'Generate Logo')}</button>
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
                                <h3 className="mt-1 text-xl font-bold text-slate-900">Theme preview from your logo</h3>
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

                        <p className="mt-4 text-xs leading-5 text-slate-500">Your exact HEX palette is saved as one reusable My Brand Theme. Running Match Theme to Logo again updates this same theme instead of adding another card.</p>

                        <div className="mt-6 flex justify-end gap-3">
                            <button type="button" onClick={() => setThemeFromLogoPreview(null)} className="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                            <button type="button" onClick={applyThemeFromLogo} className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Apply Theme</button>
                        </div>
                    </div>
                </div>
            )}

            {showTrialEmailModal && (
                <div className="fixed inset-0 z-[200] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
                    <form onSubmit={captureTrialEmailAndSave} className="w-full max-w-md rounded-2xl border border-white/10 bg-[#111318] p-6 shadow-2xl">
                        <p className="text-xs font-bold uppercase tracking-[0.22em] text-cyan-300">Save your landing page</p>
                        <h2 className="mt-2 text-xl font-bold text-white">Where should we send your private editing link?</h2>
                        <p className="mt-2 text-sm text-slate-400">Enter your email once. Future saves will not ask again, and your trial link stays available for 30 days.</p>
                        <input type="email" required autoFocus value={trialEmail} onChange={(event) => setTrialEmail(event.target.value)} placeholder="you@business.com" className="mt-5 w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-white outline-none focus:border-cyan-300" />
                        <div className="mt-5 flex justify-end gap-3">
                            <button type="button" onClick={() => setShowTrialEmailModal(false)} className="rounded-lg border border-white/10 px-4 py-2 text-sm text-slate-300">Cancel</button>
                            <button type="submit" disabled={trialEmailSaving} className="rounded-lg bg-cyan-300 px-4 py-2 text-sm font-bold text-slate-950 disabled:opacity-50">{trialEmailSaving ? 'Saving…' : 'Save & email link'}</button>
                        </div>
                    </form>
                </div>
            )}

            {regenerating && (
                <div className="fixed inset-0 z-[260] grid place-items-center bg-white/75 px-5 text-center backdrop-blur-sm" role="status" aria-live="polite">
                    <div className="w-full max-w-2xl rounded-[28px] border border-emerald-200/90 bg-white/95 px-6 py-8 shadow-[0_35px_100px_-30px_rgba(15,23,42,.35)] ring-1 ring-white sm:px-10 sm:py-10">
                        <div className="relative mx-auto h-16 w-16" aria-hidden="true">
                            <div className="cosmic-start-spinner absolute inset-0 rounded-full" />
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
                        <p className="text-xs font-bold uppercase tracking-[0.22em] text-emerald-600">Regenerate landing page</p>
                        <h2 className="mt-2 text-xl font-bold text-slate-900">Describe the new direction</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-500">You have {Math.max(0, 2 - regenerationUsed)} of 2 regenerations remaining today. Your current page is preserved if generation fails.</p>
                        <textarea required minLength={10} value={regeneratePrompt} onChange={(event) => setRegeneratePrompt(event.target.value)} rows={5} placeholder="Make it more premium, modern, and focused on corporate clients…" className="mt-5 w-full resize-none rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                        <div className="mt-5 flex justify-end gap-3">
                            <button type="button" onClick={() => setShowRegenerateModal(false)} className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</button>
                            <button type="submit" disabled={regenerating || regenerationUsed >= 2} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">{regenerating ? 'Regenerating…' : 'Regenerate page'}</button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}
