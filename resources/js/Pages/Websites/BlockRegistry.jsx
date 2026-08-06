import {
    HeroHeadlineBlock,
    HeroHeadlineSchema
} from "./Blocks/Hero/HeroHeadlineBlock";

import {
    FeatureImageLeftBlock,
    FeatureImageLeftSchema
} from "./Blocks/Features/FeatureImageLeftBlock";

import {
    FeatureImageRightBlock,
    FeatureImageRightSchema
} from "./Blocks/Features/FeatureImageRightBlock";

import {
    ServicesCardsBlock,
    ServicesCardsSchema
} from "./Blocks/Services/ServicesCardsBlock";

import {
    HeroCenteredCTA,
    HeroCenteredCTASchema
} from "./Blocks/Hero/HeroCenteredCTA";

import {
    ServicesBentoBlock,
    ServicesBentoSchema
} from "./Blocks/Services/ServicesBentoBlock";

import {
    ProcessTimelineBlock,
    ProcessTimelineSchema
} from "./Blocks/Stats/ProcessTimelineBlock";

import {
    StatsModernBlock,
    StatsModernSchema
} from "./Blocks/Stats/StatsModernBlock";

import {
    TeamModernBlock,
    TeamModernSchema
} from "./Blocks/Team/TeamModernBlock";


import {
    TestimonialsCarouselBlock,
    TestimonialsCarouselSchema
} from "./Blocks/Testimonials/TestimonialsCarouselBlock";


import {
    PricingCardsBlock,
    PricingCardsSchema
} from "./Blocks/Pricing/PricingCardsBlock";

import {
    HeroBackgroundImageBlock,
    HeroBackgroundImageSchema
} from "./Blocks/Hero/HeroBackgroundImageBlock";
import HeroSliderFadeBlock, {
    HeroSliderFadeSchema
} from "./Blocks/Hero/HeroSliderFadeBlock";

import {
    HeroParallaxBlock,
    HeroParallaxSchema
} from "./Blocks/Hero/HeroParallaxBlock";

import {
    HeroEditorialOverlayBlock,
    HeroEditorialOverlaySchema
} from "./Blocks/Hero/HeroEditorialOverlayBlock";

import {
    HeroSplitImageBlock,
    HeroSplitImageSchema
} from "./Blocks/Hero/HeroSplitImageBlock";

import { HeroLuxuryFullscreenBlock, HeroLuxuryFullscreenSchema } from "./Blocks/Hero/HeroLuxuryFullscreenBlock";
import { HeroVideoPremiumBlock, HeroVideoPremiumSchema } from "./Blocks/Hero/HeroVideoPremiumBlock";

import {
    HeroSplitEditorialBlock,
    HeroSplitEditorialSchema
} from "./Blocks/Hero/HeroSplitEditorialBlock";

import {
    HeroFloatingGlassBlock,
    HeroFloatingGlassSchema
} from "./Blocks/Hero/HeroFloatingGlassBlock";

import {
    HeroSaasDashboardBlock,
    HeroSaasDashboardSchema
} from "./Blocks/Hero/HeroSaasDashboardBlock";

import {
    ImageCtaBannerBlock,
    ImageCtaBannerSchema
} from "./Blocks/Hero/ImageCtaBannerBlock";

import {
    HeroFloatingCardsBlock,
    HeroFloatingCardsSchema
} from "./Blocks/Hero/HeroFloatingCardsBlock";

import {
    HeroVideoStyleBlock,
    HeroVideoStyleSchema
} from "./Blocks/Hero/HeroVideoStyleBlock";

import {
    HeroVideoBackgroundBlock,
    HeroVideoBackgroundSchema
} from "./Blocks/Hero/HeroVideoBackgroundBlock";

import {
    ContactFormModernBlock,
    ContactFormModernSchema
} from "./Blocks/Contact/ContactFormModernBlock";
import { FaqAccordionBlock, FaqAccordionSchema } from "./Blocks/FAQ/FaqAccordionBlock";
import { ContactDetailsBlock, ContactDetailsSchema } from "./Blocks/Contact/ContactDetailsBlock";
import { LocationMapBlock, LocationMapSchema } from "./Blocks/Contact/LocationMapBlock";
import { CaseStudiesGridBlock, CaseStudiesGridSchema } from "./Blocks/Collections/CaseStudiesGridBlock";
import { JobsListBlock, JobsListSchema } from "./Blocks/Collections/JobsListBlock";
import { EventsGridBlock, EventsGridSchema } from "./Blocks/Collections/EventsGridBlock";
import { BlogHubBlock, BlogHubSchema } from "./Blocks/Blog/BlogHubBlock";
import { BlogMiniHeroBlock, BlogMiniHeroSchema } from "./Blocks/Blog/BlogMiniHeroBlock";
import { NewsletterCtaBlock, NewsletterCtaSchema } from "./Blocks/Blog/NewsletterCtaBlock";
import { LatestResourcesBlock, LatestResourcesSchema } from "./Blocks/Blog/LatestResourcesBlock";

