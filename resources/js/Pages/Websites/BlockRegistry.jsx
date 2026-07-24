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

export const BlockRegistry = {

    hero_headline: {

        component: HeroHeadlineBlock,

        schema: HeroHeadlineSchema

    },

    hero_background_image: {

        component: HeroBackgroundImageBlock,

        schema: HeroBackgroundImageSchema

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
