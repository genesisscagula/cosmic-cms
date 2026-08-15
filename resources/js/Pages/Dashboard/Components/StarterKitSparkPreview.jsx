import { Component } from "react";
import { ActualSparkPreview } from "../../Websites/Components/AddSectionModal";
import { BlockRegistry as SparkRegistry } from "../../Websites/Components/SparkRegistry";
import { GlassmorphismHeader } from "../../Websites/GenerateHeader";
import { MinimalFooter } from "../../Websites/GenerateFooter";

const sparkLabels = {
    hero_background_image: "Image Hero",
    hero_split_image: "Split Hero",
    hero_parallax: "Parallax Hero",
    hero_slider_fade: "Fade Slider Hero",
    hero_editorial_overlay: "Editorial Hero",
    hero_floating_cards: "Floating Hero",
    hero_split_editorial: "Split Editorial",
    hero_luxury_fullscreen: "Luxury Hero",
    hero_bento_premium: "Bento Hero",
    hero_agency_showcase: "Agency Showcase",
    hero_saas_dashboard: "SaaS Dashboard Hero",
    hero_floating_glass: "Floating Glass Hero",
    hero_video_background: "Video Background Hero",
    hero_video_style: "Video Style Hero",
    hero_video_premium: "Video Hero Premium",
    hero_ai_conversation: "AI Conversation Hero",
    feature_image_left: "Story Left",
    feature_image_right: "Story Right",
    services_cards: "Service Cards",
    services_bento: "Services Bento",
    services_bento_premium: "Premium Services Bento",
    services_pricing_comparison: "Pricing Comparison Premium",
    services_feature_comparison: "Feature Comparison Premium",
    services_hover_cards: "Hover Services",
    services_sticky_scroll: "Sticky Scroll Services",
    services_horizontal: "Horizontal Services",
    services_interactive_tabs: "Interactive Tabs",
    services_mega_grid: "Mega Grid",
    about_timeline_story: "Timeline Story",
    about_founder_story: "Founder Story",
    about_mission_grid: "Mission Grid",
    about_interactive_stats: "Company Stats",
    about_brand_journey: "Brand Journey",
    about_awards_timeline: "Awards Timeline",
    about_culture_section: "Culture Section",
    about_office_gallery: "Office Gallery",
    portfolio_masonry: "Masonry Portfolio",
    portfolio_pinterest: "Pinterest Portfolio",
    portfolio_hover_video: "Hover Video Portfolio",
    portfolio_case_study: "Case Study Portfolio",
    portfolio_before_after: "Before After Portfolio",
    portfolio_filterable: "Filterable Portfolio",
    portfolio_animated: "Animated Portfolio",
    portfolio_project_timeline: "Project Timeline",
    process_timeline: "Process",
    testimonials_carousel: "Testimonials",
    testimonials_video_premium: "Video Testimonials",
    testimonials_scrolling_marquee: "Scrolling Marquee",
    testimonials_wall_of_love: "Wall of Love",
    testimonials_card_stack: "Card Stack",
    testimonials_trust_dashboard: "Trust Dashboard",
    testimonials_review_grid: "Review Grid",
    testimonials_review_carousel_pro: "Review Carousel Pro",
    stats_modern: "Stats",
    stats_animated_counters_premium: "Animated Counters",
    stats_revenue_dashboard_premium: "Revenue Dashboard",
    stats_growth_charts_premium: "Growth Charts",
    stats_achievements_premium: "Achievements",
    stats_global_presence_premium: "Global Presence",
    stats_timeline_metrics_premium: "Timeline Metrics",
    faq_accordion_pro: "Accordion Pro",
    faq_search_premium: "Search FAQ",
    faq_categories_premium: "FAQ Categories",
    faq_support_portal_premium: "Support Portal",
    faq_documentation_premium: "Documentation",
    lead_magnet_premium: "Lead Magnet",
    lead_free_audit_premium: "Free Audit",
    lead_website_audit_premium: "Website Audit",
    lead_quote_form_premium: "Quote Form",
    lead_roi_calculator_premium: "ROI Calculator",
    lead_cost_calculator_premium: "Cost Calculator",
    lead_consultation_booking_premium: "Consultation Booking",
    sales_comparison_premium: "Sales Comparison Table",
    sales_feature_matrix_premium: "Sales Feature Matrix",
    sales_competitor_comparison_premium: "Competitor Comparison",
    sales_roi_premium: "Sales ROI",
    sales_guarantee_premium: "Guarantee",
    sales_trust_premium: "Trust Section",
    sales_integrations_premium: "Integrations",
    agency_dashboard_preview_premium: "Agency Dashboard Preview",
    agency_client_portal_premium: "Client Portal",
    agency_white_label_showcase_premium: "White Label Showcase",
    agency_website_management_premium: "Website Management",
    agency_maintenance_plans_premium: "Maintenance Plans",
    agency_support_plans_premium: "Support Plans",
    agency_workflow_premium: "Agency Workflow",
    agency_project_pipeline_premium: "Project Pipeline",
    agency_client_reviews_premium: "Client Reviews",
    agency_website_reports_premium: "Website Reports",
    ai_prompt_showcase_premium: "AI Prompt Showcase",
    ai_workflow_premium: "AI Workflow",
    ai_assistant_premium: "AI Assistant",
    ai_timeline_premium: "AI Timeline",
    ai_builder_premium: "AI Builder",
    ai_automation_premium: "Automation",
    ai_credits_dashboard_premium: "Credits Dashboard",
    ai_generation_process_premium: "Generation Process",
    ai_statistics_premium: "AI Statistics",
    ai_prompt_examples_premium: "Prompt Examples",
    case_studies_grid: "Case Studies",
    pricing_cards: "Pricing",
    pricing_comparison_premium: "Comparison Table",
    pricing_toggle_premium: "Toggle Monthly/Yearly",
    pricing_enterprise_premium: "Enterprise Pricing",
    pricing_calculator_premium: "Pricing Calculator",
    pricing_credit_premium: "Credit Pricing",
    pricing_agency_premium: "Agency Pricing",
    pricing_feature_matrix_premium: "Feature Matrix",
    hero_centered_cta: "Centered CTA",
    image_cta_banner: "Image CTA",
    cta_glass_premium: "Glass CTA",
    cta_gradient_premium: "Gradient CTA",
    cta_newsletter_premium: "Newsletter CTA",
    cta_book_demo_premium: "Book Demo CTA",
    cta_calendly_premium: "Calendly CTA",
    cta_free_trial_premium: "Free Trial CTA",
    cta_countdown_premium: "Countdown CTA",
    cta_limited_offer_premium: "Limited Offer CTA",
    contact_split_premium: "Split Contact",
    contact_map_premium: "Map Contact",
    contact_appointment_premium: "Appointment Booking",
    contact_support_center_premium: "Support Center",
    contact_faq_premium: "FAQ + Contact",
    contact_multistep_premium: "Multi-step Contact",
    contact_live_chat_premium: "Live Chat CTA",
    blog_magazine_premium: "Magazine Layout",
    blog_featured_article_premium: "Featured Article",
    blog_editors_pick_premium: "Editor's Pick",
    blog_sidebar_news_premium: "Sidebar News",
    blog_newsletter_premium: "Blog Newsletter",
    blog_trending_premium: "Trending",
    blog_categories_grid_premium: "Categories Grid",
    blog_author_profile_premium: "Author Profile",
    team_cards_premium: "Team Cards",
    team_timeline_premium: "Team Timeline",
    team_org_chart_premium: "Organization Chart",
    team_leadership_premium: "Leadership",
    team_culture_premium: "Culture",
    team_open_positions_premium: "Open Positions",
    footer_mega_premium: "Mega Footer",
    footer_agency_premium: "Agency Footer",
    footer_saas_premium: "SaaS Footer",
    footer_luxury_premium: "Luxury Footer",
    footer_dark_premium: "Dark Footer",
    footer_minimal_premium: "Minimal Footer",
    contact_form_modern: "Contact",
};