export const BlockRegistry = {

    hero_headline: {

        component: HeroHeadlineBlock,

        schema: HeroHeadlineSchema

    },

    hero_video_background: {

        component: HeroVideoBackgroundBlock,

        schema: HeroVideoBackgroundSchema

    },

    hero_video_style: {

        component: HeroVideoStyleBlock,

        schema: HeroVideoStyleSchema

    },

    hero_floating_cards: {

        component: HeroFloatingCardsBlock,

        schema: HeroFloatingCardsSchema

    },

    hero_background_image: {

        component: HeroBackgroundImageBlock,

        schema: HeroBackgroundImageSchema

    },

    hero_slider_fade: {

        component: HeroSliderFadeBlock,

        schema: HeroSliderFadeSchema

    },

    hero_parallax: {

        component: HeroParallaxBlock,

        schema: HeroParallaxSchema

    },

    hero_editorial_overlay: {

        component: HeroEditorialOverlayBlock,

        schema: HeroEditorialOverlaySchema

    },

    hero_split_image: {

        component: HeroSplitImageBlock,

        schema: HeroSplitImageSchema

    },

    hero_split_editorial: {

        component: HeroSplitEditorialBlock,

        schema: HeroSplitEditorialSchema

    },

    hero_floating_glass: {

        component: HeroFloatingGlassBlock,

        schema: HeroFloatingGlassSchema

    },

    hero_saas_dashboard: {

        component: HeroSaasDashboardBlock,

        schema: HeroSaasDashboardSchema

    },

    hero_luxury_fullscreen: { component: HeroLuxuryFullscreenBlock, schema: HeroLuxuryFullscreenSchema },
    hero_video_premium: { component: HeroVideoPremiumBlock, schema: HeroVideoPremiumSchema },

    image_cta_banner: {

        component: ImageCtaBannerBlock,

        schema: ImageCtaBannerSchema

    },

    pricing_cards: {

        component: PricingCardsBlock,

        schema: PricingCardsSchema

    },

    feature_image_left: {

        component: FeatureImageLeftBlock,

        schema: FeatureImageLeftSchema

    },

    feature_image_right: {

        component: FeatureImageRightBlock,

        schema: FeatureImageRightSchema

    },

    services_cards: {

	    component: ServicesCardsBlock,

	    schema: ServicesCardsSchema

	},

    services_bento: {

        component: ServicesBentoBlock,

        schema: ServicesBentoSchema

    },

	hero_centered_cta: {

	    component: HeroCenteredCTA,

	    schema: HeroCenteredCTASchema

	},

    process_timeline: {

        component: ProcessTimelineBlock,

        schema: ProcessTimelineSchema

    },

    stats_modern: {

        component: StatsModernBlock,

        schema: StatsModernSchema

    },

    team_modern: {

        component: TeamModernBlock,

        schema: TeamModernSchema

    },

    testimonials_carousel: {

        component: TestimonialsCarouselBlock,

        schema: TestimonialsCarouselSchema

    },

    contact_form_modern: {

        component: ContactFormModernBlock,

        schema: ContactFormModernSchema

    },

    faq_accordion: {
        component: FaqAccordionBlock,
        schema: FaqAccordionSchema,
    },

    contact_details: {
        component: ContactDetailsBlock,
        schema: ContactDetailsSchema,
    },

    location_map: {
        component: LocationMapBlock,
        schema: LocationMapSchema,
    },

    case_studies_grid: {
        component: CaseStudiesGridBlock,
        schema: CaseStudiesGridSchema,
    },

    jobs_list: {
        component: JobsListBlock,
        schema: JobsListSchema,
    },

    events_grid: {
        component: EventsGridBlock,
        schema: EventsGridSchema,
    },

    blog_hub: {
        component: BlogHubBlock,
        schema: BlogHubSchema,
    },

    blog_mini_hero: {
        component: BlogMiniHeroBlock,
        schema: BlogMiniHeroSchema,
    },

    newsletter_cta: {
        component: NewsletterCtaBlock,
        schema: NewsletterCtaSchema,
    },

    latest_resources: {
        component: LatestResourcesBlock,
        schema: LatestResourcesSchema,
    },

};
