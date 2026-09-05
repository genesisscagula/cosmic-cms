export const guideCategories = ['All', 'Getting started', 'Design', 'Content & SEO', 'Publishing', 'Growth'];

export const guides = [
    {
        slug: 'plan-your-ai-website',
        category: 'Getting started',
        eyebrow: 'Start stronger',
        title: 'Plan an AI website before you generate it',
        description: 'Turn a vague idea into a useful business brief so Luna has the context to create a stronger first draft.',
        readTime: '6 min read',
        level: 'Beginner',
        icon: '✦',
        featured: true,
        takeaways: ['Define one primary conversion goal', 'List the pages the visitor actually needs', 'Give Luna enough brand and audience context'],
        sections: [
            {
                heading: 'Start with the business outcome',
                paragraphs: [
                    'A good website brief begins with the action you want a visitor to take. That may be booking an appointment, requesting a quote, purchasing a product, making a reservation, or contacting a team.',
                    'Keep one primary action clear. Secondary actions can still exist, but the page hierarchy is easier to build when the main outcome is obvious.',
                ],
                bullets: ['Primary audience', 'Primary conversion action', 'Core offer or service', 'Location or market served'],
            },
            {
                heading: 'Describe the brand in practical terms',
                paragraphs: [
                    'Instead of relying on broad words like modern or premium, describe the visual feeling in terms Luna can apply: editorial typography, warm natural colors, high-contrast calls to action, generous spacing, or compact information density.',
                ],
                bullets: ['Brand personality', 'Preferred colors or colors to avoid', 'Visual references', 'Tone of voice'],
            },
            {
                heading: 'Give every page a job',
                paragraphs: [
                    'Before generating, decide why each page exists. A Services page should clarify the offer. An About page should build trust. A Contact page should remove friction from the next step.',
                    'This keeps the generated website focused and makes later editing much faster.',
                ],
            },
        ],
    },
    {
        slug: 'write-better-luna-prompts',
        category: 'Getting started',
        eyebrow: 'Luna AI',
        title: 'Write prompts that produce better website changes',
        description: 'A practical prompt structure for page creation, redesigns, content changes, and design refinements with Luna.',
        readTime: '7 min read',
        level: 'Beginner',
        icon: '⌘',
        takeaways: ['State the exact scope', 'Describe the desired outcome', 'Add constraints only when they matter'],
        sections: [
            {
                heading: 'Name the thing you want changed',
                paragraphs: ['Tell Luna whether you are changing a full page, one section, navigation, typography, imagery, or content. Clear scope reduces accidental changes elsewhere.'],
                bullets: ['Create a Services page', 'Rewrite only the hero copy', 'Make this section more premium', 'Keep the existing header and footer'],
            },
            {
                heading: 'Describe the outcome, not implementation trivia',
                paragraphs: ['You do not need to specify every CSS property. Describe the visual or business result first, then add specific requirements when they are genuinely important.'],
            },
            {
                heading: 'Use existing design language as a constraint',
                paragraphs: ['For established websites, ask Luna to preserve the current design system. Marketplace websites already use their installed design kit as the preferred source for new pages and rebuilds.'],
            },
        ],
    },
    {
        slug: 'keep-pages-visually-consistent',
        category: 'Design',
        eyebrow: 'Design system',
        title: 'Keep new pages visually consistent with the rest of your site',
        description: 'Use typography, spacing, cards, buttons, headers, footers, and reusable patterns as one connected design system.',
        readTime: '8 min read',
        level: 'Intermediate',
        icon: '◇',
        takeaways: ['Reuse patterns before inventing new ones', 'Keep type and spacing rhythm consistent', 'Treat the header and footer as global anchors'],
        sections: [
            {
                heading: 'Reuse before redesigning',
                paragraphs: ['Consistency usually comes from repeating a small set of strong visual rules. Reuse existing card, CTA, hero, and section patterns before introducing an entirely new layout.'],
            },
            {
                heading: 'Protect the visual rhythm',
                paragraphs: ['Heading scale, body width, section spacing, button size, corner radius, and image treatment should feel related from page to page.'],
                bullets: ['Heading scale', 'Section padding', 'Container width', 'Button treatment', 'Card radius and shadow'],
            },
            {
                heading: 'Marketplace sites already have a design kit',
                paragraphs: ['When a website originates from Marketplace, Cosmic stores an installed reusable design kit with the customer website. Luna should prefer those patterns when creating additional pages.'],
            },
        ],
    },
    {
        slug: 'website-content-that-converts',
        category: 'Content & SEO',
        eyebrow: 'Content',
        title: 'Structure website content around real customer decisions',
        description: 'Build clearer pages by matching headlines, proof, services, objections, and calls to action to the visitor journey.',
        readTime: '9 min read',
        level: 'Intermediate',
        icon: 'Aa',
        takeaways: ['Lead with the visitor problem', 'Use proof close to the decision point', 'Keep CTAs specific'],
        sections: [
            {
                heading: 'Make the first screen immediately useful',
                paragraphs: ['A visitor should quickly understand what the business offers, who it is for, and what to do next. Avoid abstract headlines that depend on the rest of the page for meaning.'],
            },
            {
                heading: 'Build trust before asking for commitment',
                paragraphs: ['Testimonials, credentials, process clarity, examples, guarantees, and transparent service information reduce uncertainty. Place the strongest proof near important calls to action.'],
            },
            {
                heading: 'Write for scanning',
                paragraphs: ['Most visitors scan before they read closely. Use clear section headings, short paragraphs, specific labels, and visible calls to action.'],
            },
        ],
    },
    {
        slug: 'launch-checklist',
        category: 'Publishing',
        eyebrow: 'Launch',
        title: 'A practical website launch checklist',
        description: 'Review responsive layouts, content, links, forms, metadata, domains, and final publishing details before going live.',
        readTime: '7 min read',
        level: 'All levels',
        icon: '↗',
        takeaways: ['Preview all device sizes', 'Check real conversion paths', 'Verify SEO and domain basics'],
        sections: [
            {
                heading: 'Review the complete customer journey',
                paragraphs: ['Do not only inspect individual sections. Follow the path a real visitor takes from landing page to service detail, contact, booking, or checkout.'],
                bullets: ['Navigation links', 'Primary CTA destinations', 'Forms and confirmations', 'Phone and email links'],
            },
            {
                heading: 'Check responsive behavior',
                paragraphs: ['Preview the important pages on desktop, tablet, and mobile. Look for text wrapping, oversized whitespace, image cropping, menu behavior, and buttons that become difficult to use.'],
            },
            {
                heading: 'Finish the launch fundamentals',
                paragraphs: ['Confirm page titles, descriptions, favicon, social preview, domain configuration, analytics, and any required legal pages before the final publish.'],
            },
        ],
    },
    {
        slug: 'turn-website-into-growth-channel',
        category: 'Growth',
        eyebrow: 'Growth',
        title: 'Turn a finished website into an active growth channel',
        description: 'Use content, landing pages, lead capture, analytics, and iterative page improvements after the initial launch.',
        readTime: '8 min read',
        level: 'Intermediate',
        icon: '⚡',
        takeaways: ['Measure meaningful actions', 'Create focused campaign pages', 'Improve the site from actual behavior'],
        sections: [
            {
                heading: 'Choose metrics tied to the business',
                paragraphs: ['Traffic is useful context, but the most important metrics usually involve enquiries, bookings, purchases, qualified leads, or another meaningful conversion.'],
            },
            {
                heading: 'Build pages for specific campaigns',
                paragraphs: ['A focused landing page can match an ad, service, location, audience, or seasonal offer more closely than a general homepage. Reuse the site design system so those pages still feel native to the brand.'],
            },
            {
                heading: 'Iterate from evidence',
                paragraphs: ['Use analytics and customer feedback to identify confusing journeys, weak calls to action, missing information, or pages that deserve stronger proof.'],
            },
        ],
    },
];