const sparkRegistry = new Map(SparkRegistry.map((item) => [item.type, item]));
const starterKitPreviewCycle = ["primary", "white", "surface", "white"];

function starterKitThemeFamily(template) {
    return template?.themeFamily || template?.theme_family || template?.themeId || "midnight";
}

function starterKitGlobalTheme(template) {
    return {
        primary: starterKitThemeFamily(template),
        secondary: "white",
        tertiary: "surface",
        auto: true,
    };
}

function starterKitSiteName(template) {
    const raw = String(template?.name || "Cosmic Starter").trim();
    return raw.replace(/\s+(starter\s+kit|kit)$/i, "").trim() || "Cosmic Starter";
}

function starterKitShell(template) {
    const name = starterKitSiteName(template);
    const themeFamily = starterKitThemeFamily(template);
    const globalTheme = starterKitGlobalTheme(template);

    return {
        globalTheme,
        header: {
            type: "glassmorphism_header",
            theme: "white",
            logo_text: name,
            logo_filter_key: themeFamily,
            menu: [
                { label: "Home", url: "#" },
                { label: "About", url: "#" },
                { label: "Services", url: "#" },
                { label: "Contact", url: "#" },
            ],
            cta_label: "Get Started",
            cta_url: "#",
        },
        footer: {
            type: "minimal_footer",
            theme: "white",
            logo_text: name,
            logo_filter_key: themeFamily,
            links: [
                { label: "About" },
                { label: "Services" },
                { label: "Contact" },
                { label: "Privacy" },
            ],
            tagline: `${name} — thoughtfully built for what comes next.`,
            copyright: `© 2026 ${name}. All rights reserved.`,
        },
    };
}

class StarterKitPreviewBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { failed: false };
    }

    static getDerivedStateFromError() {
        return { failed: true };
    }

    componentDidCatch(error) {
        console.error("Starter Kit preview block failed", this.props.type, error);
    }

    render() {
        if (this.state.failed) {
            return (
                <div className="flex min-h-32 items-center justify-center border-y border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">
                    Preview unavailable for {sparkLabels[this.props.type] || this.props.type}.
                </div>
            );
        }

        return this.props.children;
    }
}

function resolvePreviewBlocks(template) {
    if (Array.isArray(template?.previewBlocks) && template.previewBlocks.length) return template.previewBlocks;
    if (Array.isArray(template?.preview_blocks) && template.preview_blocks.length) return template.preview_blocks;

    const types = Array.isArray(template?.previewSparks) && template.previewSparks.length
        ? template.previewSparks
        : Array.isArray(template?.preview_sparks) && template.preview_sparks.length
            ? template.preview_sparks
            : [];

    return types.map((type) => ({ type }));
}

function sparkForType(type) {
    const registry = sparkRegistry.get(type);
    if (!registry) return null;
    return { key: type, registry };
}

/**
 * Starter-kit parity renderer.
 *
 * IMPORTANT: this intentionally delegates to ActualSparkPreview, the exact
 * renderer used by the Sparks marketplace. A starter kit only supplies a
 * payload override + theme variant; it never owns a second block renderer.
 * This keeps Sparks -> Starter Kit card -> Starter Kit full preview in sync.
 */
export function ActualStarterKitSpark({ type, index = 0, template, blockData = null }) {
    const spark = sparkForType(type);
    if (!spark) {
        return (
            <div className="flex min-h-32 items-center justify-center bg-white px-6 py-10 text-center text-sm text-slate-500">
                Preview unavailable for {sparkLabels[type] || type}.
            </div>
        );
    }

    const previewVariant = starterKitPreviewCycle[index % starterKitPreviewCycle.length];

    return (
        <StarterKitPreviewBoundary type={type}>
            <ActualSparkPreview
                spark={spark}
                previewVariant={previewVariant}
                websiteTheme={starterKitGlobalTheme(template)}
                payloadOverride={blockData || {}}
                blockIndex={index}
            />
        </StarterKitPreviewBoundary>
    );
}


