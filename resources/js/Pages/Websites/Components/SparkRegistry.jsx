import axios from "axios";
import { useState } from "react";
import { useRef } from "react";
import { showCosmicNotification } from "../../../Components/CosmicNotification";
import { ACTION_PRICING, getBlockPrice } from "../../../cosmic/pricing";
import CreditBalanceBadge from "../../../Components/CosmicCredits/CreditBalanceBadge";
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import CreditPrice from "../../../Components/CosmicCredits/CreditPrice";

import BlockPreviewCard from "./BlockPreviewCard";

import HeroHeadlinePreview from "./Previews/HeroHeadlinePreview";
import ServicesGridPreview from "./Previews/ServicesGridPreview";
import FeatureLeftPreview from "./Previews/FeatureLeftPreview";
import FeatureRightPreview from "./Previews/FeatureRightPreview";
import HeroCenteredPreview from "./Previews/HeroCenteredPreview";
import ServicesBentoPreview from "./Previews/ServicesBentoPreview";
import ProcessTimelinePreview from "./Previews/ProcessTimelinePreview";
import StatsModernPreview from "./Previews/StatsModernPreview";
import StatsAnimatedCountersPremiumPreview from "./Previews/StatsAnimatedCountersPremiumPreview";
import StatsRevenueDashboardPremiumPreview from "./Previews/StatsRevenueDashboardPremiumPreview";
import StatsGrowthChartsPremiumPreview from "./Previews/StatsGrowthChartsPremiumPreview";
import StatsAchievementsPremiumPreview from "./Previews/StatsAchievementsPremiumPreview";
import StatsGlobalPresencePremiumPreview from "./Previews/StatsGlobalPresencePremiumPreview";
import StatsTimelineMetricsPremiumPreview from "./Previews/StatsTimelineMetricsPremiumPreview";
import TeamModernPreview from "./Previews/TeamModernPreview";
import TeamCardsPremiumPreview from "./Previews/TeamCardsPremiumPreview";
import TeamTimelinePremiumPreview from "./Previews/TeamTimelinePremiumPreview";
import TeamOrgChartPremiumPreview from "./Previews/TeamOrgChartPremiumPreview";
import TeamLeadershipPremiumPreview from "./Previews/TeamLeadershipPremiumPreview";
import TeamCulturePremiumPreview from "./Previews/TeamCulturePremiumPreview";
import TeamOpenPositionsPremiumPreview from "./Previews/TeamOpenPositionsPremiumPreview";
import TestimonialsCarouselPreview from "./Previews/TestimonialsCarouselPreview";
import PricingCardsPreview from "./Previews/PricingCardsPreview";
import CtaGlassPremiumPreview from "./Previews/CtaGlassPremiumPreview";
import CtaGradientPremiumPreview from "./Previews/CtaGradientPremiumPreview";
import CtaNewsletterPremiumPreview from "./Previews/CtaNewsletterPremiumPreview";
import CtaBookDemoPremiumPreview from "./Previews/CtaBookDemoPremiumPreview";
import CtaCalendlyPremiumPreview from "./Previews/CtaCalendlyPremiumPreview";
import CtaFreeTrialPremiumPreview from "./Previews/CtaFreeTrialPremiumPreview";
import CtaCountdownPremiumPreview from "./Previews/CtaCountdownPremiumPreview";
import CtaLimitedOfferPremiumPreview from "./Previews/CtaLimitedOfferPremiumPreview";
import HeroBackgroundImagePreview from "./Previews/HeroBackgroundImagePreview";
import HeroSliderFadePreview from "./Previews/HeroSliderFadePreview";
import HeroParallaxPreview from "./Previews/HeroParallaxPreview";
import HeroEditorialOverlayPreview from "./Previews/HeroEditorialOverlayPreview";
import HeroSplitImagePreview from "./Previews/HeroSplitImagePreview";
import HeroSplitEditorialPreview from "./Previews/HeroSplitEditorialPreview";
import HeroFloatingGlassPreview from "./Previews/HeroFloatingGlassPreview";
import HeroSaasDashboardPreview from "./Previews/HeroSaasDashboardPreview";
import HeroLuxuryFullscreenPreview from "./Previews/HeroLuxuryFullscreenPreview";
import HeroVideoPremiumPreview from "./Previews/HeroVideoPremiumPreview";
import HeroAiConversationPreview from "./Previews/HeroAiConversationPreview";
import HeroAgencyShowcasePreview from "./Previews/HeroAgencyShowcasePreview";
import HeroBentoPremiumPreview from "./Previews/HeroBentoPremiumPreview";
import ServicesBentoPremiumPreview from "./Previews/ServicesBentoPremiumPreview";
import ServicesPricingComparisonPreview from "./Previews/ServicesPricingComparisonPreview";
import ServicesFeatureComparisonPreview from "./Previews/ServicesFeatureComparisonPreview";
import ServicesHoverCardsPreview from "./Previews/ServicesHoverCardsPreview";
import ServicesStickyScrollPreview from "./Previews/ServicesStickyScrollPreview";
import ServicesHorizontalPreview from "./Previews/ServicesHorizontalPreview";
import ServicesInteractiveTabsPreview from "./Previews/ServicesInteractiveTabsPreview";
import ServicesMegaGridPreview from "./Previews/ServicesMegaGridPreview";
import AboutTimelineStoryPreview from "./Previews/AboutTimelineStoryPreview";
import AboutFounderStoryPreview from "./Previews/AboutFounderStoryPreview";
import AboutMissionGridPreview from "./Previews/AboutMissionGridPreview";
import AboutInteractiveStatsPreview from "./Previews/AboutInteractiveStatsPreview";
import AboutBrandJourneyPreview from "./Previews/AboutBrandJourneyPreview";
import AboutAwardsTimelinePreview from "./Previews/AboutAwardsTimelinePreview";
import AboutCultureSectionPreview from "./Previews/AboutCultureSectionPreview";
import AboutOfficeGalleryPreview from "./Previews/AboutOfficeGalleryPreview";
import PortfolioMasonryPreview from "./Previews/PortfolioMasonryPreview";
import PortfolioPinterestPreview from "./Previews/PortfolioPinterestPreview";
import PortfolioHoverVideoPreview from "./Previews/PortfolioHoverVideoPreview";
import PortfolioCaseStudyPreview from "./Previews/PortfolioCaseStudyPreview";
import PortfolioBeforeAfterPreview from "./Previews/PortfolioBeforeAfterPreview";
import PortfolioFilterablePreview from "./Previews/PortfolioFilterablePreview";
import PortfolioAnimatedPreview from "./Previews/PortfolioAnimatedPreview";
import PortfolioProjectTimelinePreview from "./Previews/PortfolioProjectTimelinePreview";
import TestimonialsVideoPremiumPreview from "./Previews/TestimonialsVideoPremiumPreview";
import TestimonialsScrollingMarqueePreview from "./Previews/TestimonialsScrollingMarqueePreview";
import TestimonialsWallOfLovePreview from "./Previews/TestimonialsWallOfLovePreview";
import TestimonialsCardStackPreview from "./Previews/TestimonialsCardStackPreview";
import TestimonialsTrustDashboardPreview from "./Previews/TestimonialsTrustDashboardPreview";
import TestimonialsReviewGridPreview from "./Previews/TestimonialsReviewGridPreview";
import TestimonialsReviewCarouselProPreview from "./Previews/TestimonialsReviewCarouselProPreview";
import ImageCtaBannerPreview from "./Previews/ImageCtaBannerPreview";
import HeroFloatingCardsPreview from "./Previews/HeroFloatingCardsPreview";
import HeroVideoStylePreview from "./Previews/HeroVideoStylePreview";
import HeroVideoBackgroundPreview from "./Previews/HeroVideoBackgroundPreview";
import ContactFormPreview from "./Previews/ContactFormPreview";
import ContactSplitPremiumPreview from "./Previews/ContactSplitPremiumPreview";
import ContactMapPremiumPreview from "./Previews/ContactMapPremiumPreview";
import ContactAppointmentPremiumPreview from "./Previews/ContactAppointmentPremiumPreview";
import ContactSupportCenterPremiumPreview from "./Previews/ContactSupportCenterPremiumPreview";
import ContactFaqPremiumPreview from "./Previews/ContactFaqPremiumPreview";
import ContactMultiStepPremiumPreview from "./Previews/ContactMultiStepPremiumPreview";
import ContactLiveChatPremiumPreview from "./Previews/ContactLiveChatPremiumPreview";
import BlogMagazinePremiumPreview from "./Previews/BlogMagazinePremiumPreview";
import BlogFeaturedArticlePremiumPreview from "./Previews/BlogFeaturedArticlePremiumPreview";
import BlogEditorsPickPremiumPreview from "./Previews/BlogEditorsPickPremiumPreview";
import BlogSidebarNewsPremiumPreview from "./Previews/BlogSidebarNewsPremiumPreview";
import BlogNewsletterPremiumPreview from "./Previews/BlogNewsletterPremiumPreview";
import BlogTrendingPremiumPreview from "./Previews/BlogTrendingPremiumPreview";
import BlogCategoriesGridPremiumPreview from "./Previews/BlogCategoriesGridPremiumPreview";
import BlogAuthorProfilePremiumPreview from "./Previews/BlogAuthorProfilePremiumPreview";
import FooterMegaPremiumPreview from "./Previews/FooterMegaPremiumPreview";
import FooterAgencyPremiumPreview from "./Previews/FooterAgencyPremiumPreview";
import FooterSaasPremiumPreview from "./Previews/FooterSaasPremiumPreview";
import FooterLuxuryPremiumPreview from "./Previews/FooterLuxuryPremiumPreview";
import FooterDarkPremiumPreview from "./Previews/FooterDarkPremiumPreview";
import FooterMinimalPremiumPreview from "./Previews/FooterMinimalPremiumPreview";

import FaqAccordionPreview from "./Previews/FaqAccordionPreview";
import FaqAccordionProPreview from "./Previews/FaqAccordionProPreview";
import FaqSearchPremiumPreview from "./Previews/FaqSearchPremiumPreview";
import FaqCategoriesPremiumPreview from "./Previews/FaqCategoriesPremiumPreview";
import FaqSupportPortalPremiumPreview from "./Previews/FaqSupportPortalPremiumPreview";
import FaqDocumentationPremiumPreview from "./Previews/FaqDocumentationPremiumPreview";
import LeadMagnetPremiumPreview from "./Previews/LeadMagnetPremiumPreview";
import FreeAuditPremiumPreview from "./Previews/FreeAuditPremiumPreview";
import WebsiteAuditPremiumPreview from "./Previews/WebsiteAuditPremiumPreview";
import QuoteFormPremiumPreview from "./Previews/QuoteFormPremiumPreview";
import SalesComparisonPremiumPreview from "./Previews/SalesComparisonPremiumPreview";
import SalesFeatureMatrixPremiumPreview from "./Previews/SalesFeatureMatrixPremiumPreview";
import SalesCompetitorComparisonPremiumPreview from "./Previews/SalesCompetitorComparisonPremiumPreview";
import SalesRoiPremiumPreview from "./Previews/SalesRoiPremiumPreview";
import SalesGuaranteePremiumPreview from "./Previews/SalesGuaranteePremiumPreview";
import SalesTrustPremiumPreview from "./Previews/SalesTrustPremiumPreview";
import SalesIntegrationsPremiumPreview from "./Previews/SalesIntegrationsPremiumPreview";
import AgencyDashboardPreviewPremiumPreview from "./Previews/AgencyDashboardPreviewPremiumPreview";
import AgencyClientPortalPremiumPreview from "./Previews/AgencyClientPortalPremiumPreview";
import AgencyWhiteLabelShowcasePremiumPreview from "./Previews/AgencyWhiteLabelShowcasePremiumPreview";
import AgencyWebsiteManagementPremiumPreview from "./Previews/AgencyWebsiteManagementPremiumPreview";
import AgencyMaintenancePlansPremiumPreview from "./Previews/AgencyMaintenancePlansPremiumPreview";
import AgencySupportPlansPremiumPreview from "./Previews/AgencySupportPlansPremiumPreview";
import AgencyWorkflowPremiumPreview from "./Previews/AgencyWorkflowPremiumPreview";
import AgencyProjectPipelinePremiumPreview from "./Previews/AgencyProjectPipelinePremiumPreview";
import AgencyClientReviewsPremiumPreview from "./Previews/AgencyClientReviewsPremiumPreview";
import AgencyWebsiteReportsPremiumPreview from "./Previews/AgencyWebsiteReportsPremiumPreview";
import AiPromptShowcasePremiumPreview from "./Previews/AiPromptShowcasePremiumPreview";
import AiWorkflowPremiumPreview from "./Previews/AiWorkflowPremiumPreview";
import AiAssistantPremiumPreview from "./Previews/AiAssistantPremiumPreview";
import AiTimelinePremiumPreview from "./Previews/AiTimelinePremiumPreview";
import AiBuilderPremiumPreview from "./Previews/AiBuilderPremiumPreview";
import AiAutomationPremiumPreview from "./Previews/AiAutomationPremiumPreview";
import AiCreditsDashboardPremiumPreview from "./Previews/AiCreditsDashboardPremiumPreview";
import AiGenerationProcessPremiumPreview from "./Previews/AiGenerationProcessPremiumPreview";
import AiStatisticsPremiumPreview from "./Previews/AiStatisticsPremiumPreview";
import AiPromptExamplesPremiumPreview from "./Previews/AiPromptExamplesPremiumPreview";
import RoiCalculatorPremiumPreview from "./Previews/RoiCalculatorPremiumPreview";
import CostCalculatorPremiumPreview from "./Previews/CostCalculatorPremiumPreview";
import ConsultationBookingPremiumPreview from "./Previews/ConsultationBookingPremiumPreview";
import ContactDetailsPreview from "./Previews/ContactDetailsPreview";
import LocationMapPreview from "./Previews/LocationMapPreview";
import CaseStudiesGridPreview from "./Previews/CaseStudiesGridPreview";
import JobsListPreview from "./Previews/JobsListPreview";
import EventsGridPreview from "./Previews/EventsGridPreview";


