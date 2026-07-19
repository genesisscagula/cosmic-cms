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

export const BlockRegistry = {

    hero_headline: {

        component: HeroHeadlineBlock,

        schema: HeroHeadlineSchema

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

	hero_centered_cta: {

	    component: HeroCenteredCTA,

	    schema: HeroCenteredCTASchema

	},

};