/**
 * Purpose-built Starter Kit card visual.
 *
 * Marketplace Spark cards intentionally use the registry `preview` component
 * instead of shrinking the full Builder block. Starter Kit cards now do the
 * same thing, while still merging the kit's curated first-block payload and
 * fixed theme family. This keeps the silhouette identical to the chosen Spark
 * card (slider/parallax/bento/split/etc.) without the fragile giant-page scale.
 */
function StarterKitCardSparkVisual({ block, template }) {
    const type = block?.type;
    const spark = sparkForType(type);
    const Preview = spark?.registry?.preview;

    if (!Preview) {
        return (
            <div className="flex h-full items-center justify-center bg-slate-950 px-4 text-center text-xs font-medium text-slate-400">
                {sparkLabels[type] || "Spark"} preview unavailable
            </div>
        );
    }

    const payload = {
        ...(spark.registry.payload || {}),
        ...(block || {}),
    };

    return (
        <Preview
            {...payload}
            previewVariant="primary"
            websiteTheme={starterKitGlobalTheme(template)}
        />
    );
}

export default function StarterKitSparkPreview({ template, compact = false, showLabels = false }) {
    const previewBlocks = resolvePreviewBlocks(template);

    if (!previewBlocks.length) {
        // Unknown/future Starter Kits should fail visibly instead of silently
        // rendering a flat theme-color rectangle that looks like a broken hero.
        return (
            <div className={`${compact ? "h-full min-h-24" : "min-h-[70vh]"} flex items-center justify-center bg-slate-950 px-6 text-center`}>
                <div>
                    <p className="text-sm font-semibold text-white">Starter Kit preview is unavailable</p>
                    <p className="mt-1 text-xs text-slate-400">The curated Spark composition could not be loaded.</p>
                </div>
            </div>
        );
    }

    if (compact) {
        const firstBlock = previewBlocks[0];
        const heroType = firstBlock?.type;

        return (
            <div
                className="cosmic-preview-isolation h-full min-h-0 overflow-hidden rounded-lg"
                data-cosmic-preview-isolation="true"
                data-cosmic-site-preview="true"
                aria-label={`${template.name || "Starter kit"} ${sparkLabels[heroType] || "hero"} preview`}
            >
                <div className="pointer-events-none h-full w-full [&>*]:h-full">
                    <StarterKitCardSparkVisual block={firstBlock} template={template} />
                </div>
            </div>
        );
    }

    const shell = starterKitShell(template);

    return (
        <div className="cosmic-preview-isolation cosmic-starter-kit-live-site w-full bg-white text-slate-950 [color-scheme:light]" data-cosmic-site-preview="true" data-cosmic-preview-isolation="true">
            <div className="pointer-events-none w-full">
                <GlassmorphismHeader
                    block={shell.header}
                    onUpdate={() => {}}
                    globalTheme={shell.globalTheme}
                    pageTargets={[]}
                />
            </div>

            <main className="w-full">
                {previewBlocks.map((block, index) => {
                    const type = block?.type;
                    if (!type) return null;

                    return (
                        <section key={`${type}-${index}`} className="w-full overflow-hidden">
                            {showLabels && (
                                <div className="flex items-center justify-between border-y border-slate-200 bg-white px-4 py-2 text-[9px] font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                    <span>{sparkLabels[type] || type.replaceAll("_", " ")}</span>
                                    <span>{String(index + 1).padStart(2, "0")}</span>
                                </div>
                            )}
                            <div className="pointer-events-none w-full">
                                <ActualStarterKitSpark
                                    type={type}
                                    index={index}
                                    template={template}
                                    blockData={block}
                                />
                            </div>
                        </section>
                    );
                })}
            </main>

            <div className="pointer-events-none w-full">
                <MinimalFooter block={shell.footer} onUpdate={() => {}} />
            </div>
        </div>
    );
}

export function starterKitCompositionLabels(template, limit = 3) {
    return resolvePreviewBlocks(template)
        .map((block) => block?.type)
        .filter(Boolean)
        .slice(0, limit)
        .map((type) => sparkLabels[type] || String(type).replaceAll("_", " "));
}

export { sparkLabels };