import PricingComparisonPremiumPreview from "./Previews/PricingComparisonPremiumPreview";
import PricingTogglePremiumPreview from "./Previews/PricingTogglePremiumPreview";
import PricingEnterprisePremiumPreview from "./Previews/PricingEnterprisePremiumPreview";
import PricingCalculatorPremiumPreview from "./Previews/PricingCalculatorPremiumPreview";
import PricingCreditPremiumPreview from "./Previews/PricingCreditPremiumPreview";
import PricingAgencyPremiumPreview from "./Previews/PricingAgencyPremiumPreview";
import PricingFeatureMatrixPremiumPreview from "./Previews/PricingFeatureMatrixPremiumPreview";
export const BlockRegistry = [
    {
        type: "hero_headline",
        theme:"auto",
        title: "Hero",
        buttonLabel: "Install",
        buttonClass: "bg-rose-600 hover:bg-rose-500",
        preview: HeroHeadlinePreview,
        payload: {
            type: "hero_headline",
            subtitle: "WELCOME TO THE FUTURE",
            heading: "Build Better Digital Reality.",
            text: "Build a polished website faster with reusable sections and complete editorial control.",
            btn1_label: "Get Started",
            btn1_url: "/contact",
            btn2_label: "View Docs",
            btn2_url: "/about"
        }
    },

    {
        type: "hero_video_background",
        theme: "auto",
        title: "Hero Video Background",
        buttonLabel: "Add Video Background",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroVideoBackgroundPreview,
        payload: {
            type: "hero_video_background",
            theme: "auto",
            tagline: "STEP INTO THE EXPERIENCE",
            heading: "Make every first impression unforgettable.",
            text: "Introduce your business through motion, strong storytelling, and a clear next step for every visitor.",
            primary_label: "Get started",
            primary_url: "/contact",
            secondary_label: "Explore more",
            secondary_url: "/about",
            video_url: "/storage/cms-videos/hero-placeholder.mp4",
            poster_image_url: "/storage/cms-images/background/background-1.avif",
            video_badge: "Discover what makes us different",
            scroll_label: "Explore",
        },
    },

    {
    type: "hero_video_style",
        theme: "auto",
        title: "Hero Video Style",
        buttonLabel: "Add Video Hero",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroVideoStylePreview,
        payload: {
            type: "hero_video_style",
            theme: "auto",
            tagline: "SEE WHAT SETS US APART",
            heading: "A clear vision for what comes next.",
            text: "Introduce your business with a strong message, a compelling visual, and a simple path for visitors to learn more.",
            primary_label: "Get started",
            primary_url: "/contact",
            video_label: "Watch our story",
            video_url: "/storage/cms-videos/hero-placeholder.mp4",
            play_label: "Play video",
            image_badge: "Discover our approach",
            image_url: "/storage/cms-images/background/background-1.avif",
        },
    },

    {
    type: "hero_floating_cards",
        theme: "auto",
        title: "Hero Floating Cards",
        buttonLabel: "Add Floating Hero",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroFloatingCardsPreview,
        payload: {
            type: "hero_floating_cards",
            theme: "auto",
            tagline: "BUILT AROUND YOUR NEXT STEP",
            heading: "A better way to move your business forward.",
            text: "Present your strongest message, highlight what makes your business different, and help visitors take action with confidence.",
            primary_label: "Get started",
            primary_url: "/contact",
            secondary_label: "Explore services",
            secondary_url: "/about",
            image_url: "/storage/cms-images/background/background-1.avif",
            image_badge: "Professional service you can rely on",
            card_one_value: "15+",
            card_one_label: "Years of experience",
            card_two_title: "Trusted expertise",
            card_two_text: "Thoughtful service, clear communication, and dependable results.",
        },
    },

    {
        type: "services_cards",
        theme:"auto",
        title: "Services Grid",
        buttonLabel: "Install Grid",
        buttonClass: "bg-blue-600 hover:bg-blue-500",
        preview: ServicesGridPreview,
        payload: {
            type: "services_cards",
            heading: "Our Services",
            tagline: "WHAT WE OFFER"
        }
    },

    {
        type: "feature_image_left",
        theme:"auto",
        title: "Feature Image Left",
        buttonLabel: "Install Feature Block",
        buttonClass: "bg-blue-600 hover:bg-blue-500",
        preview: FeatureLeftPreview,
        payload: {
            type: "feature_image_left",
            category: "CATEGORY",
            heading: "Lorem ipsum dolor sit amet",
            text: "Consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
            button_label: "Read more →",
            button_url: "/contact",
            image_url: "https://picsum.photos/800/400"
        }
    },

    {
        type: "feature_image_right",
        theme:"auto",
        title: "Feature Image Right",
        buttonLabel: "Install Reverse Block",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: FeatureRightPreview,
        payload: {
            type: "feature_image_right",
            category: "CATEGORY",
            heading: "Lorem ipsum dolor sit amet",
            text: "Consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
            button_label: "Read more →",
            button_url: "/contact",
            image_url: "https://picsum.photos/800/400"
        }
    },

    {
        type: "hero_centered_cta",
        theme:"auto",
        title: "Hero: Accent Focus",
        buttonLabel: "Install Accent Focus",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: HeroCenteredPreview,
        payload: {
            type: "hero_centered_cta",
            heading: "Build Modern Websites Fast",
            tagline: "GET STARTED"
        }
    },

    {
        type: "services_bento",
        theme:"auto",
        title: "Services Bento",
        buttonLabel: "Install Bento",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: ServicesBentoPreview,
        payload: {
            type: "services_bento",
            heading: "Solutions Built Around Your Business",
            tagline: "OUR SERVICES"
        }
    },
    {
        type: "process_timeline",
        theme: "auto",
        title: "Process Timeline",
        buttonLabel: "Install Timeline",
        buttonClass: "bg-cyan-600 hover:bg-cyan-500",
        preview: ProcessTimelinePreview,
        payload: {
            type: "process_timeline",
            category: "HOW IT WORKS",
            heading: "Our Simple Process",
            text: "We follow a proven workflow to deliver quality results from consultation to completion."
        }
    },
    {
        type: "stats_modern",
        theme: "auto",
        title: "Modern Stats",
        buttonLabel: "Add Stats",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: StatsModernPreview,
        payload: {
            type: "stats_modern",
            eyebrow: "Why choose us",
            heading: "Experience you can count on",
            text: "Clear results, dependable service, and a team committed to every project.",
            metrics: [
                { value: "15+", label: "Years of experience", description: "Serving customers with proven expertise." },
                { value: "250+", label: "Projects completed", description: "Delivered across a wide range of needs." },
                { value: "98%", label: "Client satisfaction", description: "Built through reliable service and support." },
                { value: "24/7", label: "Responsive support", description: "Help is available whenever it matters." }
            ]
        }
    },
    { type:"stats_animated_counters_premium", theme:"auto", title:"Animated Counters", buttonLabel:"Add Animated Counters", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:StatsAnimatedCountersPremiumPreview, badge:"PRO", payload:{type:"stats_animated_counters_premium",theme:"auto",eyebrow:"BY THE NUMBERS",heading:"A clear view of the progress behind the work.",text:"Replace sample values with verified company metrics before publishing."} },
    { type:"stats_revenue_dashboard_premium", theme:"auto", title:"Revenue Dashboard", buttonLabel:"Add Revenue Dashboard", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:StatsRevenueDashboardPremiumPreview, badge:"PRO", payload:{type:"stats_revenue_dashboard_premium",theme:"auto",eyebrow:"PERFORMANCE SNAPSHOT",heading:"Commercial performance at a glance.",text:"Use only supplied and verified financial figures."} },
    { type:"stats_growth_charts_premium", theme:"auto", title:"Growth Charts", buttonLabel:"Add Growth Charts", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:StatsGrowthChartsPremiumPreview, badge:"PRO", payload:{type:"stats_growth_charts_premium",theme:"auto",eyebrow:"GROWTH",heading:"See the direction, not just the headline.",text:"Replace sample trend data with verified values."} },
    { type:"stats_achievements_premium", theme:"auto", title:"Achievements", buttonLabel:"Add Achievements", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:StatsAchievementsPremiumPreview, badge:"PRO", payload:{type:"stats_achievements_premium",theme:"auto",eyebrow:"MILESTONES",heading:"Moments worth marking.",text:"Use real milestones or clearly editable placeholders until verified."} },
    { type:"stats_global_presence_premium", theme:"auto", title:"Global Presence", buttonLabel:"Add Global Presence", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:StatsGlobalPresencePremiumPreview, badge:"PRO", payload:{type:"stats_global_presence_premium",theme:"auto",eyebrow:"GLOBAL PRESENCE",heading:"Where the work reaches.",text:"Replace placeholders with verified offices, markets, regions, or service areas before publishing."} },
    { type:"stats_timeline_metrics_premium", theme:"auto", title:"Timeline Metrics", buttonLabel:"Add Timeline Metrics", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:StatsTimelineMetricsPremiumPreview, badge:"PRO", payload:{type:"stats_timeline_metrics_premium",theme:"auto",eyebrow:"PROGRESS OVER TIME",heading:"A measurable story, period by period.",text:"Use only verified dates and figures before publishing."} },
    {
        type: "team_modern",
        theme: "auto",
        title: "Team Modern",
        buttonLabel: "Choose team layout",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: TeamModernPreview,
        payload: {
            type: "team_modern",
            eyebrow: "Meet the team",
            heading: "The people behind the work",
            text: "A dedicated team focused on thoughtful service and dependable results.",
            members: [
                { name: "Alex Morgan", role: "Founder & Director", bio: "Guides the team with a client-first approach.", image_url: "/storage/cms-images/avatars/avatar-1.jpg" },
                { name: "Jordan Lee", role: "Client Experience Lead", bio: "Keeps every project organized and responsive.", image_url: "/storage/cms-images/avatars/avatar-2.jpg" },
                { name: "Taylor Brooks", role: "Creative Lead", bio: "Turns clear ideas into polished experiences.", image_url: "/storage/cms-images/avatars/avatar-3.jpg" },
                { name: "Casey Rivera", role: "Operations Manager", bio: "Keeps quality consistent from start to finish.", image_url: "/storage/cms-images/avatars/avatar-4.jpg" }
            ]
        }
    },
    { type:"team_cards_premium", theme:"auto", title:"Team Cards", buttonLabel:"Add Team Cards", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:TeamCardsPremiumPreview, badge:"PRO", payload:{type:"team_cards_premium",theme:"auto",eyebrow:"MEET THE TEAM",heading:"People who make the work happen.",text:"A premium editorial team card grid."} },
    { type:"team_timeline_premium", theme:"auto", title:"Team Timeline", buttonLabel:"Add Team Timeline", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:TeamTimelinePremiumPreview, badge:"PRO", payload:{type:"team_timeline_premium",theme:"auto",eyebrow:"OUR PEOPLE",heading:"A team built over time.",text:"A people-first timeline layout."} },
    { type:"team_org_chart_premium", theme:"auto", title:"Organization Chart", buttonLabel:"Add Organization Chart", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:TeamOrgChartPremiumPreview, badge:"PRO", payload:{type:"team_org_chart_premium",theme:"auto",eyebrow:"HOW WE WORK",heading:"Clear roles. Connected team.",text:"A lightweight responsive organization hierarchy."} },
    { type:"team_leadership_premium", theme:"auto", title:"Leadership", buttonLabel:"Add Leadership", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:TeamLeadershipPremiumPreview, badge:"PRO", payload:{type:"team_leadership_premium",theme:"auto",eyebrow:"LEADERSHIP",heading:"Meet the people setting the direction.",text:"A focused premium leadership spotlight."} },
    { type:"team_culture_premium", theme:"auto", title:"Culture", buttonLabel:"Add Culture", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:TeamCulturePremiumPreview, badge:"PRO", payload:{type:"team_culture_premium",theme:"auto",eyebrow:"OUR CULTURE",heading:"How we work together.",text:"A premium principles-led team culture section."} },
    { type:"team_open_positions_premium", theme:"auto", title:"Open Positions", buttonLabel:"Add Open Positions", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:TeamOpenPositionsPremiumPreview, badge:"PRO", payload:{type:"team_open_positions_premium",theme:"auto",eyebrow:"JOIN THE TEAM",heading:"Open roles for people who care about the work.",text:"A premium careers section for real user-supplied vacancies."} },
    {
        type: "testimonials_carousel",
        theme: "auto",
        title: "Testimonials",
        buttonLabel: "Install Testimonials",
        buttonClass: "bg-amber-600 hover:bg-amber-500",
        preview: TestimonialsCarouselPreview,
        payload: {
            type: "testimonials_carousel",
            tagline: "CLIENT TESTIMONIALS",
            heading: "Trusted By Businesses Around The World",
            text: "See what our satisfied clients say about working with our team."
        }
    },
    { type:"testimonials_video_premium", theme:"auto", title:"Video Testimonials", buttonLabel:"Add Video Testimonials", buttonClass:"bg-amber-600 hover:bg-amber-500", preview:TestimonialsVideoPremiumPreview, payload:{type:"testimonials_video_premium",theme:"auto",eyebrow:"CLIENT STORIES",heading:"Proof that feels personal.",text:"Pair authentic customer feedback with an optional video story."} },
    { type:"testimonials_scrolling_marquee", theme:"auto", title:"Scrolling Marquee", buttonLabel:"Add Scrolling Marquee", buttonClass:"bg-amber-600 hover:bg-amber-500", preview:TestimonialsScrollingMarqueePreview, payload:{type:"testimonials_scrolling_marquee",theme:"auto",eyebrow:"CUSTOMER LOVE",heading:"What clients keep saying.",text:"A flowing rail of customer voices with a clean static fallback."} },
    { type:"testimonials_wall_of_love", theme:"auto", title:"Wall of Love", buttonLabel:"Add Wall of Love", buttonClass:"bg-amber-600 hover:bg-amber-500", preview:TestimonialsWallOfLovePreview, payload:{type:"testimonials_wall_of_love",theme:"auto",eyebrow:"WALL OF LOVE",heading:"A lot of good things to say.",text:"Bring multiple verified customer voices together in one proof-rich section."} },
    { type:"testimonials_card_stack", theme:"auto", title:"Card Stack", buttonLabel:"Add Card Stack", buttonClass:"bg-amber-600 hover:bg-amber-500", preview:TestimonialsCardStackPreview, payload:{type:"testimonials_card_stack",theme:"auto",eyebrow:"CLIENT STORIES",heading:"Feedback worth keeping close.",text:"Layer testimonial cards into an editorial stack with strong visual hierarchy."} },
    { type:"testimonials_trust_dashboard", theme:"auto", title:"Trust Dashboard", buttonLabel:"Add Trust Dashboard", buttonClass:"bg-amber-600 hover:bg-amber-500", preview:TestimonialsTrustDashboardPreview, payload:{type:"testimonials_trust_dashboard",theme:"auto",eyebrow:"TRUST DASHBOARD",heading:"Proof at a glance.",text:"Combine customer voices with a premium trust-oriented summary layout."} },
    { type:"testimonials_review_grid", theme:"auto", title:"Review Grid", buttonLabel:"Add Review Grid", buttonClass:"bg-amber-600 hover:bg-amber-500", preview:TestimonialsReviewGridPreview, payload:{type:"testimonials_review_grid",theme:"auto",eyebrow:"CUSTOMER REVIEWS",heading:"Real voices, clearly presented.",text:"Show several customer stories in a balanced premium grid."} },
    { type:"testimonials_review_carousel_pro", theme:"auto", title:"Review Carousel Pro", buttonLabel:"Add Review Carousel Pro", buttonClass:"bg-amber-600 hover:bg-amber-500", preview:TestimonialsReviewCarouselProPreview, payload:{type:"testimonials_review_carousel_pro",theme:"auto",eyebrow:"FEATURED REVIEWS",heading:"Stories worth scrolling through.",text:"A premium carousel-style review layout with strong featured-card hierarchy."} },
    { type:"pricing_comparison_premium", theme:"auto", title:"Comparison Table", buttonLabel:"Add Comparison Table", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PricingComparisonPremiumPreview, badge:"PRO", payload:{type:"pricing_comparison_premium",theme:"auto",eyebrow:"COMPARE PLANS",heading:"See every option side by side.",text:"A premium feature matrix for clear plan comparison."} },
    { type:"pricing_toggle_premium", theme:"auto", title:"Toggle Monthly/Yearly", buttonLabel:"Add Billing Toggle", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PricingTogglePremiumPreview, badge:"PRO", payload:{type:"pricing_toggle_premium",theme:"auto",eyebrow:"FLEXIBLE BILLING",heading:"Monthly or yearly. Your choice.",text:"Interactive pricing cards with a billing toggle."} },
    { type:"pricing_enterprise_premium", theme:"auto", title:"Enterprise Pricing", buttonLabel:"Add Enterprise Pricing", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PricingEnterprisePremiumPreview, badge:"PRO", payload:{type:"pricing_enterprise_premium",theme:"auto",eyebrow:"ENTERPRISE",heading:"Pricing built around your organisation.",text:"A consultative pricing layout for complex needs."} },
    { type:"pricing_calculator_premium", theme:"auto", title:"Pricing Calculator", buttonLabel:"Add Pricing Calculator", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PricingCalculatorPremiumPreview, badge:"PRO", payload:{type:"pricing_calculator_premium",theme:"auto",eyebrow:"ESTIMATE YOUR PLAN",heading:"Calculate a simple estimate.",text:"A lightweight estimator with no external dependency."} },
    { type:"pricing_credit_premium", theme:"auto", title:"Credit Pricing", buttonLabel:"Add Credit Pricing", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PricingCreditPremiumPreview, badge:"PRO", payload:{type:"pricing_credit_premium",theme:"auto",eyebrow:"FLEXIBLE CREDITS",heading:"Buy credits when you need them.",text:"Simple prepaid packs for flexible usage."} },
    { type:"pricing_agency_premium", theme:"auto", title:"Agency Pricing", buttonLabel:"Add Agency Pricing", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PricingAgencyPremiumPreview, badge:"PRO", payload:{type:"pricing_agency_premium",theme:"auto",eyebrow:"BUILT FOR AGENCIES",heading:"Pricing that grows with your client roster.",text:"Packages designed around client capacity and collaboration."} },
    { type:"pricing_feature_matrix_premium", theme:"auto", title:"Feature Matrix", buttonLabel:"Add Feature Matrix", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PricingFeatureMatrixPremiumPreview, badge:"PRO", payload:{type:"pricing_feature_matrix_premium",theme:"auto",eyebrow:"FEATURE MATRIX",heading:"Everything included, clearly mapped.",text:"A detailed grouped capability matrix for plan evaluation."} },
    { type:"cta_glass_premium", theme:"auto", title:"Glass CTA", buttonLabel:"Add Glass CTA", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:CtaGlassPremiumPreview, badge:"PRO", payload:{type:"cta_glass_premium",theme:"auto",eyebrow:"READY WHEN YOU ARE",heading:"Turn the next step into an easy yes.",text:"A premium frosted-glass conversion block."} },
    { type:"cta_gradient_premium", theme:"auto", title:"Gradient CTA", buttonLabel:"Add Gradient CTA", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:CtaGradientPremiumPreview, badge:"PRO", payload:{type:"cta_gradient_premium",theme:"auto",eyebrow:"LET’S BUILD WHAT’S NEXT",heading:"Make your next move impossible to miss.",text:"A high-impact gradient conversion block."} },
    { type:"cta_newsletter_premium", theme:"auto", title:"Newsletter CTA", buttonLabel:"Add Newsletter CTA", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:CtaNewsletterPremiumPreview, badge:"PRO", payload:{type:"cta_newsletter_premium",theme:"auto",eyebrow:"STAY IN THE LOOP",heading:"Useful updates, without the noise.",text:"A premium email signup call to action."} },
    { type:"cta_book_demo_premium", theme:"auto", title:"Book Demo CTA", buttonLabel:"Add Book Demo CTA", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:CtaBookDemoPremiumPreview, badge:"PRO", payload:{type:"cta_book_demo_premium",theme:"auto",eyebrow:"SEE IT IN ACTION",heading:"Book a focused product walkthrough.",text:"A scheduling-oriented premium sales CTA."} },
    { type:"cta_calendly_premium", theme:"auto", title:"Calendly CTA", buttonLabel:"Add Calendly CTA", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:CtaCalendlyPremiumPreview, badge:"PRO", payload:{type:"cta_calendly_premium",theme:"auto",eyebrow:"PICK A TIME",heading:"Schedule a conversation that works for you.",text:"A premium calendar-style scheduling CTA."} },
    { type:"cta_free_trial_premium", theme:"auto", title:"Free Trial CTA", buttonLabel:"Add Free Trial CTA", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:CtaFreeTrialPremiumPreview, badge:"PRO", payload:{type:"cta_free_trial_premium",theme:"auto",eyebrow:"TRY IT YOURSELF",heading:"Start exploring before you commit.",text:"A transparent premium trial conversion block."} },
    { type:"cta_countdown_premium", theme:"auto", title:"Countdown CTA", buttonLabel:"Add Countdown CTA", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:CtaCountdownPremiumPreview, badge:"PRO", payload:{type:"cta_countdown_premium",theme:"auto",eyebrow:"UPCOMING",heading:"A key moment is getting closer.",text:"A premium time-sensitive campaign block with editable deadline values."} },
    { type:"cta_limited_offer_premium", theme:"auto", title:"Limited Offer CTA", buttonLabel:"Add Limited Offer CTA", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:CtaLimitedOfferPremiumPreview, badge:"PRO", payload:{type:"cta_limited_offer_premium",theme:"auto",eyebrow:"LIMITED OFFER",heading:"Give visitors a clear reason to act now.",text:"A premium offer CTA with transparent terms."} },
    { type:"contact_split_premium", theme:"auto", title:"Split Contact", buttonLabel:"Add Split Contact", buttonClass:"bg-sky-600 hover:bg-sky-500", preview:ContactSplitPremiumPreview, badge:"PRO", payload:{type:"contact_split_premium",theme:"auto",eyebrow:"START A CONVERSATION",heading:"Tell us what you’re planning.",text:"A premium split contact section."} },
    { type:"contact_map_premium", theme:"auto", title:"Map Contact", buttonLabel:"Add Map Contact", buttonClass:"bg-sky-600 hover:bg-sky-500", preview:ContactMapPremiumPreview, badge:"PRO", payload:{type:"contact_map_premium",theme:"auto",eyebrow:"FIND US",heading:"Visit, call, or send a message.",text:"A premium location-first contact section.",directions_url:"https://www.google.com/maps/search/?api=1&query=Your+Business+Location"} },
    { type:"contact_appointment_premium", theme:"auto", title:"Appointment Booking", buttonLabel:"Add Appointment Booking", buttonClass:"bg-sky-600 hover:bg-sky-500", preview:ContactAppointmentPremiumPreview, badge:"PRO", payload:{type:"contact_appointment_premium",theme:"auto",eyebrow:"BOOK A TIME",heading:"Choose the right way to connect.",text:"A premium appointment-oriented contact section.",booking_url:"https://calendly.com/"} },
    { type:"contact_support_center_premium", theme:"auto", title:"Support Center", buttonLabel:"Add Support Center", buttonClass:"bg-sky-600 hover:bg-sky-500", preview:ContactSupportCenterPremiumPreview, badge:"PRO", payload:{type:"contact_support_center_premium",theme:"auto",eyebrow:"SUPPORT CENTER",heading:"Help is easy to find.",text:"A premium support hub with clear channels."} },
    { type:"contact_faq_premium", theme:"auto", title:"FAQ + Contact", buttonLabel:"Add FAQ + Contact", buttonClass:"bg-sky-600 hover:bg-sky-500", preview:ContactFaqPremiumPreview, badge:"PRO", payload:{type:"contact_faq_premium",theme:"auto",eyebrow:"QUESTIONS, ANSWERED",heading:"Get clarity before you get in touch.",text:"A premium FAQ and inquiry combination."} },
    { type:"contact_multistep_premium", theme:"auto", title:"Multi-step Contact", buttonLabel:"Add Multi-step Contact", buttonClass:"bg-sky-600 hover:bg-sky-500", preview:ContactMultiStepPremiumPreview, badge:"PRO", payload:{type:"contact_multistep_premium",theme:"auto",eyebrow:"START YOUR INQUIRY",heading:"A few details. One clear next step.",text:"A staged premium inquiry experience."} },
    { type:"contact_live_chat_premium", theme:"auto", title:"Live Chat CTA", buttonLabel:"Add Live Chat CTA", buttonClass:"bg-sky-600 hover:bg-sky-500", preview:ContactLiveChatPremiumPreview, badge:"PRO", payload:{type:"contact_live_chat_premium",theme:"auto",eyebrow:"CHAT WITH US",heading:"Prefer a quick conversation?",text:"A premium chat-entry call to action.",chat_url:"https://www.messenger.com/",secondary_url:"mailto:hello@example.com"} },
    { type:"blog_magazine_premium", theme:"auto", title:"Magazine Layout", buttonLabel:"Add Magazine Layout", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:BlogMagazinePremiumPreview, badge:"PRO", payload:{type:"blog_magazine_premium",theme:"auto",eyebrow:"THE JOURNAL",heading:"Stories, ideas, and perspectives.",text:"A premium magazine-style editorial section."} },
    { type:"blog_featured_article_premium", theme:"auto", title:"Featured Article", buttonLabel:"Add Featured Article", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:BlogFeaturedArticlePremiumPreview, badge:"PRO", payload:{type:"blog_featured_article_premium",theme:"auto",eyebrow:"FEATURED ARTICLE",heading:"One story, given room to breathe.",text:"A premium featured-story section."} },
    { type:"blog_editors_pick_premium", theme:"auto", title:"Editor\'s Pick", buttonLabel:"Add Editor\'s Pick", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:BlogEditorsPickPremiumPreview, badge:"PRO", payload:{type:"blog_editors_pick_premium",theme:"auto",eyebrow:"EDITOR\'S PICK",heading:"Worth your time this week.",text:"A curated premium editorial shortlist."} },
    { type:"blog_sidebar_news_premium", theme:"auto", title:"Sidebar News", buttonLabel:"Add Sidebar News", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:BlogSidebarNewsPremiumPreview, badge:"PRO", payload:{type:"blog_sidebar_news_premium",theme:"auto",eyebrow:"LATEST NEWS",heading:"Stay close to what matters.",text:"A premium news layout with a compact sidebar."} },
    { type:"blog_newsletter_premium", theme:"auto", title:"Blog Newsletter", buttonLabel:"Add Blog Newsletter", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:BlogNewsletterPremiumPreview, badge:"PRO", payload:{type:"blog_newsletter_premium",theme:"auto",eyebrow:"THE NEWSLETTER",heading:"Good ideas, delivered thoughtfully.",text:"A premium editorial newsletter signup section."} },
    { type:"blog_trending_premium", theme:"auto", title:"Trending", buttonLabel:"Add Trending", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:BlogTrendingPremiumPreview, badge:"PRO", payload:{type:"blog_trending_premium",theme:"auto",eyebrow:"FEATURED NOW",heading:"What readers can explore next.",text:"A premium curated-story list."} },
    { type:"blog_categories_grid_premium", theme:"auto", title:"Categories Grid", buttonLabel:"Add Categories Grid", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:BlogCategoriesGridPremiumPreview, badge:"PRO", payload:{type:"blog_categories_grid_premium",theme:"auto",eyebrow:"EXPLORE BY TOPIC",heading:"Find the ideas most useful to you.",text:"A premium category navigation grid."} },
    { type:"blog_author_profile_premium", theme:"auto", title:"Author Profile", buttonLabel:"Add Author Profile", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:BlogAuthorProfilePremiumPreview, badge:"PRO", payload:{type:"blog_author_profile_premium",theme:"auto",eyebrow:"MEET THE AUTHOR",heading:"The voice behind the ideas.",text:"A premium author profile section."} },
    { type:"footer_mega_premium", theme:"auto", title:"Mega Footer", buttonLabel:"Add Mega Footer", buttonClass:"bg-slate-700 hover:bg-slate-600", preview:FooterMegaPremiumPreview, badge:"PRO", payload:{type:"footer_mega_premium",theme:"auto",brand_name:"Your Brand",tagline:"A premium information-rich footer."} },
    { type:"footer_agency_premium", theme:"auto", title:"Agency Footer", buttonLabel:"Add Agency Footer", buttonClass:"bg-slate-700 hover:bg-slate-600", preview:FooterAgencyPremiumPreview, badge:"PRO", payload:{type:"footer_agency_premium",theme:"auto",eyebrow:"NEXT PROJECT",heading:"Have something ambitious in mind?"} },
    { type:"footer_saas_premium", theme:"auto", title:"SaaS Footer", buttonLabel:"Add SaaS Footer", buttonClass:"bg-slate-700 hover:bg-slate-600", preview:FooterSaasPremiumPreview, badge:"PRO", payload:{type:"footer_saas_premium",theme:"auto",brand_name:"Your Product",tagline:"A structured premium SaaS footer."} },
    { type:"footer_luxury_premium", theme:"auto", title:"Luxury Footer", buttonLabel:"Add Luxury Footer", buttonClass:"bg-slate-700 hover:bg-slate-600", preview:FooterLuxuryPremiumPreview, badge:"PRO", payload:{type:"footer_luxury_premium",theme:"auto",eyebrow:"ESTABLISHED WITH INTENT",heading:"Crafted for people who value the details."} },
    { type:"footer_dark_premium", theme:"auto", title:"Dark Footer", buttonLabel:"Add Dark Footer", buttonClass:"bg-slate-800 hover:bg-slate-700", preview:FooterDarkPremiumPreview, badge:"PRO", payload:{type:"footer_dark_premium",theme:"auto",eyebrow:"STAY CONNECTED",brand_name:"Your Brand",tagline:"A confident premium dark footer."} },
    { type:"footer_minimal_premium", theme:"auto", title:"Minimal Footer", buttonLabel:"Add Minimal Footer", buttonClass:"bg-slate-600 hover:bg-slate-500", preview:FooterMinimalPremiumPreview, badge:"PRO", payload:{type:"footer_minimal_premium",theme:"auto",brand_name:"Your Brand",tagline:"A quiet premium footer."} },
    {
        type: "pricing_cards",
        theme: "auto",
        title: "Pricing Cards",
        buttonLabel: "Install Pricing Cards",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: PricingCardsPreview,
        payload: {
            type: "pricing_cards",
            tagline: "SIMPLE PRICING",
            heading: "Choose The Perfect Plan",
            text: "Flexible pricing options designed for individuals, growing businesses, and enterprise teams."
        }
    },

    {
        type: "hero_parallax",
        theme: "auto",
        title: "Hero Parallax",
        buttonLabel: "Unlock Hero Parallax",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroParallaxPreview,
        badge: "PRO",
        payload: {
            type: "hero_parallax",
            eyebrow: "INTRODUCING A NEW PERSPECTIVE",
            heading: "Move beyond the ordinary.",
            text: "Create a memorable first impression with cinematic depth, confident typography, and a clear next step.",
            primary_label: "Start a project",
            primary_url: "/contact",
            secondary_label: "Explore our work",
            secondary_url: "/about",
            image_url: "/storage/cms-images/background/background-1.avif",
            overlayOpacity: 64,
            parallaxSpeed: 24,
            contentAlign: "left",
            height: "screen",
            scroll_label: "Scroll to explore"
        }
    },

    {
    type: "hero_background_image",
        theme: "auto",
        title: "Hero Background Image",
        buttonLabel: "Install Hero Background",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: HeroBackgroundImagePreview,
        payload: {
            type: "hero_background_image",
            tagline: "WELCOME TO OUR COMPANY",
            heading: "Build Beautiful Websites With Confidence",
            text: "Create modern, responsive websites using reusable blocks, AI-generated content, and powerful customization tools.",
            button_label: "Get Started",
            button_url: "/contact",
            image_url: "/storage/cms-images/background/background-1.avif",
            overlayOpacity: 50,
            textAlign: "center",
            height: "screen"
        }
    },

    {
        type: "hero_slider_fade",
        theme: "auto",
        title: "Hero Slider Fade",
        buttonLabel: "Add Fade Slider",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroSliderFadePreview,
        payload: {
            type: "hero_slider_fade",
            theme: "auto",
            autoplay: true,
            interval: 5000,
            pause_on_hover: true,
            show_dots: true,
            show_arrows: true,
            slides: [
                {
                    image_url: "/storage/cms-images/background/background-1.avif",
                    eyebrow: "BUILT FOR WHAT'S NEXT",
                    heading: "A stronger first impression",
                    description: "Introduce your business with a clear message and a confident next step.",
                    button_1_text: "Get Started",
                    button_1_url: "/contact",
                    button_2_text: "Explore Services",
                    button_2_url: "/contact",
                    button_3_text: "View Our Work",
                    button_3_url: "/contact",
                    button_4_text: "Learn More",
                    button_4_url: "/contact"
                },
                {
                    image_url: "/storage/cms-images/background/background-2.avif",
                    eyebrow: "DESIGNED AROUND YOU",
                    heading: "Show what makes you different",
                    description: "Highlight your services, experience, and the value customers can expect.",
                    button_1_text: "Explore Services",
                    button_1_url: "/contact",
                    button_2_text: "Our Process",
                    button_2_url: "/contact",
                    button_3_text: "Case Studies",
                    button_3_url: "/contact",
                    button_4_text: "See Details",
                    button_4_url: "/contact"
                },
                {
                    image_url: "/storage/cms-images/background/background-3.avif",
                    eyebrow: "READY WHEN YOU ARE",
                    heading: "Turn interest into action",
                    description: "Give visitors a simple, direct path to contact, book, or learn more.",
                    button_1_text: "Contact Us",
                    button_1_url: "/contact",
                    button_2_text: "Book a Call",
                    button_2_url: "/contact",
                    button_3_text: "View Pricing",
                    button_3_url: "/contact",
                    button_4_text: "Get Started",
                    button_4_url: "/contact"
                }
            ]
        }
    },

    {
        type: "hero_editorial_overlay",
        theme: "auto",
        title: "Hero Editorial Overlay",
        buttonLabel: "Add Hero",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroEditorialOverlayPreview,
        payload: {
            type: "hero_editorial_overlay",
            tagline: "BUILT FOR WHAT COMES NEXT",
            heading: "A stronger first impression starts here.",
            text: "Bring your story, services, and next step into focus with a confident, image-led introduction.",
            primary_label: "Start a project",
            primary_url: "/contact",
            secondary_label: "Explore services",
            secondary_url: "/about",
            image_url: "/storage/cms-images/background/background-1.avif",
            overlayOpacity: 72,
            height: "screen",
        },
    },

    {
        type: "hero_split_editorial",
        theme: "auto",
        title: "Hero Split Editorial",
        buttonLabel: "Add Editorial Hero",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: HeroSplitEditorialPreview,
        payload: {
            type: "hero_split_editorial",
            theme: "auto",
            eyebrow: "A NEW STANDARD",
            editorial_index: "01",
            heading: "Designed to make the right first impression.",
            text: "A considered digital experience that brings your story, expertise, and next step into one confident opening statement.",
            primary_label: "Start a conversation",
            primary_url: "/contact",
            secondary_label: "Explore our work",
            secondary_url: "/about",
            proof_value: "15+",
            proof_label: "Years of considered craft",
            image_caption: "Built with clarity, confidence, and care.",
            image_url: "/storage/cms-images/background/background-1.avif",
        },
    },

    {
        type: "hero_floating_glass",
        theme: "auto",
        title: "Floating Glass Hero",
        buttonLabel: "Add Glass Hero",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: HeroFloatingGlassPreview,
        payload: {
            type: "hero_floating_glass", theme: "auto", eyebrow: "BUILT FOR MOMENTUM",
            heading: "A clearer way to move your business forward.",
            text: "Bring your offer, proof, and next step together in one immersive opening experience.",
            primary_label: "Start a project", primary_url: "/contact", secondary_label: "See how it works", secondary_url: "/about",
            glass_title: "Made for decisive teams", glass_text: "A focused digital experience designed to turn attention into action.",
            metric_value: "3.2x", metric_label: "Faster path to launch", badge_one: "Strategy-led", badge_two: "Conversion-ready",
            image_url: "/storage/cms-images/background/background-1.avif",
        },
    },

    {
        type: "hero_saas_dashboard",
        theme: "auto",
        title: "SaaS Dashboard Hero",
        buttonLabel: "Add SaaS Hero",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: HeroSaasDashboardPreview,
        payload: {
            type: "hero_saas_dashboard", theme: "auto", eyebrow: "THE OPERATING SYSTEM FOR GROWTH",
            heading: "Turn your workflow into a clear, measurable advantage.",
            text: "Bring projects, performance, and customer momentum into one focused workspace built for modern teams.",
            primary_label: "Start building", primary_url: "/contact", secondary_label: "View product tour", secondary_url: "/about",
            dashboard_title: "Workspace overview", dashboard_subtitle: "Live performance across your team",
            metric_one_value: "42%", metric_one_label: "Faster delivery", metric_two_value: "18.4k", metric_two_label: "Monthly actions",
            metric_three_value: "99.9%", metric_three_label: "Platform uptime", chart_label: "Growth this quarter",
            logo_one: "NORTHSTAR", logo_two: "ARC LABS", logo_three: "SCALEWORKS", logo_four: "FOUNDRY",
        },
    },

    {
        type: "hero_luxury_fullscreen", theme: "auto", title: "Luxury Fullscreen Hero", buttonLabel: "Add Luxury Hero", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: HeroLuxuryFullscreenPreview,
        payload: { type: "hero_luxury_fullscreen", theme: "auto", eyebrow: "THE ART OF ARRIVAL", heading: "Quiet confidence, made unforgettable.", text: "A refined opening statement for brands defined by craft, place, and exceptional attention to detail.", primary_label: "Discover the collection", primary_url: "/contact", secondary_label: "Our story", secondary_url: "/about", location_label: "Crafted in exceptional detail", edition_label: "Private Edition 01", image_url: "/storage/cms-images/background/background-1.avif" },
    },

    {
        type: "hero_video_premium", theme: "auto", title: "Video Hero Premium", buttonLabel: "Add Premium Video Hero", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: HeroVideoPremiumPreview,
        payload: { type: "hero_video_premium", theme: "auto", eyebrow: "A STORY IN MOTION", heading: "Make the first few seconds impossible to forget.", text: "Use cinematic movement, focused copy, and one clear next step to introduce your brand with confidence.", primary_label: "Start the experience", primary_url: "/contact", secondary_label: "Watch the story", secondary_url: "/about", media_badge: "Cinematic brand experience", scroll_label: "Scroll to explore", video_url: "/storage/cms-videos/hero-placeholder.mp4", poster_image_url: "/storage/cms-images/background/background-1.avif" },
    },

    {
        type: "hero_ai_conversation", theme: "auto", title: "AI Conversation Hero", buttonLabel: "Add AI Conversation Hero", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: HeroAiConversationPreview,
        payload: { type: "hero_ai_conversation", theme: "auto", eyebrow: "AI THAT WORKS WITH YOU", heading: "Turn a simple prompt into meaningful progress.", text: "Show visitors how your AI listens, responds, and helps them move from idea to action in one focused experience.", primary_label: "Start building", primary_url: "/contact", secondary_label: "See how it works", secondary_url: "/about", assistant_label: "Cosmic AI", assistant_status: "Ready to help", user_message: "Create a polished campaign page for our next launch.", assistant_message: "I’ll shape the structure, write the first draft, and prepare a responsive page you can refine.", prompt_placeholder: "Ask AI to create, improve, or explain...", chip_one: "Strategy-aware", chip_two: "Editable output", chip_three: "Built to publish" },
    },

    {
        type: "hero_agency_showcase", theme: "auto", title: "Agency Showcase Hero", buttonLabel: "Add Agency Hero", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: HeroAgencyShowcasePreview,
        payload: { type: "hero_agency_showcase", theme: "auto", eyebrow: "DESIGN THAT MOVES BUSINESS FORWARD", heading: "From overlooked to unforgettable.", text: "Pair strategic thinking with polished execution, then show visitors the difference your agency creates at a glance.", primary_label: "Start a project", primary_url: "/contact", secondary_label: "View case studies", secondary_url: "/about", before_label: "Before", before_caption: "A fragmented digital experience", after_label: "After", after_caption: "A focused brand built to convert", metric_one_value: "48%", metric_one_label: "More qualified enquiries", metric_two_value: "2.4x", metric_two_label: "Higher conversion rate", metric_three_value: "6 weeks", metric_three_label: "From strategy to launch", logo_one: "NORTHSTAR", logo_two: "MORROW & CO", logo_three: "FOUNDRY", logo_four: "KINSHIP", before_image_url: "/storage/cms-images/background/background-2.avif", after_image_url: "/storage/cms-images/background/background-1.avif" },
    },


    {
        type: "hero_bento_premium", theme: "auto", title: "Bento Hero", buttonLabel: "Add Bento Hero", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: HeroBentoPremiumPreview,
        payload: { type: "hero_bento_premium", theme: "auto", eyebrow: "BUILT TO STAND APART", heading: "One clear idea, expressed from every angle.", text: "Bring your message, proof, imagery, and next step together in a flexible bento composition designed for modern brands.", primary_label: "Start a project", primary_url: "/contact", secondary_label: "Explore the work", secondary_url: "/about", image_url: "/storage/cms-images/background/background-1.avif", image_label: "Featured perspective", metric_value: "3.4x", metric_label: "More engaged visitors", proof_title: "Built around clarity", proof_text: "A modular opening experience with strong hierarchy and deliberate rhythm.", card_one_label: "Strategy-led", card_two_label: "Responsive by design", card_three_label: "Ready to publish" },
    },


    {
        type: "services_bento_premium", theme: "auto", title: "Bento Services Premium", buttonLabel: "Add Premium Services", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: ServicesBentoPremiumPreview,
        payload: { type: "services_bento_premium", theme: "auto", eyebrow: "SERVICES DESIGNED AROUND MOMENTUM", heading: "Specialist thinking, connected into one clear growth system.", text: "Combine strategy, design, technology, and optimisation in a flexible service model built around the way your business actually works.", primary_label: "Explore our services", primary_url: "/contact", featured_number: "01", featured_title: "Digital strategy", featured_text: "Clarify the opportunity, align the priorities, and turn ambitious goals into an actionable roadmap.", featured_meta: "Research · Positioning · Roadmaps", service_two_number: "02", service_two_title: "Experience design", service_two_text: "Shape intuitive journeys and interfaces that make every interaction feel considered.", service_three_number: "03", service_three_title: "Web platforms", service_three_text: "Build fast, scalable digital foundations designed to evolve with your team.", service_four_number: "04", service_four_title: "Growth systems", service_four_text: "Connect content, campaigns, and measurement into a repeatable growth engine.", service_five_number: "05", service_five_title: "Ongoing optimisation", service_five_text: "Improve performance continuously through testing, insight, and focused iteration.", proof_value: "5 disciplines", proof_label: "One integrated senior team" },
    },

    {
        type: "services_pricing_comparison", theme: "auto", title: "Pricing Comparison Premium", buttonLabel: "Add Pricing Comparison", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: ServicesPricingComparisonPreview,
        payload: { type: "services_pricing_comparison", theme: "auto", eyebrow: "CHOOSE THE RIGHT LEVEL OF SUPPORT", heading: "Clear packages. No hidden complexity.", text: "Compare the level of strategy, delivery, and ongoing support included in each engagement.", starter_name: "Essential", starter_price: "$2,500", starter_period: "from", starter_description: "A focused foundation for one clear business priority.", starter_button_label: "Choose Essential", starter_button_url: "/contact", growth_name: "Growth", growth_price: "$6,500", growth_period: "from", growth_description: "A complete growth engagement for ambitious teams.", growth_button_label: "Choose Growth", growth_button_url: "/contact", growth_badge: "MOST POPULAR", pro_name: "Partner", pro_price: "Custom", pro_period: "", pro_description: "Embedded senior support for complex, ongoing work.", pro_button_label: "Talk to our team", pro_button_url: "/contact", feature_one: "Strategic discovery", starter_one: "Included", growth_one: "Extended", pro_one: "Ongoing", feature_two: "Design direction", starter_two: "1 concept", growth_two: "3 concepts", pro_two: "Unlimited scope", feature_three: "Delivery support", starter_three: "Launch", growth_three: "Launch + optimise", pro_three: "Embedded team", feature_four: "Reporting", starter_four: "Summary", growth_four: "Monthly", pro_four: "Custom dashboard", feature_five: "Response time", starter_five: "3 business days", growth_five: "1 business day", pro_five: "Priority", feature_six: "Best for", starter_six: "Focused projects", growth_six: "Growing teams", pro_six: "Complex programmes", footnote: "Every engagement is tailored before work begins. Prices shown are editable starting points." },
    },


    {
        type: "services_feature_comparison", theme: "auto", title: "Feature Comparison Premium", buttonLabel: "Add Feature Comparison", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: ServicesFeatureComparisonPreview,
        payload: { type: "services_feature_comparison", theme: "auto", eyebrow: "COMPARE THE APPROACH", heading: "Choose the level of capability your next stage needs.", text: "See how each service model differs across strategy, delivery, collaboration, and ongoing support.", option_one_name: "Foundation", option_one_kicker: "Focused project", option_one_text: "A clear, senior-led engagement for one defined priority.", option_two_name: "Growth System", option_two_kicker: "Most versatile", option_two_text: "Connected strategy and delivery for teams building momentum.", option_two_badge: "RECOMMENDED", option_three_name: "Embedded Partner", option_three_kicker: "Ongoing capability", option_three_text: "Flexible senior support across complex, evolving priorities.", feature_one: "Strategic direction", option_one_one: "Focused", option_two_one: "Integrated", option_three_one: "Embedded", feature_two: "Research depth", option_one_two: "Essentials", option_two_two: "Extended", option_three_two: "Continuous", feature_three: "Design systems", option_one_three: "Core", option_two_three: "Scalable", option_three_three: "Multi-brand", feature_four: "Delivery support", option_one_four: "Launch", option_two_four: "Launch + optimise", option_three_four: "Ongoing", feature_five: "Team access", option_one_five: "Lead specialist", option_two_five: "Cross-functional", option_three_five: "Dedicated pod", feature_six: "Reporting", option_one_six: "Wrap-up", option_two_six: "Monthly", option_three_six: "Custom cadence", feature_seven: "Best suited to", option_one_seven: "One clear priority", option_two_seven: "Growing teams", option_three_seven: "Complex programmes", feature_eight: "Engagement style", option_one_eight: "Fixed scope", option_two_eight: "Phased roadmap", option_three_eight: "Flexible retainer", primary_label: "Discuss the right approach", primary_url: "/contact", footnote: "Every engagement is shaped around your goals, team, and delivery requirements." },
    },


    {
        type: "services_hover_cards", theme: "auto", title: "Hover Cards Premium", buttonLabel: "Add Hover Cards", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: ServicesHoverCardsPreview,
        payload: { type: "services_hover_cards", theme: "auto", eyebrow: "EXPLORE OUR CAPABILITIES", heading: "Specialist services, designed to work better together.", text: "Move from first idea to measurable improvement with senior support across strategy, design, technology, and growth.", primary_label: "Discuss your project", primary_url: "/contact", card_one_number: "01", card_one_title: "Digital strategy", card_one_summary: "Set the direction.", card_one_text: "Clarify the opportunity, align priorities, and turn ambition into a focused roadmap.", card_one_link: "Explore strategy", card_two_number: "02", card_two_title: "Brand systems", card_two_summary: "Build recognition.", card_two_text: "Create a flexible visual and verbal system that keeps every touchpoint consistent.", card_two_link: "Explore branding", card_three_number: "03", card_three_title: "Experience design", card_three_summary: "Make journeys intuitive.", card_three_text: "Shape clear user flows and polished interfaces around the needs of real customers.", card_three_link: "Explore experience", card_four_number: "04", card_four_title: "Web platforms", card_four_summary: "Create a stronger foundation.", card_four_text: "Build fast, responsive websites and platforms designed to evolve with your team.", card_four_link: "Explore platforms", card_five_number: "05", card_five_title: "Growth systems", card_five_summary: "Connect the funnel.", card_five_text: "Bring campaigns, content, conversion, and measurement into one repeatable system.", card_five_link: "Explore growth", card_six_number: "06", card_six_title: "Optimisation", card_six_summary: "Keep improving.", card_six_text: "Use focused testing and insight to improve performance after launch.", card_six_link: "Explore optimisation" },
    },


    { type:"services_sticky_scroll", theme:"auto", title:"Sticky Scroll Services", buttonLabel:"Add Sticky Services", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:ServicesStickyScrollPreview, payload:{type:"services_sticky_scroll",theme:"auto",eyebrow:"WHAT WE DO",heading:"Specialist services, connected by one clear strategy.",text:"Use this layout when each service deserves context, not just a card title.",primary_label:"Start a project",primary_url:"/contact"} },
    { type:"services_horizontal", theme:"auto", title:"Horizontal Services", buttonLabel:"Add Horizontal Services", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:ServicesHorizontalPreview, payload:{type:"services_horizontal",theme:"auto",eyebrow:"CAPABILITIES",heading:"Explore the work from left to right.",text:"A strong option for a focused service catalogue with short, confident descriptions.",primary_label:"Talk to us",primary_url:"/contact"} },
    { type:"services_interactive_tabs", theme:"auto", title:"Interactive Tabs", buttonLabel:"Add Service Tabs", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:ServicesInteractiveTabsPreview, payload:{type:"services_interactive_tabs",theme:"auto",eyebrow:"SERVICES",heading:"One team. Four connected disciplines.",text:"Let visitors move between service areas without leaving the section.",primary_label:"Discuss your needs",primary_url:"/contact"} },
    { type:"services_mega_grid", theme:"auto", title:"Mega Grid", buttonLabel:"Add Mega Grid", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:ServicesMegaGridPreview, payload:{type:"services_mega_grid",theme:"auto",eyebrow:"FULL CAPABILITY",heading:"Everything needed to move from idea to growth.",text:"Use the mega grid when the business offers a wider range of connected specialist services.",primary_label:"View all capabilities",primary_url:"/services"} },

    { type:"portfolio_masonry", theme:"auto", title:"Masonry Portfolio", buttonLabel:"Add Masonry Portfolio", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PortfolioMasonryPreview, payload:{type:"portfolio_masonry",theme:"auto",eyebrow:"SELECTED WORK",heading:"A portfolio with range, rhythm, and room to breathe.",text:"Use varied image proportions to make a body of work feel curated rather than templated.",primary_label:"View all projects",primary_url:"/projects"} },
    { type:"portfolio_pinterest", theme:"auto", title:"Pinterest Portfolio", buttonLabel:"Add Pinterest Portfolio", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PortfolioPinterestPreview, payload:{type:"portfolio_pinterest",theme:"auto",eyebrow:"VISUAL ARCHIVE",heading:"A living board of ideas, details, and finished work.",text:"Best for image-first businesses where the visual library itself is part of the story.",primary_label:"Explore the archive",primary_url:"/projects"} },
    { type:"portfolio_hover_video", theme:"auto", title:"Hover Video Portfolio", buttonLabel:"Add Hover Video", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PortfolioHoverVideoPreview, payload:{type:"portfolio_hover_video",theme:"auto",eyebrow:"WORK IN MOTION",heading:"See the work before opening the case study.",text:"Poster-first project cards with optional motion previews.",primary_label:"View all case studies",primary_url:"/projects",project_one_video_url:"https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4"} },
    { type:"portfolio_case_study", theme:"auto", title:"Case Study Portfolio", buttonLabel:"Add Case Study", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PortfolioCaseStudyPreview, payload:{type:"portfolio_case_study",theme:"auto",eyebrow:"FEATURED CASE STUDY",heading:"Turn one strong project into a story worth reading.",text:"Lead with the work, then explain the challenge, approach, and outcome.",primary_label:"Read the case study",primary_url:"/projects"} },
    { type:"portfolio_before_after", theme:"auto", title:"Before After Portfolio", buttonLabel:"Add Before / After", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PortfolioBeforeAfterPreview, payload:{type:"portfolio_before_after",theme:"auto",eyebrow:"BEFORE / AFTER",heading:"Show the transformation, not just the finished frame.",text:"Compare what changed and why it matters.",primary_label:"View project",primary_url:"/projects"} },
    { type:"portfolio_filterable", theme:"auto", title:"Filterable Portfolio", buttonLabel:"Add Filterable Portfolio", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PortfolioFilterablePreview, payload:{type:"portfolio_filterable",theme:"auto",eyebrow:"PROJECT INDEX",heading:"Find the work that matters to you.",text:"Organise a mixed body of work into clear categories.",primary_label:"Start a project",primary_url:"/contact"} },
    { type:"portfolio_animated", theme:"auto", title:"Animated Portfolio", buttonLabel:"Add Animated Portfolio", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PortfolioAnimatedPreview, payload:{type:"portfolio_animated",theme:"auto",eyebrow:"SELECTED PROJECTS",heading:"A portfolio that feels alive before you click.",text:"Lightweight motion and editorial project cards.",primary_label:"Explore all work",primary_url:"/projects"} },
    { type:"portfolio_project_timeline", theme:"auto", title:"Project Timeline", buttonLabel:"Add Project Timeline", buttonClass:"bg-emerald-600 hover:bg-emerald-500", preview:PortfolioProjectTimelinePreview, payload:{type:"portfolio_project_timeline",theme:"auto",eyebrow:"PROJECT TIMELINE",heading:"From first conversation to finished experience.",text:"Reveal the progression behind a featured project.",primary_label:"Read full case study",primary_url:"/projects"} },

    { type: "about_timeline_story", theme: "auto", title: "Timeline Story", buttonLabel: "Add Timeline Story", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: AboutTimelineStoryPreview, payload: { type:"about_timeline_story", theme:"auto", eyebrow:"OUR STORY", heading:"Built one meaningful chapter at a time.", text:"Show how the business evolved without turning the About page into a wall of copy.", primary_label:"Meet the team", primary_url:"/team", year_one:"2018", title_one:"The beginning", text_one:"A focused idea became a practical service built around real customer needs.", year_two:"2021", title_two:"Growing with purpose", text_two:"The team expanded its capabilities while keeping the experience personal and clear.", year_three:"2024", title_three:"A stronger platform", text_three:"New systems, sharper positioning, and broader expertise created room for the next stage.", year_four:"Today", title_four:"What comes next", text_four:"We continue to improve the work, the process, and the value we create for every client." } },
    { type: "about_founder_story", theme: "auto", title: "Founder Story", buttonLabel: "Add Founder Story", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: AboutFounderStoryPreview, payload: { type:"about_founder_story", theme:"auto", eyebrow:"FOUNDER STORY", heading:"Built from a belief that better work starts with better listening.", text:"Use this space for the human reason behind the business—the problem the founder wanted to solve and the standard they wanted to set.", quote:"Do the useful work first. Make it beautiful second. Then keep improving both.", founder_name:"Alex Morgan", founder_role:"Founder & Creative Director", principle_one:"Clarity over complexity", principle_two:"Craft with purpose", principle_three:"Long-term partnerships", image_url:"/storage/cms-images/background/background-1.avif", primary_label:"Our approach", primary_url:"/about" } },
    { type: "about_mission_grid", theme: "auto", title: "Mission Grid", buttonLabel: "Add Mission Grid", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: AboutMissionGridPreview, payload: { type:"about_mission_grid", theme:"auto", eyebrow:"WHY WE EXIST", heading:"A clear mission, translated into everyday decisions.", text:"Make the purpose of the company concrete by connecting the big idea to the way the team actually works.", mission_label:"MISSION", mission_title:"Make complex things feel simple.", mission_text:"Create useful experiences that remove friction and help people move forward with confidence.", vision_label:"VISION", vision_title:"Raise the standard", vision_text:"Build a business known for thoughtful work, dependable delivery, and relationships that last.", value_one_title:"Stay curious", value_one_text:"Ask better questions before reaching for familiar answers.", value_two_title:"Own the outcome", value_two_text:"Take responsibility for the result, not just the task.", value_three_title:"Keep it human", value_three_text:"Communicate clearly, listen carefully, and respect people’s time." } },
    { type: "about_interactive_stats", theme: "auto", title: "Interactive Company Stats", buttonLabel: "Add Company Stats", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: AboutInteractiveStatsPreview, payload: { type:"about_interactive_stats", theme:"auto", eyebrow:"THE COMPANY IN NUMBERS", heading:"Proof that the work has momentum behind it.", text:"Use meaningful company metrics to give visitors a quick sense of scale, experience, reach, or progress.", stat_one_value:"12+", stat_one_label:"Years building", stat_one_text:"Experience shaped across changing markets and technologies.", stat_two_value:"48", stat_two_label:"Projects this year", stat_two_text:"Focused engagements delivered across strategy, design, and technology.", stat_three_value:"6", stat_three_label:"Core disciplines", stat_three_text:"A connected team covering the work from direction through delivery.", stat_four_value:"4", stat_four_label:"Markets served", stat_four_text:"Local understanding combined with a broader point of view.", footnote:"Replace starter metrics with verified company data before publishing." } },
    { type: "about_brand_journey", theme: "auto", title: "Brand Journey", buttonLabel: "Add Brand Journey", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: AboutBrandJourneyPreview, payload: { type:"about_brand_journey", theme:"auto", eyebrow:"BRAND JOURNEY", heading:"How the brand became what it is today.", text:"Connect the moments that shaped the identity, the offer, and the way customers experience the business.", chapter_one_label:"ORIGIN", chapter_one_title:"Start with the problem", chapter_one_text:"The brand began with a simple observation: customers deserved a clearer, more thoughtful option.", chapter_two_label:"REFINEMENT", chapter_two_title:"Find the point of view", chapter_two_text:"The offer became sharper, the language more confident, and the experience more recognisable.", chapter_three_label:"EXPANSION", chapter_three_title:"Grow without losing focus", chapter_three_text:"New capabilities were added while the core promise stayed consistent.", chapter_four_label:"NOW", chapter_four_title:"Build the next chapter", chapter_four_text:"The brand keeps evolving around what customers value most.", primary_label:"See our work", primary_url:"/projects" } },
    { type: "about_awards_timeline", theme: "auto", title: "Awards Timeline", buttonLabel: "Add Awards Timeline", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: AboutAwardsTimelinePreview, payload: { type:"about_awards_timeline", theme:"auto", eyebrow:"RECOGNITION", heading:"Selected recognition along the way.", text:"Use this section only for awards, shortlistings, certifications, or recognitions the business can verify.", award_one_year:"2022", award_one_title:"Industry Award", award_one_org:"Awarding organisation", award_two_year:"2023", award_two_title:"Design Recognition", award_two_org:"Awarding organisation", award_three_year:"2024", award_three_title:"Customer Experience Award", award_three_org:"Awarding organisation", award_four_year:"2025", award_four_title:"Innovation Recognition", award_four_org:"Awarding organisation", footnote:"Replace all starter entries with verified recognition before publishing." } },
    { type: "about_culture_section", theme: "auto", title: "Culture Section", buttonLabel: "Add Culture Section", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: AboutCultureSectionPreview, payload: { type:"about_culture_section", theme:"auto", eyebrow:"HOW WE WORK", heading:"A culture built around useful work and good people.", text:"Show the behaviours that shape everyday decisions, collaboration, and the experience of working with the team.", pillar_one_title:"Be clear", pillar_one_text:"Say what matters, remove ambiguity, and make the next step easy to understand.", pillar_two_title:"Stay curious", pillar_two_text:"Ask better questions and keep learning instead of defaulting to familiar answers.", pillar_three_title:"Own the outcome", pillar_three_text:"Take responsibility for the result and help the whole team move forward.", pillar_four_title:"Respect the craft", pillar_four_text:"Care about the details without losing sight of the customer or the goal.", closing_line:"The best culture is visible in the work, not just written on the wall." } },
    { type: "about_office_gallery", theme: "auto", title: "Office Gallery", buttonLabel: "Add Office Gallery", buttonClass: "bg-emerald-600 hover:bg-emerald-500", preview: AboutOfficeGalleryPreview, payload: { type:"about_office_gallery", theme:"auto", eyebrow:"INSIDE THE STUDIO", heading:"A place designed for focused work and good collaboration.", text:"Use real workplace, studio, venue, clinic, showroom, or team-environment photography to make the business feel tangible.", image_one_url:"/storage/cms-images/background/background-1.avif", image_one_caption:"Main workspace", image_two_url:"/storage/cms-images/background/background-2.avif", image_two_caption:"Collaboration space", image_three_url:"/storage/cms-images/background/background-3.avif", image_three_caption:"Details that make it ours" } },
    {
        type: "hero_split_image",
        theme: "auto",
        title: "Hero Split Image",
        buttonLabel: "Add Split Hero",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: HeroSplitImagePreview,
        payload: {
            type: "hero_split_image",
            tagline: "BUILT FOR WHAT'S NEXT",
            heading: "Make a stronger first impression.",
            text: "Tell your story clearly, show what makes your business different, and guide visitors toward the next step.",
            primary_label: "Get started",
            primary_url: "/contact",
            secondary_label: "Learn more",
            secondary_url: "/about",
            trust_line: "Trusted by customers who value quality work.",
            image_badge: "Serving your community",
            image_url: "/storage/cms-images/background/background-1.avif",
        },
    },

    {
        type: "image_cta_banner",
        theme: "auto",
        title: "Image CTA Banner",
        buttonLabel: "Add CTA Banner",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: ImageCtaBannerPreview,
        payload: {
            type: "image_cta_banner",
            eyebrow: "READY WHEN YOU ARE",
            heading: "Let’s make your next step simple.",
            text: "Talk with our team and get a clear plan for moving forward.",
            primary_label: "Get started",
            primary_url: "/contact",
            secondary_label: "Learn more",
            secondary_url: "/about",
            image_url: "/storage/cms-images/background/background-1.avif",
            overlayOpacity: 76,
        },
    },

    {
        type: "contact_form_modern",
        theme: "auto",
        title: "Contact Form",
        buttonLabel: "Choose contact form",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: ContactFormPreview,
        payload: {
            type: "contact_form_modern",
            theme: "auto",
            eyebrow: "START A CONVERSATION",
            heading: "Let’s talk about what’s next.",
            text: "Tell us a little about your goals and our team will help you find the right next step.",
            email: "hello@example.com",
            phone: "+1 (555) 010-0200",
            address: "Available by appointment",
            submit_label: "Send inquiry",
            fields: [
                { id: "name", name: "name", type: "text", label: "Name", placeholder: "Your name", required: true },
                { id: "email", name: "email", type: "email", label: "Email", placeholder: "you@example.com", required: true },
                { id: "phone", name: "phone", type: "tel", label: "Phone", placeholder: "Your phone number", required: false },
                { id: "message", name: "message", type: "textarea", label: "How can we help?", placeholder: "Tell us a little about your project", required: true },
            ],
        },
    },
    {
        type: "faq_accordion",
        theme: "auto",
        title: "FAQ Accordion",
        buttonLabel: "Choose FAQ layout",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: FaqAccordionPreview,
        payload: {
            type: "faq_accordion",
            theme: "auto",
            eyebrow: "HELPFUL ANSWERS",
            heading: "Questions, answered clearly.",
            text: "Everything visitors need to know before taking the next step.",
            faqs: [
                { question: "What services do you offer?", answer: "We provide clear, practical support tailored to your needs." },
                { question: "How do I get started?", answer: "Reach out with a short note and we will help you choose the right next step." },
                { question: "Can I request a consultation?", answer: "Yes. Use the contact details on this page to arrange a conversation." },
                { question: "What should I prepare?", answer: "Share your goals, timeline, and any questions you would like us to cover." },
            ],
        },
    },
    { type:"faq_accordion_pro", theme:"auto", title:"Accordion Pro", buttonLabel:"Add Accordion Pro", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:FaqAccordionProPreview, badge:"PRO", payload:{type:"faq_accordion_pro",theme:"auto",eyebrow:"FREQUENTLY ASKED",heading:"Answers without the fine-print feeling.",text:"Keep the most useful questions easy to scan."} },
    { type:"faq_search_premium", theme:"auto", title:"Search FAQ", buttonLabel:"Add Search FAQ", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:FaqSearchPremiumPreview, badge:"PRO", payload:{type:"faq_search_premium",theme:"auto",eyebrow:"SEARCH HELP",heading:"Find the answer in seconds.",text:"Search across useful business questions.",search_placeholder:"Search questions…"} },
    { type:"faq_categories_premium", theme:"auto", title:"FAQ Categories", buttonLabel:"Add FAQ Categories", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:FaqCategoriesPremiumPreview, badge:"PRO", payload:{type:"faq_categories_premium",theme:"auto",eyebrow:"BROWSE BY TOPIC",heading:"Everything is easier when it is organised.",text:"Group related questions into useful topics."} },
    { type:"faq_support_portal_premium", theme:"auto", title:"Support Portal", buttonLabel:"Add Support Portal", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:FaqSupportPortalPremiumPreview, badge:"PRO", payload:{type:"faq_support_portal_premium",theme:"auto",eyebrow:"SUPPORT PORTAL",heading:"Start with the answer. Escalate when you need to.",text:"Give visitors useful self-service support paths.",primary_label:"Contact support",primary_url:"/contact"} },
    { type:"faq_documentation_premium", theme:"auto", title:"Documentation", buttonLabel:"Add Documentation", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:FaqDocumentationPremiumPreview, badge:"PRO", payload:{type:"faq_documentation_premium",theme:"auto",eyebrow:"DOCUMENTATION",heading:"Guidance that stays easy to navigate.",text:"Organise real help content into clear documentation topics.",primary_label:"View guide",primary_url:"/resources"} },
    { type:"lead_magnet_premium", theme:"auto", title:"Lead Magnet", buttonLabel:"Add Lead Magnet", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:LeadMagnetPremiumPreview, badge:"PRO", payload:{type:"lead_magnet_premium",theme:"auto",eyebrow:"FREE RESOURCE",heading:"Give visitors something useful before asking for the next step.",text:"Offer a real guide, checklist, template, report, or resource.",primary_label:"Get the resource",primary_url:"/resources"} },
    { type:"lead_free_audit_premium", theme:"auto", title:"Free Audit", buttonLabel:"Add Free Audit", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:FreeAuditPremiumPreview, badge:"PRO", payload:{type:"lead_free_audit_premium",theme:"auto",eyebrow:"AUDIT REQUEST",heading:"Start with a focused review of what matters most.",text:"Only publish this as free when the business genuinely offers a free audit.",primary_label:"Request audit",primary_url:"/contact"} },
    { type:"lead_website_audit_premium", theme:"auto", title:"Website Audit", buttonLabel:"Add Website Audit", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:WebsiteAuditPremiumPreview, badge:"PRO", payload:{type:"lead_website_audit_premium",theme:"auto",eyebrow:"WEBSITE AUDIT",heading:"Find the friction before you rebuild the whole thing.",text:"Promote a real website review without fabricated scores.",primary_label:"Request website audit",primary_url:"/contact"} },
    { type:"lead_quote_form_premium", theme:"auto", title:"Quote Form", buttonLabel:"Add Quote Form", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:QuoteFormPremiumPreview, badge:"PRO", payload:{type:"lead_quote_form_premium",theme:"auto",eyebrow:"REQUEST A QUOTE",heading:"Tell us what you need. We’ll shape the next step.",text:"Collect the details needed for a real quote.",primary_label:"Request quote",primary_url:"/contact"} },
    { type:"lead_roi_calculator_premium", theme:"auto", title:"ROI Calculator", buttonLabel:"Add ROI Calculator", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:RoiCalculatorPremiumPreview, badge:"PRO", payload:{type:"lead_roi_calculator_premium",theme:"auto",eyebrow:"ROI CALCULATOR",heading:"Model the upside with your own assumptions.",text:"Create an illustrative estimate using visitor-entered values."} },
    { type:"lead_cost_calculator_premium", theme:"auto", title:"Cost Calculator", buttonLabel:"Add Cost Calculator", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:CostCalculatorPremiumPreview, badge:"PRO", payload:{type:"lead_cost_calculator_premium",theme:"auto",eyebrow:"COST ESTIMATOR",heading:"Build a quick working estimate.",text:"Let visitors enter quantity and a unit rate for a rough estimate."} },
    { type:"lead_consultation_booking_premium", theme:"auto", title:"Consultation Booking", buttonLabel:"Add Consultation Booking", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:ConsultationBookingPremiumPreview, badge:"PRO", payload:{type:"lead_consultation_booking_premium",theme:"auto",eyebrow:"BOOK A CONSULTATION",heading:"Start with a focused conversation.",text:"Collect the details needed to request a consultation.",primary_label:"Request consultation",primary_url:"/contact"} },
    { type:"sales_comparison_premium", theme:"auto", title:"Sales Comparison Table", buttonLabel:"Add Sales Comparison", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:SalesComparisonPremiumPreview, badge:"PRO", payload:{type:"sales_comparison_premium",theme:"auto",eyebrow:"COMPARE OPTIONS",heading:"Make the decision easier to understand.",text:"Compare real options using supplied features and terms."} },
    { type:"sales_feature_matrix_premium", theme:"auto", title:"Sales Feature Matrix", buttonLabel:"Add Sales Feature Matrix", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:SalesFeatureMatrixPremiumPreview, badge:"PRO", payload:{type:"sales_feature_matrix_premium",theme:"auto",eyebrow:"FEATURE MATRIX",heading:"See what matters at a glance.",text:"Organise supplied capabilities into a clear matrix."} },
    { type:"sales_competitor_comparison_premium", theme:"auto", title:"Competitor Comparison", buttonLabel:"Add Competitor Comparison", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:SalesCompetitorComparisonPremiumPreview, badge:"PRO", payload:{type:"sales_competitor_comparison_premium",theme:"auto",eyebrow:"WHY CHOOSE US",heading:"Compare on facts, not noise.",text:"Use only verifiable competitor and product information."} },
    { type:"sales_roi_premium", theme:"auto", title:"Sales ROI", buttonLabel:"Add Sales ROI", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:SalesRoiPremiumPreview, badge:"PRO", payload:{type:"sales_roi_premium",theme:"auto",eyebrow:"BUSINESS CASE",heading:"Frame the value with transparent assumptions.",text:"Use supplied inputs to explain potential value without guarantees."} },
    { type:"sales_guarantee_premium", theme:"auto", title:"Guarantee", buttonLabel:"Add Guarantee", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:SalesGuaranteePremiumPreview, badge:"PRO", payload:{type:"sales_guarantee_premium",theme:"auto",eyebrow:"OUR COMMITMENT",heading:"Make the promise clear — and keep it accurate.",text:"Present a real supplied guarantee, warranty, or assurance without inventing terms."} },
    { type:"sales_trust_premium", theme:"auto", title:"Trust Section", buttonLabel:"Add Trust Section", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:SalesTrustPremiumPreview, badge:"PRO", payload:{type:"sales_trust_premium",theme:"auto",eyebrow:"WHY TRUST US",heading:"Give buyers reasons to feel confident.",text:"Show only real supplied trust signals."} },
    { type:"sales_integrations_premium", theme:"auto", title:"Integrations", buttonLabel:"Add Integrations", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:SalesIntegrationsPremiumPreview, badge:"PRO", payload:{type:"sales_integrations_premium",theme:"auto",eyebrow:"WORKS WITH YOUR STACK",heading:"Connect the tools your customers already use.",text:"Show only real or planned integrations supplied by the business."} },
    { type:"agency_dashboard_preview_premium", theme:"auto", title:"Agency Dashboard Preview", buttonLabel:"Add Agency Dashboard", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:AgencyDashboardPreviewPremiumPreview, badge:"PRO", payload:{type:"agency_dashboard_preview_premium",theme:"auto",eyebrow:"AGENCY OVERVIEW",heading:"One clear view across the work you manage.",text:"Present real agency metrics and workflows without fabricated numbers."} },
    { type:"agency_client_portal_premium", theme:"auto", title:"Client Portal", buttonLabel:"Add Client Portal", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:AgencyClientPortalPremiumPreview, badge:"PRO", payload:{type:"agency_client_portal_premium",theme:"auto",eyebrow:"CLIENT EXPERIENCE",heading:"Give clients a polished place to stay aligned.",text:"Show only client portal capabilities genuinely provided."} },
    { type:"agency_white_label_showcase_premium", theme:"auto", title:"White Label Showcase", buttonLabel:"Add White Label Showcase", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:AgencyWhiteLabelShowcasePremiumPreview, badge:"PRO", payload:{type:"agency_white_label_showcase_premium",theme:"auto",eyebrow:"YOUR BRAND, FRONT AND CENTRE",heading:"Present the experience under the agency brand.",text:"Show only real white-label controls and branding options."} },
    { type:"agency_website_management_premium", theme:"auto", title:"Website Management", buttonLabel:"Add Website Management", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:AgencyWebsiteManagementPremiumPreview, badge:"PRO", payload:{type:"agency_website_management_premium",theme:"auto",eyebrow:"MANAGE AT SCALE",heading:"Keep multiple websites organised without losing the details.",text:"Show real website management workflows and controls."} },
    { type:"agency_maintenance_plans_premium", theme:"auto", title:"Maintenance Plans", buttonLabel:"Add Maintenance Plans", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:AgencyMaintenancePlansPremiumPreview, badge:"PRO", payload:{type:"agency_maintenance_plans_premium",theme:"auto",eyebrow:"ONGOING CARE",heading:"Keep websites healthy after launch.",text:"Present real maintenance scope, cadence, and inclusions."} },
    { type:"agency_support_plans_premium", theme:"auto", title:"Support Plans", buttonLabel:"Add Support Plans", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:AgencySupportPlansPremiumPreview, badge:"PRO", payload:{type:"agency_support_plans_premium",theme:"auto",eyebrow:"SUPPORT THAT FITS",heading:"Make support options easy to understand.",text:"Show only real support channels, coverage, and plan differences."} },
    { type:"agency_workflow_premium", theme:"auto", title:"Agency Workflow", buttonLabel:"Add Agency Workflow", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:AgencyWorkflowPremiumPreview, badge:"PRO", payload:{type:"agency_workflow_premium",theme:"auto",eyebrow:"HOW WE WORK",heading:"Turn a complex project into a clear sequence.",text:"Show the agency's real delivery workflow."} },
    { type:"agency_project_pipeline_premium", theme:"auto", title:"Project Pipeline", buttonLabel:"Add Project Pipeline", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:AgencyProjectPipelinePremiumPreview, badge:"PRO", payload:{type:"agency_project_pipeline_premium",theme:"auto",eyebrow:"PROJECT PIPELINE",heading:"Show how work moves from idea to delivery.",text:"Present real project stages without invented status or deadlines."} },
    { type:"agency_client_reviews_premium", theme:"auto", title:"Client Reviews", buttonLabel:"Add Client Reviews", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:AgencyClientReviewsPremiumPreview, badge:"PRO", payload:{type:"agency_client_reviews_premium",theme:"auto",eyebrow:"CLIENT FEEDBACK",heading:"Let verified client experiences speak for the work.",text:"Present only genuine supplied client feedback or clear editable placeholders."} },
    { type:"agency_website_reports_premium", theme:"auto", title:"Website Reports", buttonLabel:"Add Website Reports", buttonClass:"bg-violet-700 hover:bg-violet-600", preview:AgencyWebsiteReportsPremiumPreview, badge:"PRO", payload:{type:"agency_website_reports_premium",theme:"auto",eyebrow:"CLEAR REPORTING",heading:"Turn website activity into a client-friendly update.",text:"Show only reporting views, metrics, and insights the agency genuinely provides."} },
    { type:"ai_prompt_showcase_premium", theme:"auto", title:"AI Prompt Showcase", buttonLabel:"Add AI Prompt Showcase", buttonClass:"bg-cyan-700 hover:bg-cyan-600", preview:AiPromptShowcasePremiumPreview, badge:"PRO", payload:{type:"ai_prompt_showcase_premium",theme:"auto",eyebrow:"FROM PROMPT TO PAGE",heading:"Show the prompts that shape the experience.",text:"Present safe example prompts or real supplied use cases."} },
    { type:"ai_workflow_premium", theme:"auto", title:"AI Workflow", buttonLabel:"Add AI Workflow", buttonClass:"bg-cyan-700 hover:bg-cyan-600", preview:AiWorkflowPremiumPreview, badge:"PRO", payload:{type:"ai_workflow_premium",theme:"auto",eyebrow:"AI WORKFLOW",heading:"Make the generation process easy to understand.",text:"Explain the real AI-assisted workflow in clear stages."} },
    { type:"ai_assistant_premium", theme:"auto", title:"AI Assistant", buttonLabel:"Add AI Assistant", buttonClass:"bg-cyan-700 hover:bg-cyan-600", preview:AiAssistantPremiumPreview, badge:"PRO", payload:{type:"ai_assistant_premium",theme:"auto",eyebrow:"AI ASSISTANT",heading:"Turn a complex request into a guided conversation.",text:"Show an assistant-style interaction using example copy."} },
    { type:"ai_timeline_premium", theme:"auto", title:"AI Timeline", buttonLabel:"Add AI Timeline", buttonClass:"bg-cyan-700 hover:bg-cyan-600", preview:AiTimelinePremiumPreview, badge:"PRO", payload:{type:"ai_timeline_premium",theme:"auto",eyebrow:"GENERATION JOURNEY",heading:"Show how an idea becomes a finished page.",text:"Explain supported AI generation stages without fake processing times."} },
    { type:"ai_builder_premium", theme:"auto", title:"AI Builder", buttonLabel:"Add AI Builder", buttonClass:"bg-cyan-700 hover:bg-cyan-600", preview:AiBuilderPremiumPreview, badge:"PRO", payload:{type:"ai_builder_premium",theme:"auto",eyebrow:"AI BUILDER",heading:"Show how AI helps shape a page without hiding the controls.",text:"Present the real builder workflow with supported actions only."} },
    { type:"ai_automation_premium", theme:"auto", title:"Automation", buttonLabel:"Add Automation", buttonClass:"bg-cyan-700 hover:bg-cyan-600", preview:AiAutomationPremiumPreview, badge:"PRO", payload:{type:"ai_automation_premium",theme:"auto",eyebrow:"AUTOMATION",heading:"Explain the repetitive work the product can genuinely simplify.",text:"Show only real supported automations or clearly labelled planned workflows."} },
    { type:"ai_credits_dashboard_premium", theme:"auto", title:"Credits Dashboard", buttonLabel:"Add Credits Dashboard", buttonClass:"bg-cyan-700 hover:bg-cyan-600", preview:AiCreditsDashboardPremiumPreview, badge:"PRO", payload:{type:"ai_credits_dashboard_premium",theme:"auto",eyebrow:"AI CREDITS",heading:"Make AI usage easy to understand at a glance.",text:"Show supplied credit balances and real usage rules without fabricated account data."} },
    { type:"ai_generation_process_premium", theme:"auto", title:"Generation Process", buttonLabel:"Add Generation Process", buttonClass:"bg-cyan-700 hover:bg-cyan-600", preview:AiGenerationProcessPremiumPreview, badge:"PRO", payload:{type:"ai_generation_process_premium",theme:"auto",eyebrow:"GENERATION PROCESS",heading:"Turn generation into a clear sequence users can follow.",text:"Explain supported stages without fake progress or completion times."} },
    { type:"ai_statistics_premium", theme:"auto", title:"AI Statistics", buttonLabel:"Add AI Statistics", buttonClass:"bg-cyan-700 hover:bg-cyan-600", preview:AiStatisticsPremiumPreview, badge:"PRO", payload:{type:"ai_statistics_premium",theme:"auto",eyebrow:"AI STATISTICS",heading:"Explain AI usage with numbers you can actually support.",text:"Present supplied usage or efficiency metrics without fabricated performance claims."} },
    { type:"ai_prompt_examples_premium", theme:"auto", title:"Prompt Examples", buttonLabel:"Add Prompt Examples", buttonClass:"bg-cyan-700 hover:bg-cyan-600", preview:AiPromptExamplesPremiumPreview, badge:"PRO", payload:{type:"ai_prompt_examples_premium",theme:"auto",eyebrow:"PROMPT EXAMPLES",heading:"Give visitors practical ways to start with AI.",text:"Show clearly illustrative prompts tied to supported capabilities."} },
    {
        type: "contact_details",
        theme: "auto",
        title: "Contact Details",
        buttonLabel: "Choose contact details",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: ContactDetailsPreview,
        payload: {
            type: "contact_details",
            theme: "auto",
            eyebrow: "GET IN TOUCH",
            heading: "Let’s start a conversation.",
            text: "Reach out when you are ready to discuss your next project or question.",
            email: "hello@example.com",
            phone: "+1 (555) 010-0200",
            address: "Available by appointment",
            hours: "Monday to Friday, 9:00 AM to 5:00 PM",
        },
    },
    {
        type: "location_map",
        theme: "auto",
        title: "Location & Directions",
        buttonLabel: "Choose location layout",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: LocationMapPreview,
        payload: {
            type: "location_map",
            theme: "auto",
            eyebrow: "FIND US",
            heading: "Visit us when it works for you.",
            text: "Plan your visit with clear location details and directions.",
            location_name: "Our studio",
            address: "Available by appointment",
            directions_label: "Get directions",
            directions_url: "https://www.google.com/maps/search/?api=1&query=Your+Business+Location",
        },
    },
    {
        type: "case_studies_grid", theme: "auto", title: "Case Studies Grid", buttonLabel: "Choose case studies layout", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: CaseStudiesGridPreview,
        payload: { type: "case_studies_grid", theme: "auto", eyebrow: "SELECTED WORK", heading: "Results that make the difference.", text: "A closer look at focused work shaped around clear goals and practical outcomes.", studies: [
            { category: "Strategy", title: "A clearer digital path", summary: "A focused engagement that turned a complex challenge into a practical next step.", result: "Built for measurable progress", image_url: "/storage/cms-images/background/background-1.avif", link_label: "View case study" },
            { category: "Design", title: "An experience made simpler", summary: "A thoughtful redesign that made important information easier to find and act on.", result: "Clarity at every step", image_url: "/storage/cms-images/background/background-2.avif", link_label: "View case study" },
            { category: "Growth", title: "A stronger launch foundation", summary: "A collaborative project built around the real customer journey.", result: "Ready to grow", image_url: "/storage/cms-images/background/background-3.avif", link_label: "View case study" },
        ] },
    },
    {
        type: "jobs_list", theme: "auto", title: "Jobs List", buttonLabel: "Choose careers layout", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: JobsListPreview,
        payload: { type: "jobs_list", theme: "auto", eyebrow: "JOIN OUR TEAM", heading: "Do work that moves things forward.", text: "We are looking for thoughtful people who care about good work and shared progress.", jobs: [
            { title: "Senior designer", type: "Full-time", location: "New York, NY", description: "Help shape thoughtful digital experiences for ambitious teams and their customers.", button_label: "View role" },
            { title: "Project manager", type: "Full-time", location: "Remote", description: "Keep client work organized, moving clearly, and grounded in practical next steps.", button_label: "View role" },
            { title: "Growth strategist", type: "Flexible", location: "Hybrid", description: "Turn research and collaboration into clear opportunities for clients.", button_label: "View role" },
        ] },
    },
    {
        type: "events_grid", theme: "auto", title: "Events Grid", buttonLabel: "Choose events layout", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: EventsGridPreview,
        payload: { type: "events_grid", theme: "auto", eyebrow: "UPCOMING EVENTS", heading: "Useful conversations, coming up.", text: "Join practical sessions, thoughtful gatherings, and opportunities to connect with our team.", events: [
            { month: "OCT", day: "12", title: "A practical session for your next move", date: "October 12 - 10:00 AM", location: "Online", description: "Useful ideas you can put into action right away.", button_label: "Reserve a place" },
            { month: "NOV", day: "04", title: "Meet the people behind the work", date: "November 4 - 6:00 PM", location: "Our studio", description: "An informal evening to connect and exchange ideas.", button_label: "Save your seat" },
            { month: "DEC", day: "08", title: "Plan a stronger year ahead", date: "December 8 - 1:00 PM", location: "Online", description: "A guided planning session for teams setting clearer priorities.", button_label: "Join the session" },
        ] },
    },

    { type: "mini_hero_minimal", theme: "auto", title: "Mini Hero Minimal", buttonLabel: "Add Mini Hero", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: HeroCenteredPreview, payload: { type:"mini_hero_minimal", theme:"auto", eyebrow:"Explore more", heading:"A focused page for what matters next", text:"Use a compact hero to introduce this page without taking over the whole screen.", button_label:"Explore", button_url:"/about" } },
    { type: "mini_hero_split", theme: "auto", title: "Mini Hero Split Image", buttonLabel: "Add Split Mini Hero", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: HeroSplitImagePreview, payload: { type:"mini_hero_split", theme:"auto", eyebrow:"Discover the collection", heading:"Designed for everyday essentials", text:"A concise introduction with a strong supporting visual.", button_label:"Explore", button_url:"/about", image_url:"/storage/cms-images/background/background-2.avif", image_alt:"Featured page image" } },
    { type: "mini_hero_promo", theme: "auto", title: "Mini Hero Promo", buttonLabel: "Add Promo Mini Hero", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ImageCtaBannerPreview, payload: { type:"mini_hero_promo", theme:"auto", eyebrow:"Featured now", heading:"Something worth discovering", text:"Highlight a collection, announcement, or important next step without using a full-height hero.", button_label:"Shop now", button_url:"/shop", image_url:"/storage/cms-images/background/background-3.avif", image_alt:"Promotional page image" } },

    { type: "commerce_product_grid", theme: "auto", title: "Product Grid", buttonLabel: "Add Product Grid", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesGridPreview, payload: { type:"commerce_product_grid", theme:"auto", heading:"Featured products", text:"Discover customer favorites and new arrivals.", limit:8, featured_only:false } },
    { type: "commerce_catalog_grid", theme: "auto", title: "Shop Catalog Grid", buttonLabel: "Add Catalog Grid", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesGridPreview, payload: { type:"commerce_catalog_grid", theme:"auto", heading:"Shop the catalog", text:"Browse products, filter collections, and find what fits.", limit:12, show_toolbar:true } },
    { type: "commerce_catalog_editorial", theme: "auto", title: "Shop Catalog Editorial", buttonLabel: "Add Editorial Catalog", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: FeatureLeftPreview, payload: { type:"commerce_catalog_editorial", theme:"auto", heading:"Curated for you", text:"Explore an image-first collection with a more editorial rhythm.", limit:10, show_toolbar:true } },
    { type: "commerce_catalog_compact", theme: "auto", title: "Shop Catalog Compact", buttonLabel: "Add Compact Catalog", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesFeatureComparisonPreview, payload: { type:"commerce_catalog_compact", theme:"auto", heading:"Browse all products", text:"A compact catalog built for quick comparison.", limit:16, show_toolbar:true } },
    { type: "commerce_categories", theme: "auto", title: "Shop Categories", buttonLabel: "Add Categories", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesBentoPreview, payload: { type:"commerce_categories", theme:"auto", heading:"Shop by category", text:"Find the collection that fits what you need." } },
    { type: "commerce_featured_products", theme: "auto", title: "Featured Products", buttonLabel: "Add Featured Products", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesGridPreview, payload: { type:"commerce_featured_products", theme:"auto", heading:"Featured picks", text:"A curated selection worth a closer look.", limit:4 } },
    { type: "commerce_featured_collection", theme: "auto", title: "Featured Collection", buttonLabel: "Add Featured Collection", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesGridPreview, payload: { type:"commerce_featured_collection", theme:"auto", heading:"Featured collection", text:"Explore a focused edit from one collection.", category_id:null, limit:4, button_label:"View collection" } },
    { type: "commerce_promo_split", theme: "auto", title: "Promo Split Banner", buttonLabel: "Add Promo Banner", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: FeatureLeftPreview, payload: { type:"commerce_promo_split", theme:"auto", eyebrow:"Limited collection", heading:"A standout offer for the season", text:"Pair a strong message with a product-led visual and a clear next step.", button_label:"Shop the collection", button_url:"/shop", image_url:"/storage/cms-images/background/background-3.avif", image_alt:"Featured collection" } },
    { type: "commerce_benefits_strip", theme: "auto", title: "Benefits / Trust Strip", buttonLabel: "Add Benefits Strip", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesFeatureComparisonPreview, payload: { type:"commerce_benefits_strip", theme:"auto", heading:"Shop with confidence", benefit_1_title:"Secure checkout", benefit_1_text:"Protected payment flow", benefit_2_title:"Fast delivery", benefit_2_text:"Clear shipping options", benefit_3_title:"Easy returns", benefit_3_text:"Straightforward support", benefit_4_title:"Here to help", benefit_4_text:"Customer care when needed" } },
    { type: "commerce_product_gallery", theme: "auto", title: "Product Gallery", buttonLabel: "Add Product Gallery", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: FeatureLeftPreview, payload: { type:"commerce_product_gallery", theme:"auto", heading:"Product gallery", product_id:null } },
    { type: "commerce_price", theme: "auto", title: "Product Price", buttonLabel: "Add Price", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: PricingCardsPreview, payload: { type:"commerce_price", theme:"auto", product_id:null, label:"Price" } },
    { type: "commerce_variation_selector", theme: "auto", title: "Variation Selector", buttonLabel: "Add Variations", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesFeatureComparisonPreview, payload: { type:"commerce_variation_selector", theme:"auto", product_id:null, heading:"Choose your options" } },
    { type: "commerce_related_products", theme: "auto", title: "Related Products", buttonLabel: "Add Related Products", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesGridPreview, payload: { type:"commerce_related_products", theme:"auto", product_id:null, heading:"You may also like", limit:4 } },
    { type: "commerce_mini_cart", theme: "auto", title: "Mini Cart Shell", buttonLabel: "Add Mini Cart", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: PricingCardsPreview, payload: { type:"commerce_mini_cart", theme:"auto", heading:"Your cart", empty_text:"Your cart is ready for products.", button_label:"View cart" } },
    { type: "commerce_cart_classic", theme: "auto", title: "Cart Classic", buttonLabel: "Add Cart Classic", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: PricingCardsPreview, payload: { type:"commerce_cart_classic", theme:"auto", heading:"Your cart", text:"Review your items before checkout.", checkout_label:"Proceed to checkout", continue_label:"Continue shopping" } },
    { type: "commerce_cart_split", theme: "auto", title: "Cart Split Summary", buttonLabel: "Add Split Cart", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesFeatureComparisonPreview, payload: { type:"commerce_cart_split", theme:"auto", heading:"Review your bag", text:"Everything looks good? Continue securely to checkout.", checkout_label:"Secure checkout", continue_label:"Keep shopping" } },
    { type: "commerce_cart_compact", theme: "auto", title: "Cart Compact", buttonLabel: "Add Compact Cart", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesGridPreview, payload: { type:"commerce_cart_compact", theme:"auto", heading:"Cart summary", text:"Quickly review quantities and totals.", checkout_label:"Checkout", continue_label:"Back to shop" } },
    { type: "commerce_checkout_classic", theme: "auto", title: "Checkout Classic", buttonLabel: "Add Checkout Classic", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: PricingCardsPreview, payload: { type:"commerce_checkout_classic", theme:"auto", heading:"Checkout", text:"Complete your details and review the order securely.", payment_label:"Continue to payment", help_text:"Secure checkout powered by the commerce runtime." } },
    { type: "commerce_checkout_split", theme: "auto", title: "Checkout Split", buttonLabel: "Add Split Checkout", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ServicesFeatureComparisonPreview, payload: { type:"commerce_checkout_split", theme:"auto", heading:"Secure checkout", text:"Delivery details on the left, live order summary on the right.", payment_label:"Pay securely", help_text:"Shipping, tax, coupons and payment stay runtime-controlled." } },
    { type: "commerce_checkout_express", theme: "auto", title: "Checkout Express", buttonLabel: "Add Express Checkout", buttonClass: "bg-violet-600 hover:bg-violet-500", preview: ContactFormPreview, payload: { type:"commerce_checkout_express", theme:"auto", heading:"Express checkout", text:"A streamlined path from customer details to payment.", payment_label:"Complete purchase", help_text:"Fast, focused and secure." } },
    { type:"content_grid_classic", theme:"auto", title:"Content Loop · Grid", buttonLabel:"Add Content Loop", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: CaseStudiesGridPreview, payload:{type:"content_grid_classic",theme:"auto",eyebrow:"LATEST STORIES",heading:"Fresh from our updates",text:"Explore the latest articles, events, projects, and updates.",content_type_slug:"blog",category:"",tag:"",sort:"newest",limit:6}},
    { type:"content_grid_editorial", theme:"auto", title:"Content Loop · Editorial", buttonLabel:"Add Editorial Loop", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: FeatureLeftPreview, payload:{type:"content_grid_editorial",theme:"auto",eyebrow:"EDITORIAL",heading:"Stories worth exploring",text:"A more visual way to present your latest content.",content_type_slug:"blog",category:"",tag:"",sort:"newest",limit:5}},
    { type:"content_grid_compact", theme:"auto", title:"Content Loop · List", buttonLabel:"Add Content List", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: CaseStudiesGridPreview, payload:{type:"content_grid_compact",theme:"auto",eyebrow:"UPDATES",heading:"Latest updates",text:"A compact archive for quick scanning.",content_type_slug:"blog",category:"",tag:"",sort:"newest",limit:8}},
    { type:"content_featured_entry", theme:"auto", title:"Featured Post", buttonLabel:"Add Featured Post", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: FeatureLeftPreview, payload:{type:"content_featured_entry",theme:"auto",eyebrow:"FEATURED",heading:"Featured story",text:"Highlight one important entry.",content_type_slug:"blog",featured_only:true,sort:"newest",limit:1}},
    { type:"content_latest_entries", theme:"auto", title:"Latest Posts", buttonLabel:"Add Latest Posts", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: CaseStudiesGridPreview, payload:{type:"content_latest_entries",theme:"auto",eyebrow:"LATEST",heading:"Latest posts",text:"Keep your newest content close at hand.",content_type_slug:"blog",sort:"newest",limit:4}},
    { type:"content_events_grid", theme:"auto", title:"Upcoming Events", buttonLabel:"Add Events Grid", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: EventsGridPreview, payload:{type:"content_events_grid",theme:"auto",eyebrow:"UPCOMING EVENTS",heading:"What’s coming up",text:"Upcoming events, workshops, and gatherings.",content_type_slug:"events",sort:"event_date",upcoming_only:true,limit:6}},

    // Blog base Sparks are valid Builder/export blocks and are also present in the
    // backend Spark catalog. Keep them registered here so Marketplace counts and
    // the Add Spark picker do not silently filter them out.
    { type:"blog_mini_hero", theme:"auto", title:"Blog Mini Hero", buttonLabel:"Add Blog Hero", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: HeroCenteredPreview, payload:{type:"blog_mini_hero",theme:"primary",layout_variant:"mini-header-01"}},
    { type:"blog_hub", theme:"auto", title:"Blog Hub", buttonLabel:"Add Blog Hub", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: CaseStudiesGridPreview, payload:{type:"blog_hub",theme:"editorial",show_intro:false,layout_variant:"blog-cards-01"}},
    { type:"newsletter_cta", theme:"auto", title:"Newsletter CTA", buttonLabel:"Add Newsletter", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: CtaNewsletterPremiumPreview, payload:{type:"newsletter_cta",theme:"primary",layout_variant:"newsletter-01"}},
    { type:"latest_resources", theme:"auto", title:"Latest Resources", buttonLabel:"Add Resources", buttonClass:"bg-violet-600 hover:bg-violet-500", preview: CaseStudiesGridPreview, payload:{type:"latest_resources",theme:"white",layout_variant:"resources-01"}},

    // Premium animated Hero expansion · 187–193. Lightweight previews; full motion starts only after insertion.
    { type:"hero_ken_burns_premium", theme:"auto", title:"Ken Burns Cinematic Hero", buttonLabel:"Add Ken Burns Hero", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroLuxuryFullscreenPreview, badge:"PRO", aliases:["ken burns","cinematic hero","slow zoom","image pan","luxury hero"], payload:{type:"hero_ken_burns_premium",theme:"auto",eyebrow:"CINEMATIC BY DESIGN",heading:"Give every first impression room to breathe.",text:"Slow, deliberate image motion creates depth without distracting from the message.",primary_label:"Start a project",primary_url:"/contact",secondary_label:"Explore more",secondary_url:"/about",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif",overlayOpacity:58}},
    { type:"hero_crossfade_gallery_premium", theme:"auto", title:"Crossfade Gallery Hero", buttonLabel:"Add Crossfade Hero", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroSliderFadePreview, badge:"PRO", aliases:["crossfade","gallery hero","dissolve slider","photography hero","image slideshow"], payload:{type:"hero_crossfade_gallery_premium",theme:"auto",eyebrow:"THREE MOMENTS, ONE STORY",heading:"Let the imagery change the atmosphere.",text:"Soft crossfades cycle through a curated image set while your message stays focused.",primary_label:"Explore the work",primary_url:"/portfolio",secondary_label:"Start a project",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif",interval:4600,overlayOpacity:55}},
    { type:"hero_cinematic_slider_premium", theme:"auto", title:"Cinematic Slider Hero", buttonLabel:"Add Cinematic Slider", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroSliderFadePreview, badge:"PRO", aliases:["cinematic slider","fullscreen slider","hero progress","launch slider","premium slides"], payload:{type:"hero_cinematic_slider_premium",theme:"auto",eyebrow:"A STORY IN MOTION",heading:"Move through the moments that define the brand.",text:"A fullscreen image sequence with calm transitions and clear visual progress.",primary_label:"Begin the story",primary_url:"/about",secondary_label:"Work with us",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif",interval:5600,overlayOpacity:60}},
    { type:"hero_split_slider_premium", theme:"auto", title:"Split Slider Hero", buttonLabel:"Add Split Slider", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroSplitImagePreview, badge:"PRO", aliases:["split slider","split hero","image slider","saas hero","agency split"], payload:{type:"hero_split_slider_premium",theme:"auto",eyebrow:"FOCUS THE MESSAGE",heading:"Keep the story steady while the proof keeps moving.",text:"Conversion copy stays anchored beside a rotating visual showcase.",primary_label:"See how it works",primary_url:"/services",secondary_label:"Talk to us",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif",interval:4800,overlayOpacity:34}},
    { type:"hero_vertical_story_premium", theme:"auto", title:"Vertical Story Hero", buttonLabel:"Add Vertical Story", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroEditorialOverlayPreview, badge:"PRO", aliases:["vertical slider","story hero","editorial hero","vertical transition","portfolio story"], payload:{type:"hero_vertical_story_premium",theme:"auto",eyebrow:"SCROLLING ENERGY",heading:"A more editorial way to open the story.",text:"Visual panels move vertically to create a distinctive sense of progression.",primary_label:"Discover more",primary_url:"/about",secondary_label:"View projects",secondary_url:"/portfolio",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif",interval:5000,overlayOpacity:58}},
    { type:"hero_parallax_layers_premium", theme:"auto", title:"Parallax Layers Hero", buttonLabel:"Add Layered Parallax", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroParallaxPreview, badge:"PRO", aliases:["parallax layers","scroll depth","layered hero","immersive scroll","multi layer parallax"], payload:{type:"hero_parallax_layers_premium",theme:"auto",eyebrow:"DEPTH IN EVERY LAYER",heading:"Turn a flat hero into an immersive scene.",text:"Multiple visual planes move at different speeds as the page scrolls.",primary_label:"Explore the experience",primary_url:"/about",secondary_label:"Start a project",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif",parallaxStrength:22,overlayOpacity:60}},
    { type:"hero_mouse_parallax_premium", theme:"auto", title:"Mouse Parallax Hero", buttonLabel:"Add Mouse Parallax", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroFloatingGlassPreview, badge:"PRO", aliases:["mouse parallax","cursor hero","interactive depth","pointer parallax","3d hero"], payload:{type:"hero_mouse_parallax_premium",theme:"auto",eyebrow:"INTERACTIVE DEPTH",heading:"A hero that responds without shouting for attention.",text:"Layered imagery follows pointer movement with restrained, premium depth.",primary_label:"See the experience",primary_url:"/services",secondary_label:"Contact us",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif",pointerStrength:16,overlayOpacity:60}},

    // Premium animated Hero expansion · 194–200. Motion stays lightweight in Marketplace previews.
    { type:"hero_reveal_parallax_premium", theme:"auto", title:"Reveal Parallax Hero", buttonLabel:"Add Reveal Parallax", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroLuxuryFullscreenPreview, badge:"PRO", aliases:["reveal parallax","scroll reveal","image reveal","cinematic reveal","luxury hero"], payload:{type:"hero_reveal_parallax_premium",theme:"auto",eyebrow:"REVEALED IN MOTION",heading:"Let the image arrive with the story.",text:"A restrained scroll reveal adds cinematic depth while the message stays anchored and readable.",primary_label:"Explore the story",primary_url:"/about",secondary_label:"Start a project",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",motionStrength:28,overlayOpacity:54}},
    { type:"hero_zoom_scroll_premium", theme:"auto", title:"Zoom Scroll Hero", buttonLabel:"Add Zoom Scroll", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroLuxuryFullscreenPreview, badge:"PRO", aliases:["zoom scroll","scroll zoom","immersive hero","cinematic zoom","editorial hero"], payload:{type:"hero_zoom_scroll_premium",theme:"auto",eyebrow:"DRAW THEM CLOSER",heading:"A subtle zoom that builds momentum as you scroll.",text:"Designed for editorial, hospitality, property, and brand-led experiences that benefit from immersive motion.",primary_label:"Discover more",primary_url:"/about",secondary_label:"Get in touch",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-2.avif",motionStrength:24,overlayOpacity:56}},
    { type:"hero_pinned_story_premium", theme:"auto", title:"Pinned Story Hero", buttonLabel:"Add Pinned Story", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroSliderFadePreview, badge:"PRO", aliases:["pinned story","sticky hero","scroll story","scrollytelling","apple style hero"], payload:{type:"hero_pinned_story_premium",theme:"auto",eyebrow:"A STORY THAT UNFOLDS",heading:"Keep the message in place while the world changes around it.",text:"Three visual chapters progress through the opening as visitors scroll into your story.",primary_label:"Explore the journey",primary_url:"/about",secondary_label:"Start a project",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif",overlayOpacity:58}},
    { type:"hero_video_cinematic_premium", theme:"auto", title:"Video Cinematic Hero", buttonLabel:"Add Cinematic Video", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroVideoPremiumPreview, badge:"PRO", aliases:["video hero","cinematic video","background video","brand film","fullscreen video"], payload:{type:"hero_video_cinematic_premium",theme:"auto",eyebrow:"A STORY IN MOTION",heading:"Use movement to make the first seconds memorable.",text:"A full-screen muted film backdrop gives launches, hospitality, agencies, and premium brands immediate atmosphere.",primary_label:"Start the experience",primary_url:"/contact",secondary_label:"Our story",secondary_url:"/about",video_url:"/storage/cms-videos/hero-placeholder.mp4",poster_image_url:"/storage/cms-images/background/background-1.avif",overlayOpacity:58}},
    { type:"hero_video_split_premium", theme:"auto", title:"Video Split Hero", buttonLabel:"Add Split Video", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroVideoPremiumPreview, badge:"PRO", aliases:["split video","video split hero","product video","showreel hero","agency video"], payload:{type:"hero_video_split_premium",theme:"auto",eyebrow:"SHOW, THEN TELL",heading:"Keep the pitch focused while the product moves beside it.",text:"A conversion-first split layout for software demos, showreels, product launches, and service stories.",primary_label:"Book a demo",primary_url:"/contact",secondary_label:"See the work",secondary_url:"/portfolio",video_url:"/storage/cms-videos/hero-placeholder.mp4",poster_image_url:"/storage/cms-images/background/background-2.avif",overlayOpacity:40}},
    { type:"hero_aurora_motion_premium", theme:"auto", title:"Aurora Motion Hero", buttonLabel:"Add Aurora Hero", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroAiConversationPreview, badge:"PRO", aliases:["aurora hero","gradient motion","glow hero","ai hero","saas gradient"], payload:{type:"hero_aurora_motion_premium",theme:"auto",eyebrow:"INTELLIGENCE, ILLUMINATED",heading:"A luminous opening for products built around possibility.",text:"Slow aurora light creates premium depth without loading large visual media.",primary_label:"Start building",primary_url:"/start",secondary_label:"Explore features",secondary_url:"/features",overlayOpacity:32}},
    { type:"hero_mesh_gradient_motion_premium", theme:"auto", title:"Mesh Gradient Motion Hero", buttonLabel:"Add Mesh Gradient", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroSaasDashboardPreview, badge:"PRO", aliases:["mesh gradient","animated gradient","startup hero","modern product hero","launch gradient"], payload:{type:"hero_mesh_gradient_motion_premium",theme:"auto",eyebrow:"BUILT FOR WHAT'S NEXT",heading:"Modern motion without the weight of a background video.",text:"Layered mesh gradients create a polished launch canvas for SaaS, AI, fintech, and digital products.",primary_label:"Launch your idea",primary_url:"/start",secondary_label:"See how it works",secondary_url:"/features",overlayOpacity:24}},

    // Premium animated Hero expansion · 201–207. Marketplace previews stay static/lightweight.
    { type:"hero_spotlight_cursor_premium", theme:"auto", title:"Spotlight Cursor Hero", buttonLabel:"Add Spotlight Hero", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroAiConversationPreview, badge:"PRO", aliases:["spotlight cursor","mouse glow","interactive hero","cursor glow","saas hero"], payload:{type:"hero_spotlight_cursor_premium",theme:"auto",eyebrow:"FOLLOW THE SIGNAL",heading:"A focused opening that responds to attention.",text:"A soft cursor spotlight creates depth for SaaS, AI, design, and premium digital brands.",primary_label:"Start building",primary_url:"/start",secondary_label:"Explore more",secondary_url:"/features",pointerStrength:16,overlayOpacity:28}},
    { type:"hero_floating_cards_motion_premium", theme:"auto", title:"Floating Cards Motion Hero", buttonLabel:"Add Floating Cards", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroFloatingCardsPreview, badge:"PRO", aliases:["floating cards","floating ui","dashboard cards","saas motion","product cards"], payload:{type:"hero_floating_cards_motion_premium",theme:"auto",eyebrow:"PROOF IN MOTION",heading:"Let the product story float into view.",text:"Layered cards add visual proof without turning the opening into a heavy animation scene.",primary_label:"See the product",primary_url:"/features",secondary_label:"Book a demo",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif",overlayOpacity:56}},
    { type:"hero_3d_tilt_product_premium", theme:"auto", title:"3D Tilt Product Hero", buttonLabel:"Add 3D Product Tilt", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroSaasDashboardPreview, badge:"PRO", aliases:["3d tilt","product tilt","dashboard tilt","cursor perspective","interactive product"], payload:{type:"hero_3d_tilt_product_premium",theme:"auto",eyebrow:"PRODUCT, WITH DEPTH",heading:"Put the interface at the center of the pitch.",text:"A restrained perspective tilt makes dashboards, apps, and digital products feel tangible.",primary_label:"Try the product",primary_url:"/start",secondary_label:"Book a demo",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",pointerStrength:12,overlayOpacity:32}},
    { type:"hero_infinite_marquee_premium", theme:"auto", title:"Infinite Marquee Hero", buttonLabel:"Add Marquee Hero", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroEditorialOverlayPreview, badge:"PRO", aliases:["marquee hero","moving typography","infinite text","agency hero","editorial motion"], payload:{type:"hero_infinite_marquee_premium",theme:"auto",eyebrow:"MAKE THE MESSAGE MOVE",heading:"Big editorial energy, anchored by a clear next step.",text:"Oversized moving typography gives agencies, studios, events, and campaigns a distinctive first screen.",primary_label:"View the work",primary_url:"/portfolio",secondary_label:"Start a project",secondary_url:"/contact",overlayOpacity:24}},
    { type:"hero_rotating_words_premium", theme:"auto", title:"Rotating Words Hero", buttonLabel:"Add Rotating Words", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroCenteredPreview, badge:"PRO", aliases:["rotating words","headline rotation","changing words","saas headline","dynamic headline"], payload:{type:"hero_rotating_words_premium",theme:"auto",eyebrow:"ONE PLATFORM, MANY OUTCOMES",heading:"Build your next website",text:"Rotate the final idea in the headline to communicate several outcomes without adding visual clutter.",primary_label:"Start building",primary_url:"/start",secondary_label:"See templates",secondary_url:"/templates",rotating_words:["faster","smarter","beautifully"],interval:2600,overlayOpacity:22}},
    { type:"hero_typewriter_premium", theme:"auto", title:"Typewriter Premium Hero", buttonLabel:"Add Typewriter Hero", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroAiConversationPreview, badge:"PRO", aliases:["typewriter hero","typing headline","developer hero","ai hero","dynamic text"], payload:{type:"hero_typewriter_premium",theme:"auto",eyebrow:"READY WHEN YOU ARE",heading:"Create something",text:"A polished typing effect for AI products, developer tools, creative platforms, and launches.",primary_label:"Start now",primary_url:"/start",secondary_label:"Explore features",secondary_url:"/features",rotating_words:["remarkable","useful","beautiful"],interval:2200,overlayOpacity:20}},
    { type:"hero_curtain_reveal_premium", theme:"auto", title:"Curtain Reveal Hero", buttonLabel:"Add Curtain Reveal", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroLuxuryFullscreenPreview, badge:"PRO", aliases:["curtain reveal","panel reveal","cinematic intro","luxury hero","launch reveal"], payload:{type:"hero_curtain_reveal_premium",theme:"auto",eyebrow:"THE REVEAL",heading:"Open with a cinematic first beat.",text:"Two restrained panels reveal the image beneath for luxury, property, hospitality, automotive, and campaign launches.",primary_label:"Discover more",primary_url:"/about",secondary_label:"Contact us",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-2.avif",overlayOpacity:54}},

    // Premium animated Hero expansion · 208–214. Marketplace previews stay static/lightweight.
    { type:"hero_image_mask_reveal_premium", theme:"auto", title:"Image Mask Reveal Hero", buttonLabel:"Add Mask Reveal", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroEditorialOverlayPreview, badge:"PRO", aliases:["image mask reveal","masked hero","image reveal","editorial motion","fashion hero"], payload:{type:"hero_image_mask_reveal_premium",theme:"auto",eyebrow:"REVEAL THE STORY",heading:"Let the image arrive with intention.",text:"A cinematic mask opens into a full visual canvas for fashion, hospitality, architecture, and editorial brands.",primary_label:"Discover more",primary_url:"/about",secondary_label:"View the work",secondary_url:"/portfolio",image_url:"/storage/cms-images/background/background-1.avif",overlayOpacity:50}},
    { type:"hero_stacked_cards_premium", theme:"auto", title:"Stacked Cards Hero", buttonLabel:"Add Stacked Cards", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroFloatingCardsPreview, badge:"PRO", aliases:["stacked cards","card stack","layered cards","portfolio hero","product cards"], payload:{type:"hero_stacked_cards_premium",theme:"auto",eyebrow:"LAYERED PROOF",heading:"Tell the story one frame at a time.",text:"A dimensional stack cycles through work, products, or services without loading a heavy carousel library.",primary_label:"See the work",primary_url:"/portfolio",secondary_label:"Start a project",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif",overlayOpacity:30}},
    { type:"hero_perspective_carousel_premium", theme:"auto", title:"Perspective Carousel Hero", buttonLabel:"Add Perspective Carousel", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroSliderFadePreview, badge:"PRO", aliases:["perspective carousel","3d carousel","image carousel","visual showcase","portfolio slider"], payload:{type:"hero_perspective_carousel_premium",theme:"auto",eyebrow:"A NEW ANGLE",heading:"Make every showcase feel dimensional.",text:"Three visual cards rotate through a restrained perspective stage for portfolios, products, property, and campaigns.",primary_label:"Explore projects",primary_url:"/portfolio",secondary_label:"Contact us",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif",overlayOpacity:28}},
    { type:"hero_before_after_premium", theme:"auto", title:"Before / After Hero", buttonLabel:"Add Before / After", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroAgencyShowcasePreview, badge:"PRO", aliases:["before after","comparison slider","image comparison","renovation hero","beauty transformation"], payload:{type:"hero_before_after_premium",theme:"auto",eyebrow:"SEE THE TRANSFORMATION",heading:"Show the difference instead of describing it.",text:"A draggable comparison is ideal for renovation, landscaping, beauty, restoration, detailing, fitness, and design work.",primary_label:"View results",primary_url:"/portfolio",secondary_label:"Get a quote",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",splitPosition:52,overlayOpacity:22}},
    { type:"hero_scroll_morph_premium", theme:"auto", title:"Scroll Morph Hero", buttonLabel:"Add Scroll Morph", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroLuxuryFullscreenPreview, badge:"PRO", aliases:["scroll morph","morphing hero","scroll animation","cinematic frame","interactive editorial"], payload:{type:"hero_scroll_morph_premium",theme:"auto",eyebrow:"FORM FOLLOWS MOTION",heading:"Let the visual expand as the story begins.",text:"The artwork grows from a framed object into a more cinematic presence as visitors move into the page.",primary_label:"Explore the story",primary_url:"/about",secondary_label:"See projects",secondary_url:"/portfolio",image_url:"/storage/cms-images/background/background-2.avif",motionStrength:28,overlayOpacity:32}},
    { type:"hero_glass_orb_premium", theme:"auto", title:"Glass Orb Hero", buttonLabel:"Add Glass Orb", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroFloatingGlassPreview, badge:"PRO", aliases:["glass orb","glassmorphism hero","floating orb","luxury tech hero","gradient glass"], payload:{type:"hero_glass_orb_premium",theme:"auto",eyebrow:"DEPTH WITHOUT WEIGHT",heading:"A luminous opening built from light and glass.",text:"Floating translucent forms create premium motion for AI, fintech, beauty, luxury technology, and modern service brands.",primary_label:"Start building",primary_url:"/start",secondary_label:"Explore features",secondary_url:"/features",overlayOpacity:22}},
    { type:"hero_particle_constellation_premium", theme:"auto", title:"Particle Constellation Hero", buttonLabel:"Add Constellation", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroAiConversationPreview, badge:"PRO", aliases:["particle constellation","particles hero","network hero","ai particles","connected dots"], payload:{type:"hero_particle_constellation_premium",theme:"auto",eyebrow:"CONNECTED BY DESIGN",heading:"Turn complexity into a clear first impression.",text:"A lightweight constellation canvas gives AI, science, cybersecurity, data, and technology brands subtle network motion.",primary_label:"Explore the platform",primary_url:"/features",secondary_label:"Book a demo",secondary_url:"/contact",particleCount:36,overlayOpacity:18}},

    // Premium animated Hero expansion · 215–220. Static marketplace previews keep browsing lightweight.
    { type:"hero_grid_pulse_tech_premium", theme:"auto", title:"Grid Pulse Tech Hero", buttonLabel:"Add Grid Pulse", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroAiConversationPreview, badge:"PRO", aliases:["grid pulse","tech grid","ai hero","cybersecurity","developer"], payload:{type:"hero_grid_pulse_tech_premium",theme:"auto",eyebrow:"SIGNAL THROUGH THE NOISE",heading:"A technical first screen with a pulse of motion.",text:"A restrained moving grid gives AI, cyber, data and developer brands a precise visual rhythm.",primary_label:"Explore platform",primary_url:"/features",secondary_label:"Book a demo",secondary_url:"/contact"}},
    { type:"hero_light_trails_premium", theme:"auto", title:"Beam / Light Trails Hero", buttonLabel:"Add Light Trails", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroLuxuryFullscreenPreview, badge:"PRO", aliases:["light trails","beam hero","glow lines","technology","launch"], payload:{type:"hero_light_trails_premium",theme:"auto",eyebrow:"MOVE WITH LIGHT",heading:"A luminous opening for the next big launch.",text:"Slow directional beams add energy without overwhelming premium technology and campaign pages.",primary_label:"Discover more",primary_url:"/about",secondary_label:"Get started",secondary_url:"/start"}},
    { type:"hero_device_showcase_premium", theme:"auto", title:"Device Showcase Hero", buttonLabel:"Add Device Showcase", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroSaasDashboardPreview, badge:"PRO", aliases:["device showcase","phone laptop","app hero","saas product","device mockup"], payload:{type:"hero_device_showcase_premium",theme:"auto",eyebrow:"BUILT FOR EVERY SCREEN",heading:"Put the product experience front and center.",text:"Floating laptop and phone frames make SaaS, apps and digital products feel tangible.",primary_label:"Try the product",primary_url:"/start",secondary_label:"See features",secondary_url:"/features",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif"}},
    { type:"hero_app_screens_carousel_premium", theme:"auto", title:"App Screens Carousel Hero", buttonLabel:"Add App Screens", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroSliderFadePreview, badge:"PRO", aliases:["app screens","screens carousel","mobile app","product carousel","ui showcase"], payload:{type:"hero_app_screens_carousel_premium",theme:"auto",eyebrow:"SEE THE FLOW",heading:"Show the app through its strongest screens.",text:"A lightweight rotating screen stack gives product-led landing pages instant visual context.",primary_label:"Start now",primary_url:"/start",secondary_label:"View features",secondary_url:"/features",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif"}},
    { type:"hero_editorial_image_sequence_premium", theme:"auto", title:"Editorial Image Sequence Hero", buttonLabel:"Add Editorial Sequence", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroEditorialOverlayPreview, badge:"PRO", aliases:["editorial images","image sequence","fashion hero","hotel hero","creative"], payload:{type:"hero_editorial_image_sequence_premium",theme:"auto",eyebrow:"A STORY IN FRAMES",heading:"Lead with imagery that feels art-directed.",text:"Layered editorial photography brings fashion, hospitality, architecture and creative brands to life.",primary_label:"Explore the story",primary_url:"/about",secondary_label:"View gallery",secondary_url:"/portfolio",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif"}},
    { type:"hero_interactive_bento_premium", theme:"auto", title:"Interactive Bento Hero", buttonLabel:"Add Interactive Bento", buttonClass:"bg-violet-600 hover:bg-violet-500", preview:HeroBentoPremiumPreview, badge:"PRO", aliases:["interactive bento","bento hero","hover tiles","agency hero","product grid"], payload:{type:"hero_interactive_bento_premium",theme:"auto",eyebrow:"EXPLORE AT A GLANCE",heading:"Turn the first screen into an interactive showcase.",text:"Responsive bento tiles create a premium opening for agencies, products and visual service brands.",primary_label:"Explore work",primary_url:"/portfolio",secondary_label:"Start a project",secondary_url:"/contact",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif"}},

];
