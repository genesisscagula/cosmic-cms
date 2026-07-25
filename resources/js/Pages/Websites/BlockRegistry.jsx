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

import {
    HeroEditorialOverlayBlock,
    HeroEditorialOverlaySchema
} from "./Blocks/Hero/HeroEditorialOverlayBlock";

import {
    HeroSplitImageBlock,
    HeroSplitImageSchema
} from "./Blocks/Hero/HeroSplitImageBlock";

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

    hero_editorial_overlay: {

        component: HeroEditorialOverlayBlock,

        schema: HeroEditorialOverlaySchema

    },

    hero_split_image: {

        component: HeroSplitImageBlock,

        schema: HeroSplitImageSchema

    },

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

    testimonials_carousel: {

        component: TestimonialsCarouselBlock,

        schema: TestimonialsCarouselSchema

    },

};