export const docsSections = [
    {
        label: 'Getting started',
        items: [
            { slug: 'cosmic-overview', title: 'Cosmic CMS overview' },
            { slug: 'create-first-website', title: 'Create your first website' },
        ],
    },
    {
        label: 'Luna & AI',
        items: [
            { slug: 'luna-basics', title: 'Working with Luna' },
            { slug: 'luna-page-actions', title: 'Page creation and rebuilds' },
        ],
    },
    {
        label: 'Builder & design',
        items: [
            { slug: 'builder-overview', title: 'Visual Builder overview' },
            { slug: 'global-styling', title: 'Global styling' },
        ],
    },
    {
        label: 'Marketplace',
        items: [
            { slug: 'marketplace-websites', title: 'Marketplace websites' },
        ],
    },
    {
        label: 'Publishing',
        items: [
            { slug: 'preview-and-publish', title: 'Preview and publish' },
        ],
    },
    {
        label: 'Account & billing',
        items: [
            { slug: 'plans-and-credits', title: 'Plans and Cosmic Credits' },
        ],
    },
];

export const docs = [
    {
        slug: 'cosmic-overview',
        category: 'Getting started',
        title: 'Cosmic CMS overview',
        description: 'Understand how websites, Luna, the visual builder, Marketplace, publishing, plans, and credits fit together.',
        updated: 'September 2026',
        icon: '◎',
        sections: [
            {
                heading: 'How Cosmic CMS is organized',
                paragraphs: ['Cosmic CMS combines website creation, AI-assisted editing, reusable design systems, content management, previews, and publishing in one connected workflow.'],
                bullets: ['Dashboard for websites and account-level work', 'Luna for natural-language website actions', 'Visual Builder for direct page refinement', 'Marketplace for premium installed website designs', 'Publishing workflow for preview, staging, and live delivery'],
            },
            {
                heading: 'Two ways to start a website',
                paragraphs: ['A website can begin in Cosmic Studio or from a Marketplace template. Studio websites keep flexible Builder tools. Marketplace websites preserve the installed template as their primary design system.'],
            },
            {
                heading: 'What Luna changes',
                paragraphs: ['Luna can create pages, rewrite content, change layouts, refine typography and colors, and perform supported website actions. For Marketplace websites, Luna should preserve the installed design language unless the user explicitly asks for a broader redesign.'],
            },
        ],
    },
    {
        slug: 'create-first-website',
        category: 'Getting started',
        title: 'Create your first website',
        description: 'Create a website from a short brief, review the generated structure, and continue editing in the Builder.',
        updated: 'September 2026',
        icon: '✦',
        sections: [
            {
                heading: 'Start with a useful brief',
                paragraphs: ['Provide the business type, audience, primary offer, desired action, and useful visual direction. Luna uses this information to create a more relevant starting point.'],
            },
            {
                heading: 'Review the generated pages',
                paragraphs: ['Check whether the page structure matches the business. Add, remove, or rebuild pages when needed before spending time polishing small visual details.'],
            },
            {
                heading: 'Refine and preview',
                paragraphs: ['Use Luna and the Builder to refine content, imagery, sections, navigation, and styling. Preview important pages across device sizes before publishing.'],
            },
        ],
    },
    {
        slug: 'luna-basics',
        category: 'Luna & AI',
        title: 'Working with Luna',
        description: 'Use Luna for website-level and page-level changes while keeping the request clear and scoped.',
        updated: 'September 2026',
        icon: '✦',
        sections: [
            {
                heading: 'Ask for an outcome',
                paragraphs: ['Describe the result you want in plain language. Luna can translate supported requests into website actions without requiring implementation-specific instructions.'],
                bullets: ['Create a Services page', 'Rewrite the homepage hero', 'Make this section more premium', 'Change the site typography', 'Add a background image related to the content'],
            },
            {
                heading: 'Keep scope explicit',
                paragraphs: ['If the request should only affect one section, say so. If a change should apply across the site, identify it as a global change.'],
            },
            {
                heading: 'AI usage and credits',
                paragraphs: ['Cosmic Credits are intended for real AI/API work. Manual interface actions should not consume credits unless they trigger a supported AI operation.'],
            },
        ],
    },
    {
        slug: 'luna-page-actions',
        category: 'Luna & AI',
        title: 'Page creation and rebuilds',
        description: 'Understand how Luna chooses patterns when creating new pages or rebuilding existing pages.',
        updated: 'September 2026',
        icon: '▦',
        sections: [
            {
                heading: 'Studio websites',
                paragraphs: ['For Studio websites, Luna can use the website design system and supported reusable patterns to create or rebuild a page while keeping the broader site consistent.'],
            },
            {
                heading: 'Marketplace websites',
                paragraphs: ['For Marketplace websites, Luna should prefer the installed Marketplace design kit and reusable template patterns instead of defaulting to unrelated global Sparks.'],
                bullets: ['Reuse colors and typography', 'Reuse spacing and visual effects', 'Reuse header and footer design', 'Reuse cards, grids, CTAs, forms, and navigation styling'],
            },
            {
                heading: 'When generic patterns are acceptable',
                paragraphs: ['A generic Spark should only be used when no suitable installed Marketplace pattern exists and the result can be safely adapted to the website design system.'],
            },
        ],
    },
    {
        slug: 'builder-overview',
        category: 'Builder & design',
        title: 'Visual Builder overview',
        description: 'Refine pages visually, manage sections, work with media, and preview responsive output.',
        updated: 'September 2026',
        icon: '⌘',
        sections: [
            {
                heading: 'Page editing',
                paragraphs: ['The Builder provides the visual workspace for supported content and layout changes while keeping the underlying website structured.'],
            },
            {
                heading: 'Section actions',
                paragraphs: ['Use section-level controls for focused editing, reordering, duplication, deletion, media changes, and Luna-assisted refinement where available.'],
            },
            {
                heading: 'Responsive preview',
                paragraphs: ['Check desktop, tablet, and mobile presentations before publishing. Responsive review is especially important after major layout or typography changes.'],
            },
        ],
    },
    {
        slug: 'global-styling',
        category: 'Builder & design',
        title: 'Global styling',
        description: 'Manage typography, heading scale, buttons, spacing, corners, and effects from a shared design layer.',
        updated: 'September 2026',
        icon: 'Aa',
        sections: [
            {
                heading: 'Typography',
                paragraphs: ['Choose heading and body fonts that remain readable across sections and device sizes. Use a consistent heading scale rather than styling every section independently.'],
            },
            {
                heading: 'Buttons and surfaces',
                paragraphs: ['Keep button shapes, corner radius, borders, shadows, and card treatments coherent so the interface feels intentionally designed.'],
            },
            {
                heading: 'Spacing',
                paragraphs: ['Section spacing and content width define much of the visual rhythm of a website. Adjust global defaults carefully and verify pages with different content lengths.'],
            },
        ],
    },
    {
        slug: 'marketplace-websites',
        category: 'Marketplace',
        title: 'Marketplace websites',
        description: 'Learn how purchased templates remain protected while still supporting content editing and Luna customization.',
        updated: 'September 2026',
        icon: '◇',
        sections: [
            {
                heading: 'The installed design stays primary',
                paragraphs: ['Marketplace websites do not expose generic Add Sparks, generic Theme switching, or generic Color Family switching in the normal Builder workflow. This protects the purchased design from accidental replacement.'],
            },
            {
                heading: 'The customer gets an installed design kit',
                paragraphs: ['Provisioning stores enough reusable design information with the customer website to create future pages without modifying the master Marketplace template.'],
                bullets: ['Design tokens and colors', 'Typography and spacing', 'Buttons and form styling', 'Header and footer patterns', 'Heroes, sections, cards, grids, CTAs, and page layouts'],
            },
            {
                heading: 'Luna can still customize the website',
                paragraphs: ['Users can explicitly ask Luna to change colors, typography, sections, layout, or other supported design details. By default, Luna should preserve the Marketplace template design language.'],
            },
        ],
    },
    {
        slug: 'preview-and-publish',
        category: 'Publishing',
        title: 'Preview and publish',
        description: 'Review website output before launch and move supported sites through staging and publishing workflows.',
        updated: 'September 2026',
        icon: '↗',
        sections: [
            {
                heading: 'Preview first',
                paragraphs: ['Use preview to inspect navigation, content, responsive layout, imagery, forms, and the complete visitor journey before publishing.'],
            },
            {
                heading: 'Publishing',
                paragraphs: ['Publishing prepares the current website version for its configured live delivery workflow. Keep important account and domain settings complete before the final launch.'],
            },
            {
                heading: 'After launch',
                paragraphs: ['Continue using the same website design system for future content, page additions, campaign pages, and ongoing improvements.'],
            },
        ],
    },
    {
        slug: 'plans-and-credits',
        category: 'Account & billing',
        title: 'Plans and Cosmic Credits',
        description: 'Understand the difference between subscription access, website limits, plan features, and AI usage credits.',
        updated: 'September 2026',
        icon: '⚡',
        sections: [
            {
                heading: 'Plans define product access',
                paragraphs: ['Your subscription determines website limits and the product capabilities available to the account. The current pricing page is the source of truth for active plan positioning.'],
            },
            {
                heading: 'Credits cover AI/API work',
                paragraphs: ['Cosmic Credits are used for supported AI or external API operations. Routine manual website actions should remain separate from AI usage.'],
            },
            {
                heading: 'Marketplace and subscriptions',
                paragraphs: ['Marketplace is an Agency feature. Each template installation uses Cosmic Credits and provisions into a normal customer Website that shares the same Agency website allowance as Cosmic Studio. The installed customer copy can then be edited without modifying the master Marketplace template.'],
            },
        ],
    },
];

export const findGuide = (slug) => guides.find((guide) => guide.slug === slug);
export const findDoc = (slug) => docs.find((doc) => doc.slug === slug);
