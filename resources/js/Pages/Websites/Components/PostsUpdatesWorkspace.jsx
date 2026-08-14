import { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { showCosmicNotification, confirmCosmicAction } from '@/Components/CosmicNotification';
import { colorFamilies } from '@/theme/colorFamilies';
import MediaPickerModal from '@/Components/Media/MediaPickerModal';

const slugify = (value = '') => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

const fieldPromptSamples = (type) => {
    const name = type?.singular_name || type?.name || 'content item';
    const slug = String(type?.slug || '').toLowerCase();
    if (slug.includes('event')) return [
        'Create fields for professional events: dates, venue, address, registration link, organizer, event format, capacity, and agenda highlights.',
        'Design a structured event schema for conferences and workshops with schedule, speakers, ticket status, location, and gallery.',
    ];
    if (slug.includes('project')) return [
        'Create premium case study fields: client, services, project year, challenge, outcome metrics, project URL, gallery, and testimonial.',
        'Build fields for portfolio projects with industry, completion date, location, services, key results, featured media, and project link.',
    ];
    if (slug.includes('blog') || slug.includes('post')) return [
        'Create editorial blog fields for author, reading time, article series, difficulty level, key takeaway, source link, and related call to action.',
        'Design a practical magazine-style post schema with author, reading time, topic, audience, key points, and optional supporting image gallery.',
    ];
    return [
        `Create a polished schema for ${name} entries with the most useful structured text, media, status, date, select, and relationship fields.`,
        `Design professional custom fields for ${name} content. Keep the schema concise, practical, and suitable for a premium public website.`,
    ];
};


const templatePromptSamples = (type, mode = 'single') => {
    const slug = String(type?.slug || '').toLowerCase();
    const singular = type?.singular_name || type?.name || 'content';
    if (mode === 'archive') {
        if (slug.includes('blog') || slug.includes('post')) return [
            'Create a premium editorial archive with a featured lead story, elegant article cards, strong imagery, category labels, and generous whitespace.',
            'Design a modern magazine archive with an asymmetric featured post, compact metadata, refined cards, and a polished responsive grid.',
        ];
        if (slug.includes('project')) return [
            'Create a premium portfolio archive with cinematic project imagery, understated metadata, and an editorial case-study grid.',
            'Design a clean agency case-study listing with large image cards, project categories, concise excerpts, and refined spacing.',
        ];
        return [
            `Create a premium ${singular} archive with a strong featured item, elegant cards, clear metadata, and responsive spacing.`,
            `Design a polished ${singular} listing with modern editorial hierarchy, premium imagery, and a clean responsive grid.`,
        ];
    }
    if (slug.includes('blog') || slug.includes('post')) return [
        'Create a premium editorial article with a cinematic hero image, narrow readable content column, elegant metadata, gallery treatment, and subtle category styling.',
        'Design a refined magazine-style article with oversized headline typography, editorial whitespace, premium image treatment, and a clean reading experience.',
    ];
    if (slug.includes('project')) return [
        'Create a cinematic case-study page with a bold hero, project overview, strong results hierarchy, gallery sections, and premium agency styling.',
        'Design a minimal luxury portfolio detail page with large imagery, concise metadata, readable narrative content, and elegant spacing.',
    ];
    return [
        `Create a premium ${singular} detail page with strong visual hierarchy, polished media treatment, readable content, and elegant metadata.`,
        `Design a modern luxury ${singular} page with cinematic imagery, refined typography, generous whitespace, and responsive content sections.`,
    ];
};


const safeTemplateText = (value = '') => String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));

const contentTemplateProfile = (type) => {
    const haystack = `${type?.slug || ''} ${type?.name || ''} ${type?.singular_name || ''}`.toLowerCase();
    if (haystack.includes('faq')) return { kind: 'faq', eyebrow: 'Helpful answers', archiveTitle: 'Frequently asked questions', archiveIntro: 'Clear answers to the questions people ask most.', sparkTitle: 'Still need help?', sparkCopy: 'Use the supporting section for contact details, related guidance, or the next useful action.' };
    if (haystack.includes('service')) return { kind: 'services', eyebrow: 'Service', archiveTitle: 'Services built around your goals', archiveIntro: 'Explore capabilities, deliverables, and ways to work together.', sparkTitle: 'Ready to move forward?', sparkCopy: 'Pair the service details with a clear next step or related capability.' };
    if (haystack.includes('team') || haystack.includes('people')) return { kind: 'team', eyebrow: 'Meet the team', archiveTitle: 'People behind the work', archiveIntro: 'Meet the people, roles, and expertise behind the brand.', sparkTitle: 'Work with our team', sparkCopy: 'Use this supporting section for expertise, culture, or a contact call-to-action.' };
    if (haystack.includes('testimonial') || haystack.includes('review')) return { kind: 'testimonials', eyebrow: 'Client story', archiveTitle: 'What clients are saying', archiveIntro: 'Real experiences, outcomes, and feedback from clients and customers.', sparkTitle: 'More proof, less promise', sparkCopy: 'Pair customer stories with related services, outcomes, or a conversion call-to-action.' };
    if (haystack.includes('job') || haystack.includes('career')) return { kind: 'jobs', eyebrow: 'Opportunity', archiveTitle: 'Open opportunities', archiveIntro: 'Browse current roles, teams, locations, and ways to join us.', sparkTitle: 'Build what comes next', sparkCopy: 'Use this supporting section for culture, benefits, hiring steps, or an application call-to-action.' };
    if (haystack.includes('event')) return { kind: 'events', eyebrow: 'Event', archiveTitle: 'Upcoming events', archiveIntro: 'Discover upcoming dates, venues, sessions, and ways to take part.', sparkTitle: 'Plan your visit', sparkCopy: 'Surface registration details, related events, or useful attendee information.' };
    if (haystack.includes('project') || haystack.includes('case')) return { kind: 'projects', eyebrow: 'Case study', archiveTitle: 'Selected projects', archiveIntro: 'Explore recent work, outcomes, and the thinking behind each project.', sparkTitle: 'Explore the work', sparkCopy: 'Connect the case study to related capabilities, results, or another project.' };
    if (haystack.includes('news')) return { kind: 'news', eyebrow: 'News', archiveTitle: 'Latest news', archiveIntro: 'Company updates, announcements, and timely stories.', sparkTitle: 'Keep exploring', sparkCopy: 'Use this supporting section for related updates, context, or the next story.' };
    return { kind: 'editorial', eyebrow: 'Latest story', archiveTitle: `Latest ${safeTemplateText(type?.name || 'updates')}`, archiveIntro: safeTemplateText(type?.description || 'Explore recent entries, highlights, and fresh updates.'), sparkTitle: 'Discover more', sparkCopy: 'New published entries automatically flow into this collection.' };
};

const sidebarSafeSchemaTypes = new Set(['text', 'select', 'number', 'date', 'url', 'boolean']);

const schemaBindingsForPreset = (type, limit = 4, compact = false) => (type?.schema || [])
    .filter((field) => field?.key && !['gallery', 'group', 'repeater', 'relation'].includes(field?.type))
    // Sidebars are intentionally for short/scannable metadata only. Long copy
    // (rich text, textarea, repeaters, etc.) belongs in the main reading column.
    .filter((field) => !compact || sidebarSafeSchemaTypes.has(String(field?.type || 'text').toLowerCase()))
    .slice(0, limit)
    .map((field) => ({ key: `custom_fields.${field.key}`, label: safeTemplateText(field.label || field.key), type: field.type || 'text' }));

const schemaDetailMarkup = (type, styleId, compact = false) => {
    const fields = schemaBindingsForPreset(type, 6, compact);
    if (!fields.length) return '';
    const shell = styleId === 'minimal' ? 'border-y py-8' : styleId === 'bold' ? 'rounded-[2rem] border-2 p-7' : 'rounded-[1.75rem] border p-6';
    const outer = compact ? `w-full min-w-0 ${shell}` : `mx-auto mt-10 w-full max-w-5xl ${shell}`;
    const columns = compact ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-1' : 'sm:grid-cols-2 lg:grid-cols-3';
    return `<section data-cosmic-schema-fields="true" data-cosmic-schema-compact="${compact ? 'true' : 'false'}" class="${outer}" style="border-color:var(--dt-border)"><p class="text-xs font-extrabold uppercase tracking-[.18em]" style="color:var(--dt-primary)">Details</p><dl class="mt-5 grid gap-3 ${columns}">${fields.map((field) => `<div class="min-w-0 rounded-2xl p-4" style="background:color-mix(in srgb,var(--dt-primary) 6%,var(--dt-bg))"><dt class="text-[11px] font-bold uppercase tracking-[.14em]" style="color:var(--dt-muted)">${field.label}</dt><dd class="mt-2 break-words text-sm font-bold">{{ ${field.key} }}</dd></div>`).join('')}</dl></section>`;
};

const archiveMetaMarkup = (type) => schemaBindingsForPreset(type, 2, true).map((field) => `<span>{{ ${field.key} }}</span>`).join('<span aria-hidden="true">·</span>');

const genericPostSparkDefaults = (type, mode = 'single') => {
    const profile = contentTemplateProfile(type);
    if (mode === 'archive') return {
        related_title: profile.kind === 'events' ? 'More events to explore' : profile.kind === 'projects' ? 'More projects to explore' : 'More from this collection',
        related_copy: profile.kind === 'events' ? 'Keep browsing upcoming dates, sessions and experiences.' : profile.kind === 'projects' ? 'Explore more work, outcomes and case studies.' : 'Continue exploring recent stories and useful updates.',
        spotlight_title: 'Featured next step',
        spotlight_copy: 'Use this space for a featured category, service, offer or useful destination.',
        cta_title: 'Stay connected',
        cta_copy: 'Add a simple next step that fits this website — subscribe, enquire, book or keep exploring.',
    };
    return {
        related_title: profile.kind === 'events' ? 'More events' : profile.kind === 'projects' ? 'Related projects' : 'Related stories',
        related_copy: profile.kind === 'events' ? 'Help visitors discover another date, session or related experience.' : profile.kind === 'projects' ? 'Connect this case study with another relevant project or capability.' : 'Surface another useful article or update after the main content.',
        spotlight_title: profile.kind === 'team' ? 'Work with our team' : profile.kind === 'events' ? 'Plan your next step' : 'Author spotlight',
        spotlight_copy: profile.kind === 'team' ? 'Use this block for expertise, availability or a direct contact path.' : 'A flexible supporting block for author context, a service link or a focused call-to-action.',
        cta_title: 'Keep the conversation going',
        cta_copy: 'Use this final block for an enquiry, newsletter, booking link or the next useful action.',
    };
};

const genericPostSparksMarkup = (type, mode = 'single') => {
    const copy = genericPostSparkDefaults(type, mode);
    const width = mode === 'archive' ? 'max-w-7xl' : 'max-w-6xl';
    return `<div data-cosmic-generic-post-sparks="true" class="border-t" style="border-color:var(--dt-border)">
        <section data-cosmic-post-spark="related" class="px-6 py-10 lg:px-8"><div class="mx-auto ${width} grid gap-5 md:grid-cols-[1fr_auto] md:items-end"><div><p class="text-xs font-extrabold uppercase tracking-[.18em]" style="color:var(--dt-primary)"><span data-cosmic-generic-copy="related_title">${copy.related_title}</span></p><p class="mt-2 max-w-2xl text-sm leading-6" style="color:var(--dt-muted)"><span data-cosmic-generic-copy="related_copy">${copy.related_copy}</span></p></div><span class="text-sm font-extrabold" style="color:var(--dt-primary)">Explore more →</span></div></section>
        <section data-cosmic-post-spark="spotlight" class="border-y px-6 py-10 lg:px-8" style="border-color:var(--dt-border);background:color-mix(in srgb,var(--dt-primary) 6%,var(--dt-bg))"><div class="mx-auto ${width} rounded-[1.75rem] border p-6 sm:p-8" style="border-color:color-mix(in srgb,var(--dt-primary) 22%,var(--dt-border));background:var(--dt-bg)"><p class="text-lg font-extrabold"><span data-cosmic-generic-copy="spotlight_title">${copy.spotlight_title}</span></p><p class="mt-2 max-w-3xl text-sm leading-6" style="color:var(--dt-muted)"><span data-cosmic-generic-copy="spotlight_copy">${copy.spotlight_copy}</span></p></div></section>
        <section data-cosmic-post-spark="cta" class="px-6 py-12 lg:px-8"><div class="mx-auto ${width} rounded-[2rem] p-7 text-white sm:p-10" style="background:var(--dt-primary)"><p class="text-2xl font-extrabold tracking-[-.03em]"><span data-cosmic-generic-copy="cta_title">${copy.cta_title}</span></p><p class="mt-3 max-w-2xl text-sm leading-6 text-white/80"><span data-cosmic-generic-copy="cta_copy">${copy.cta_copy}</span></p></div></section>
    </div>`;
};

const extractGenericPostSparkCopy = (markup, type, mode) => {
    const defaults = genericPostSparkDefaults(type, mode);
    return Object.fromEntries(Object.entries(defaults).map(([key, fallback]) => {
        const rx = new RegExp(`<span[^>]*data-cosmic-generic-copy=["']${key}["'][^>]*>([\\s\\S]*?)<\\/span>`, 'i');
        const match = String(markup || '').match(rx);
        return [key, match?.[1]?.replace(/<[^>]*>/g, '').trim() || fallback];
    }));
};

const applyGenericPostSparkCopy = (markup, copy = {}) => {
    let next = String(markup || '');
    Object.entries(copy).forEach(([key, value]) => {
        const safe = safeTemplateText(value || '');
        const rx = new RegExp(`(<span[^>]*data-cosmic-generic-copy=["']${key}["'][^>]*>)[\\s\\S]*?(<\\/span>)`, 'i');
        next = next.replace(rx, `$1${safe}$2`);
    });
    return next;
};

const buildSchemaAwareDynamicTheme = (preset, type) => {
    const profile = contentTemplateProfile(type);
    const style = {
        premium: { bg: '#ffffff', hero: 'text-center', heroWrap: 'max-w-5xl', title: 'text-4xl sm:text-6xl lg:text-7xl', card: 'rounded-[1.75rem] shadow-sm hover:-translate-y-1 hover:shadow-xl', content: 'max-w-3xl text-lg leading-8' },
        editorial: { bg: '#fbfaf7', hero: 'text-left', heroWrap: 'max-w-6xl', title: 'text-5xl lg:text-7xl', card: 'rounded-2xl border-l-4', content: 'max-w-3xl text-lg leading-9' },
        modern: { bg: '#ffffff', hero: 'text-left', heroWrap: 'max-w-6xl', title: 'text-4xl sm:text-6xl', card: 'rounded-3xl shadow-sm', content: 'max-w-4xl text-lg leading-8' },
        minimal: { bg: '#ffffff', hero: 'text-left', heroWrap: 'max-w-4xl', title: 'text-4xl sm:text-5xl', card: 'rounded-none border-b', content: 'max-w-3xl text-lg leading-9' },
        magazine: { bg: '#fffdf8', hero: 'text-left', heroWrap: 'max-w-7xl', title: 'text-5xl lg:text-7xl', card: 'rounded-[1.5rem] shadow-md', content: 'max-w-3xl text-lg leading-9' },
        bold: { bg: '#ffffff', hero: 'text-left', heroWrap: 'max-w-7xl', title: 'text-5xl sm:text-7xl lg:text-8xl', card: 'rounded-[2rem] border-2 shadow-[8px_8px_0_var(--dt-primary)]', content: 'max-w-4xl text-xl leading-9' },
    }[preset.id] || {};
    const details = schemaDetailMarkup(type, preset.id);
    const compactDetails = schemaDetailMarkup(type, preset.id, true);
    const archiveMeta = archiveMetaMarkup(type);
    const typeName = safeTemplateText(type?.name || 'Updates');
    const singular = safeTemplateText(type?.singular_name || type?.name || 'Entry');
    const contentKind = safeTemplateText(profile.kind || type?.slug || 'content');
    const vars = `--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-accent:var(--a,var(--cosmic-accent,#10b981));--dt-surface:var(--s,#f1f5f9);--dt-muted:var(--m,#64748b);--dt-border:var(--b,#e2e8f0);--dt-bg:var(--bg,${style.bg});`;

    // Page Style is authoritative for every premade theme. The six visual
    // families may change typography/cards/body composition, but never choose
    // their own mini-header surface. Clean stays light; Auto/Balanced/Premium
    // resolve to the website primary surface in the runtime shell.
    const heroMode = 'inherit';
    const heroShell = 'border-color:var(--dt-border)';
    const heroAccent = 'color:var(--dt-primary)';
    const heroMuted = 'color:var(--dt-muted)';

    let singleBody = `<div class="mx-auto mt-10 ${style.content}">{{ content }}</div>${details}<div class="mx-auto mt-10 max-w-5xl">{{ gallery }}</div>`;
    if (profile.kind === 'team') singleBody = `<div class="mx-auto mt-10 grid max-w-5xl gap-8 md:grid-cols-[280px_1fr]"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-square w-full rounded-[2rem] object-cover"><div class="${style.content}">{{ content }}</div></div>${details}`;
    else if (profile.kind === 'testimonials') singleBody = `<div class="mx-auto mt-12 max-w-4xl"><blockquote class="text-3xl font-extrabold leading-tight sm:text-5xl">“{{ excerpt }}”</blockquote><div class="mt-8 ${style.content}">{{ content }}</div></div>${details}`;
    else if (profile.kind === 'faq') singleBody = `<div class="mx-auto mt-10 max-w-4xl rounded-[2rem] border p-7 sm:p-10" style="border-color:var(--dt-border)"><p class="text-xs font-bold uppercase tracking-[.18em]" style="color:var(--dt-primary)">Answer</p><div class="mt-5 ${style.content}">{{ content }}</div></div>${details}`;
    else if (profile.kind === 'jobs') singleBody = `<div class="mx-auto mt-10 grid max-w-6xl gap-10 lg:grid-cols-[1fr_300px]"><div class="${style.content}">{{ content }}</div><aside class="h-fit rounded-[1.75rem] border p-6" style="border-color:var(--dt-border)"><p class="text-xs font-extrabold uppercase tracking-[.18em]" style="color:var(--dt-primary)">Role details</p>${compactDetails || '<div class="mt-4 text-sm" style="color:var(--dt-muted)">{{ tags }}</div>'}</aside></div>`;
    else if (profile.kind === 'events') singleBody = `<div data-cosmic-content-layout="events" class="mx-auto mt-10 grid w-full max-w-6xl min-w-0 gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(280px,320px)]"><div><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[16/9] w-full rounded-[2rem] object-cover"><div class="mt-10 ${style.content}">{{ content }}</div></div><aside class="h-fit rounded-[1.75rem] border p-6" style="border-color:var(--dt-border)"><p class="text-xs font-extrabold uppercase tracking-[.18em]" style="color:var(--dt-primary)">Event details</p><div class="mt-5 min-w-0">${compactDetails}</div></aside></div>`;
    else if (profile.kind === 'services') singleBody = `<div class="mx-auto mt-10 grid max-w-6xl gap-10 lg:grid-cols-[.9fr_1.1fr]"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[4/3] w-full rounded-[2rem] object-cover"><div class="min-w-0"><div class="${style.content}">{{ content }}</div><div class="mt-8">${compactDetails}</div></div></div>`;
    else if (profile.kind === 'projects') singleBody = `<div data-cosmic-content-layout="projects" class="mx-auto mt-10 w-full max-w-7xl min-w-0"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[16/8] w-full rounded-[2rem] object-cover"><div class="mx-auto mt-12 grid max-w-6xl gap-10 lg:grid-cols-[1fr_2fr]"><div class="min-w-0">${compactDetails}</div><div class="min-w-0 ${style.content}">{{ content }}</div></div><div class="mt-12">{{ gallery }}</div></div>`;
    else singleBody = `<img src="{{ featured_image_url }}" alt="{{ title }}" class="mx-auto mt-10 aspect-[16/9] w-full max-w-6xl rounded-[2rem] object-cover">${singleBody}`;

    // Make the six visual presets genuinely different below the mini hero as well.
    // Entry bindings stay untouched; only the presentation frame changes.
    if (preset.id === 'editorial') {
        singleBody = `<div data-cosmic-post-layout="editorial" class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[180px_minmax(0,1fr)]"><aside class="hidden border-r pr-6 lg:block" style="border-color:var(--dt-border);color:var(--dt-muted)"><p class="text-xs font-extrabold uppercase tracking-[.18em]" style="color:var(--dt-primary)">Story notes</p><div class="mt-4 text-sm leading-7">{{ category }}<br>{{ published_at }}</div><div class="mt-5 text-xs leading-6">{{ tags }}</div></aside><div class="min-w-0">${singleBody}</div></div>`;
    } else if (preset.id === 'modern') {
        singleBody = `<div data-cosmic-post-layout="modern" class="mx-auto max-w-7xl rounded-[2rem] border p-4 sm:p-7 lg:p-9" style="border-color:var(--dt-border);background:color-mix(in srgb,var(--dt-primary) 4%,var(--dt-bg))">${singleBody}</div>`;
    } else if (preset.id === 'minimal') {
        singleBody = `<div data-cosmic-post-layout="minimal" class="mx-auto max-w-4xl border-y py-4 sm:py-8" style="border-color:var(--dt-border)">${singleBody}</div>`;
    } else if (preset.id === 'magazine') {
        singleBody = `<div data-cosmic-post-layout="magazine" class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[250px_minmax(0,1fr)]"><aside class="h-fit rounded-[1.5rem] border p-6 lg:sticky lg:top-6" style="border-color:var(--dt-border);background:color-mix(in srgb,var(--dt-primary) 5%,var(--dt-bg))"><p class="text-xs font-extrabold uppercase tracking-[.18em]" style="color:var(--dt-primary)">In this story</p><p class="mt-4 text-sm font-bold">{{ category }}</p><div class="mt-3 text-xs leading-6" style="color:var(--dt-muted)">{{ published_at }}</div><div class="mt-4 text-xs leading-6" style="color:var(--dt-muted)">{{ tags }}</div></aside><div class="min-w-0">${singleBody}</div></div>`;
    } else if (preset.id === 'bold') {
        singleBody = `<div data-cosmic-post-layout="bold" class="mx-auto max-w-7xl border-l-[10px] pl-5 sm:pl-8" style="border-color:var(--dt-primary)">${singleBody}</div>`;
    }

    let archiveCards = `<div data-cosmic-catalog="true" class="mx-auto grid max-w-7xl gap-7 px-6 py-12 md:grid-cols-2 lg:grid-cols-3 lg:px-8">{{#entries}}<article class="group overflow-hidden border bg-white p-0 transition ${style.card}" style="border-color:var(--dt-border)"><a href="{{ url }}" class="block"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[4/3] w-full object-cover"><div class="p-6"><p class="text-xs font-bold uppercase tracking-[.16em]" style="color:var(--dt-primary)">{{ category }}</p><h2 class="mt-3 text-2xl font-extrabold tracking-[-.03em]">{{ title }}</h2><p class="mt-3 line-clamp-3 text-sm leading-6" style="color:var(--dt-muted)">{{ excerpt }}</p>${archiveMeta ? `<div class="mt-4 flex flex-wrap gap-2 text-xs" style="color:var(--dt-muted)">${archiveMeta}</div>` : ''}<p class="mt-5 text-sm font-bold" style="color:var(--dt-primary)">View ${singular} →</p></div></a></article>{{/entries}}</div>`;
    if (profile.kind === 'faq') archiveCards = `<div data-cosmic-catalog="true" class="mx-auto max-w-5xl space-y-3 px-6 py-12">{{#entries}}<details class="rounded-2xl border bg-white p-5" style="border-color:var(--dt-border)"><summary class="cursor-pointer text-lg font-extrabold">{{ title }}</summary><div class="mt-4 leading-7" style="color:var(--dt-muted)">{{ content }}</div><a href="{{ url }}" class="mt-4 inline-flex text-sm font-bold" style="color:var(--dt-primary)">Open answer →</a></details>{{/entries}}</div>`;
    else if (profile.kind === 'jobs') archiveCards = `<div data-cosmic-catalog="true" class="mx-auto max-w-6xl divide-y px-6 py-12" style="border-color:var(--dt-border)">{{#entries}}<a href="{{ url }}" class="grid gap-3 py-7 transition hover:translate-x-1 md:grid-cols-[1fr_auto] md:items-center"><div><p class="text-xs font-bold uppercase tracking-[.16em]" style="color:var(--dt-primary)">{{ category }}</p><h2 class="mt-2 text-2xl font-extrabold">{{ title }}</h2><p class="mt-2 text-sm" style="color:var(--dt-muted)">{{ excerpt }}</p>${archiveMeta ? `<div class="mt-3 flex flex-wrap gap-2 text-xs" style="color:var(--dt-muted)">${archiveMeta}</div>` : ''}</div><span class="font-bold" style="color:var(--dt-primary)">View role →</span></a>{{/entries}}</div>`;
    else if (profile.kind === 'testimonials') archiveCards = `<div data-cosmic-catalog="true" class="mx-auto columns-1 gap-6 px-6 py-12 md:columns-2 lg:max-w-7xl lg:columns-3 lg:px-8">{{#entries}}<article class="mb-6 break-inside-avoid border bg-white p-7 ${style.card}" style="border-color:var(--dt-border)"><p class="text-2xl font-extrabold leading-snug">“{{ excerpt }}”</p><h2 class="mt-6 text-sm font-extrabold">{{ title }}</h2>${archiveMeta ? `<div class="mt-2 flex flex-wrap gap-2 text-xs" style="color:var(--dt-muted)">${archiveMeta}</div>` : ''}<a href="{{ url }}" class="mt-5 inline-flex text-sm font-bold" style="color:var(--dt-primary)">Read story →</a></article>{{/entries}}</div>`;
    else if (profile.kind === 'team') archiveCards = `<div data-cosmic-catalog="true" class="mx-auto grid max-w-7xl gap-8 px-6 py-12 sm:grid-cols-2 lg:grid-cols-3 lg:px-8">{{#entries}}<a href="{{ url }}" class="group"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-square w-full rounded-[2rem] object-cover"><h2 class="mt-5 text-2xl font-extrabold">{{ title }}</h2><div class="mt-2 text-sm" style="color:var(--dt-muted)">${archiveMeta || '{{ category }}'}</div></a>{{/entries}}</div>`;
    else if (profile.kind === 'events') archiveCards = `<div data-cosmic-catalog="true" class="mx-auto grid max-w-7xl gap-6 px-6 py-12 lg:grid-cols-2 lg:px-8">{{#entries}}<a href="{{ url }}" class="grid overflow-hidden rounded-[2rem] border bg-white sm:grid-cols-[220px_1fr]" style="border-color:var(--dt-border)"><img src="{{ featured_image_url }}" alt="{{ title }}" class="h-full min-h-48 w-full object-cover"><div class="p-6"><p class="text-xs font-bold uppercase tracking-[.16em]" style="color:var(--dt-primary)">{{ category }}</p><h2 class="mt-2 text-2xl font-extrabold">{{ title }}</h2><p class="mt-3 text-sm" style="color:var(--dt-muted)">{{ excerpt }}</p>${archiveMeta ? `<div class="mt-4 flex flex-wrap gap-2 text-xs" style="color:var(--dt-muted)">${archiveMeta}</div>` : ''}</div></a>{{/entries}}</div>`;
    else if (profile.kind === 'projects') archiveCards = `<div data-cosmic-catalog="true" class="mx-auto grid max-w-7xl gap-8 px-6 py-12 md:grid-cols-2 lg:px-8">{{#entries}}<a href="{{ url }}" class="group"><div class="overflow-hidden rounded-[2rem]"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[16/10] w-full object-cover transition duration-500 group-hover:scale-[1.03]"></div><p class="mt-5 text-xs font-bold uppercase tracking-[.16em]" style="color:var(--dt-primary)">{{ category }}</p><h2 class="mt-2 text-3xl font-extrabold tracking-[-.04em]">{{ title }}</h2>${archiveMeta ? `<div class="mt-3 flex flex-wrap gap-2 text-xs" style="color:var(--dt-muted)">${archiveMeta}</div>` : ''}</a>{{/entries}}</div>`;
    else if (profile.kind === 'services') archiveCards = `<div data-cosmic-catalog="true" class="mx-auto grid max-w-7xl gap-5 px-6 py-12 md:grid-cols-2 lg:grid-cols-3 lg:px-8">{{#entries}}<a href="{{ url }}" class="rounded-[2rem] border p-7 transition hover:-translate-y-1" style="border-color:var(--dt-border);background:var(--dt-bg)"><span class="inline-flex h-10 w-10 items-center justify-center rounded-full text-white" style="background:var(--dt-primary)">→</span><h2 class="mt-6 text-2xl font-extrabold">{{ title }}</h2><p class="mt-3 text-sm leading-6" style="color:var(--dt-muted)">{{ excerpt }}</p>${archiveMeta ? `<div class="mt-5 flex flex-wrap gap-2 text-xs" style="color:var(--dt-muted)">${archiveMeta}</div>` : ''}</a>{{/entries}}</div>`;

    const single = `<article data-cosmic-dynamic-theme="${preset.id}" data-cosmic-content-kind="${profile.kind}" class="text-slate-950" style="${vars}background:var(--dt-bg)"><header data-cosmic-mini-hero="true" data-cosmic-content-kind="${contentKind}" data-cosmic-surface="${heroMode}" class="border-b px-6 py-14 lg:px-8 lg:py-20" style="${heroShell}"><div class="mx-auto ${style.heroWrap} ${style.hero}"><p class="text-xs font-extrabold uppercase tracking-[.24em]" style="${heroAccent}">${profile.eyebrow} · {{ category }}</p><h1 class="mt-5 ${style.title} font-extrabold tracking-[-.05em]">{{ title }}</h1><p class="mt-6 max-w-3xl text-lg leading-8 ${style.hero === 'text-center' ? 'mx-auto' : ''}" style="${heroMuted}">{{ excerpt }}</p><div class="mt-5 text-sm" style="${heroMuted}">{{ tags }}</div></div></header><section data-cosmic-post-content="true" class="px-6 py-12 lg:px-8 lg:py-16">${singleBody}</section>${genericPostSparksMarkup(type, 'single')}</article>`;

    const archive = `<section data-cosmic-dynamic-theme="${preset.id}" data-cosmic-content-kind="${profile.kind}" class="text-slate-950" style="${vars}background:var(--dt-bg)"><header data-cosmic-mini-hero="true" data-cosmic-content-kind="${contentKind}" data-cosmic-surface="${heroMode}" class="border-b px-6 py-14 lg:px-8" style="${heroShell}"><div class="mx-auto max-w-7xl"><p class="text-xs font-extrabold uppercase tracking-[.24em]" style="${heroAccent}">${typeName}</p><h1 class="mt-3 ${preset.id === 'bold' ? 'text-6xl lg:text-8xl' : 'text-4xl sm:text-5xl'} font-extrabold tracking-[-.045em]">${profile.archiveTitle}</h1><p class="mt-4 max-w-2xl text-lg" style="${heroMuted}">${profile.archiveIntro}</p></div></header>${archiveCards}${genericPostSparksMarkup(type, 'archive')}</section>`;

    return { ...preset, single, archive, schemaAware: true, miniHeroSurface: heroMode };
};


const dynamicThemeIdFromMarkup = (markup = '') => {
    const match = String(markup || '').match(/data-cosmic-dynamic-theme=["']([^"']+)["']/i);
    return match?.[1] || null;
};

const dynamicThemePresets = [
    {
        id: 'premium', name: 'Premium Default', badge: 'Recommended', description: 'Cinematic mini hero, editorial content and polished post sparks.',
        single: `<article data-cosmic-dynamic-theme="premium" class="bg-white text-slate-950" style="--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-accent:var(--a,var(--cosmic-accent,#10b981));--dt-surface:var(--s,#f1f5f9);--dt-muted:var(--m,#64748b);--dt-border:var(--b,#e2e8f0);--dt-bg:var(--bg,#ffffff)"><header data-cosmic-mini-hero="true" class="border-b px-6 py-14 lg:px-8 lg:py-20" style="border-color:var(--dt-border);background:color-mix(in srgb,var(--dt-primary) 7%,var(--dt-bg))"><div class="mx-auto max-w-5xl text-center"><p class="text-xs font-extrabold uppercase tracking-[.24em]" style="color:var(--dt-primary)">{{ category }}</p><h1 class="mt-5 text-4xl font-extrabold tracking-[-.05em] sm:text-6xl lg:text-7xl">{{ title }}</h1><p class="mx-auto mt-6 max-w-3xl text-lg leading-8" style="color:var(--dt-muted)">{{ excerpt }}</p><div class="mt-5 text-sm" style="color:var(--dt-muted)">{{ tags }}</div></div></header><section data-cosmic-post-content="true" class="mx-auto max-w-6xl px-6 py-12 lg:px-8 lg:py-16"><div class="overflow-hidden rounded-[2rem] shadow-xl"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[16/9] w-full object-cover"></div><div class="mx-auto mt-12 max-w-3xl text-lg leading-8">{{ content }}</div><div class="mx-auto mt-12 max-w-5xl">{{ gallery }}</div></section><section data-cosmic-post-spark="true" class="border-y px-6 py-10 lg:px-8" style="border-color:var(--dt-border);background:color-mix(in srgb,var(--dt-primary) 5%,var(--dt-bg))"><div class="mx-auto flex max-w-5xl flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-extrabold uppercase tracking-[.18em]" style="color:var(--dt-primary)">Post spark</p><p class="mt-2 text-sm" style="color:var(--dt-muted)">Published {{ published_at }}</p></div><div class="text-sm">{{ tags }}</div></div></section></article>`,
        archive: `<section data-cosmic-dynamic-theme="premium" class="bg-white text-slate-950" style="--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-accent:var(--a,var(--cosmic-accent,#10b981));--dt-surface:var(--s,#f1f5f9);--dt-muted:var(--m,#64748b);--dt-border:var(--b,#e2e8f0);--dt-bg:var(--bg,#ffffff)"><header data-cosmic-mini-hero="true" class="border-b px-6 py-14 lg:px-8" style="border-color:var(--dt-border);background:color-mix(in srgb,var(--dt-primary) 7%,var(--dt-bg))"><div class="mx-auto max-w-7xl"><p class="text-xs font-extrabold uppercase tracking-[.24em]" style="color:var(--dt-primary)">Archive</p><h1 class="mt-3 text-4xl font-extrabold tracking-[-.045em] sm:text-5xl">Latest stories & updates</h1><p class="mt-4 max-w-2xl" style="color:var(--dt-muted)">Explore recent entries, highlights and fresh updates.</p></div></header><div data-cosmic-catalog="true" class="mx-auto grid max-w-7xl gap-7 px-6 py-12 md:grid-cols-2 lg:grid-cols-3 lg:px-8">{{#entries}}<article class="group overflow-hidden rounded-[1.75rem] border bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl" style="border-color:var(--dt-border)"><a href="{{ url }}" class="block"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[4/3] w-full object-cover transition duration-500 group-hover:scale-[1.03]"><div class="p-6"><p class="text-xs font-bold uppercase tracking-[.16em]" style="color:var(--dt-primary)">{{ category }}</p><h2 class="mt-3 text-2xl font-extrabold tracking-[-.03em]">{{ title }}</h2><p class="mt-3 line-clamp-3 text-sm leading-6" style="color:var(--dt-muted)">{{ excerpt }}</p><p class="mt-5 text-sm font-bold" style="color:var(--dt-primary)">Read entry →</p></div></a></article>{{/entries}}</div><section data-cosmic-post-spark="true" class="border-t px-6 py-10 lg:px-8" style="border-color:var(--dt-border);background:color-mix(in srgb,var(--dt-primary) 5%,var(--dt-bg))"><div class="mx-auto max-w-7xl"><p class="text-xs font-extrabold uppercase tracking-[.18em]" style="color:var(--dt-primary)">Discover more</p><p class="mt-2 max-w-2xl text-sm" style="color:var(--dt-muted)">Fresh content is added here automatically as new entries are published.</p></div></section></section>`,
    },
    {
        id: 'editorial', name: 'Editorial', description: 'Magazine typography with a refined story-first rhythm.',
        single: `<article data-cosmic-dynamic-theme="editorial" class="bg-[#fbfaf7] text-slate-950" style="--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-muted:var(--m,#64748b);--dt-border:var(--b,#d6d3d1);--dt-bg:var(--bg,#fbfaf7)"><header data-cosmic-mini-hero="true" class="mx-auto grid max-w-6xl gap-8 px-6 py-16 lg:grid-cols-[1fr_240px] lg:items-end lg:py-24"><div><p class="text-xs font-bold uppercase tracking-[.26em]" style="color:var(--dt-primary)">{{ category }}</p><h1 class="mt-5 max-w-4xl text-5xl font-extrabold leading-[.98] tracking-[-.055em] lg:text-7xl">{{ title }}</h1><p class="mt-7 max-w-2xl text-xl leading-8" style="color:var(--dt-muted)">{{ excerpt }}</p></div><div class="border-l pl-5 text-sm leading-7" style="border-color:var(--dt-border);color:var(--dt-muted)">{{ tags }}<div class="mt-3">{{ published_at }}</div></div></header><section data-cosmic-post-content="true" class="mx-auto max-w-6xl px-6 pb-16"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[16/8] w-full rounded-3xl object-cover"><div class="mx-auto mt-14 max-w-3xl text-lg leading-9">{{ content }}</div><div class="mt-12">{{ gallery }}</div></section><section data-cosmic-post-spark="true" class="border-y px-6 py-12" style="border-color:var(--dt-border)"><div class="mx-auto max-w-3xl"><p class="font-serif text-3xl font-bold tracking-tight">A story worth revisiting.</p><div class="mt-5 text-sm" style="color:var(--dt-muted)">{{ tags }}</div></div></section></article>`,
        archive: `<section data-cosmic-dynamic-theme="editorial" class="bg-[#fbfaf7] text-slate-950" style="--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-muted:var(--m,#64748b);--dt-border:var(--b,#d6d3d1)"><header data-cosmic-mini-hero="true" class="mx-auto max-w-7xl px-6 py-16"><p class="text-xs font-bold uppercase tracking-[.26em]" style="color:var(--dt-primary)">Journal</p><h1 class="mt-3 text-5xl font-extrabold tracking-[-.055em]">Latest stories</h1><div class="mt-8 border-b" style="border-color:var(--dt-border)"></div></header><div data-cosmic-catalog="true" class="mx-auto max-w-7xl px-6 pb-16">{{#entries}}<a href="{{ url }}" class="grid gap-6 border-b py-8 md:grid-cols-[220px_1fr_auto] md:items-center" style="border-color:var(--dt-border)"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[4/3] w-full rounded-2xl object-cover"><div><p class="text-xs font-bold uppercase tracking-wider" style="color:var(--dt-primary)">{{ category }}</p><h2 class="mt-2 text-3xl font-extrabold tracking-[-.035em]">{{ title }}</h2><p class="mt-3 max-w-2xl text-sm leading-6" style="color:var(--dt-muted)">{{ excerpt }}</p></div><span class="text-2xl" style="color:var(--dt-primary)">↗</span></a>{{/entries}}</div><section data-cosmic-post-spark="true" class="px-6 pb-16"><div class="mx-auto max-w-7xl rounded-3xl border p-8" style="border-color:var(--dt-border)"><p class="text-xs font-bold uppercase tracking-[.18em]" style="color:var(--dt-primary)">From the archive</p><p class="mt-3 max-w-2xl text-sm" style="color:var(--dt-muted)">A living collection that grows with every published entry.</p></div></section></section>`,
    },
    {
        id: 'modern', name: 'Modern', description: 'Soft surfaces, bold hierarchy and modular post sparks.',
        single: `<article data-cosmic-dynamic-theme="modern" class="bg-slate-50 text-slate-950" style="--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-muted:var(--m,#64748b);--dt-border:var(--b,#e2e8f0);--dt-bg:var(--bg,#f8fafc)"><header data-cosmic-mini-hero="true" class="mx-auto max-w-7xl px-6 pt-12 lg:px-8"><div class="rounded-[2rem] px-7 py-12 text-white lg:px-12 lg:py-16" style="background:var(--dt-primary)"><p class="text-xs font-bold uppercase tracking-[.2em] opacity-80">{{ category }}</p><h1 class="mt-4 max-w-5xl text-4xl font-extrabold tracking-[-.045em] sm:text-6xl">{{ title }}</h1><p class="mt-6 max-w-3xl text-lg leading-8 opacity-80">{{ excerpt }}</p></div></header><section data-cosmic-post-content="true" class="mx-auto max-w-7xl px-6 pb-14 lg:px-8"><img src="{{ featured_image_url }}" alt="{{ title }}" class="relative -mt-5 mx-auto aspect-[16/8] w-[92%] rounded-[1.75rem] object-cover shadow-2xl"><div class="mx-auto mt-12 grid max-w-5xl gap-10 lg:grid-cols-[1fr_220px]"><div class="text-lg leading-8">{{ content }}<div class="mt-10">{{ gallery }}</div></div><aside class="h-fit rounded-2xl border bg-white p-5 shadow-sm" style="border-color:var(--dt-border)"><div class="text-sm" style="color:var(--dt-muted)">{{ tags }}</div><div class="mt-4 text-xs" style="color:var(--dt-muted)">{{ published_at }}</div></aside></div></section><section data-cosmic-post-spark="true" class="px-6 pb-16 lg:px-8"><div class="mx-auto grid max-w-5xl gap-4 sm:grid-cols-2"><div class="rounded-3xl border bg-white p-6" style="border-color:var(--dt-border)"><p class="text-xs font-bold uppercase tracking-wider" style="color:var(--dt-primary)">Post spark</p><p class="mt-3 text-sm" style="color:var(--dt-muted)">A flexible space for supporting metadata and content context.</p></div><div class="rounded-3xl p-6 text-white" style="background:var(--dt-primary)"><p class="text-sm font-bold">{{ title }}</p><div class="mt-3 text-xs opacity-80">{{ category }}</div></div></div></section></article>`,
        archive: `<section data-cosmic-dynamic-theme="modern" class="bg-slate-50 text-slate-950" style="--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-muted:var(--m,#64748b);--dt-border:var(--b,#e2e8f0)"><header data-cosmic-mini-hero="true" class="mx-auto max-w-7xl px-6 py-14 lg:px-8"><div class="rounded-[2rem] px-8 py-12 text-white" style="background:var(--dt-primary)"><p class="text-xs font-bold uppercase tracking-[.2em] opacity-75">Archive</p><h1 class="mt-3 text-4xl font-extrabold tracking-[-.045em] sm:text-5xl">Explore latest</h1></div></header><div data-cosmic-catalog="true" class="mx-auto grid max-w-7xl gap-6 px-6 pb-14 md:grid-cols-2 lg:px-8">{{#entries}}<a href="{{ url }}" class="group grid overflow-hidden rounded-3xl border bg-white shadow-sm sm:grid-cols-[180px_1fr]" style="border-color:var(--dt-border)"><img src="{{ featured_image_url }}" alt="{{ title }}" class="h-full min-h-48 w-full object-cover"><div class="p-6"><p class="text-xs font-bold uppercase tracking-wider" style="color:var(--dt-primary)">{{ category }}</p><h2 class="mt-2 text-2xl font-extrabold">{{ title }}</h2><p class="mt-3 text-sm leading-6" style="color:var(--dt-muted)">{{ excerpt }}</p></div></a>{{/entries}}</div><section data-cosmic-post-spark="true" class="mx-auto max-w-7xl px-6 pb-16 lg:px-8"><div class="rounded-3xl border bg-white p-7" style="border-color:var(--dt-border)"><p class="text-sm font-bold" style="color:var(--dt-primary)">Catalog spark</p><p class="mt-2 text-sm" style="color:var(--dt-muted)">Designed to stay visually connected to the active website theme.</p></div></section></section>`,
    },
    {
        id: 'minimal', name: 'Minimal', description: 'Quiet, spacious and typography-first with restrained accents.',
        single: `<article data-cosmic-dynamic-theme="minimal" class="bg-white text-slate-950" style="--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-muted:var(--m,#64748b);--dt-border:var(--b,#e2e8f0)"><header data-cosmic-mini-hero="true" class="mx-auto max-w-4xl px-6 py-16 text-center"><p class="text-xs font-semibold uppercase tracking-[.28em]" style="color:var(--dt-primary)">{{ category }}</p><h1 class="mt-6 text-4xl font-semibold tracking-[-.05em] sm:text-6xl">{{ title }}</h1><p class="mx-auto mt-6 max-w-2xl text-lg leading-8" style="color:var(--dt-muted)">{{ excerpt }}</p></header><section data-cosmic-post-content="true" class="mx-auto max-w-5xl px-6 pb-16"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[16/9] w-full rounded-2xl object-cover"><div class="mx-auto mt-12 max-w-2xl text-lg leading-9">{{ content }}</div><div class="mx-auto mt-12 max-w-4xl">{{ gallery }}</div></section><section data-cosmic-post-spark="true" class="border-t px-6 py-10" style="border-color:var(--dt-border)"><div class="mx-auto flex max-w-4xl items-center justify-between gap-6 text-sm" style="color:var(--dt-muted)"><span>{{ published_at }}</span><span>{{ tags }}</span></div></section></article>`,
        archive: `<section data-cosmic-dynamic-theme="minimal" class="bg-white text-slate-950" style="--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-muted:var(--m,#64748b);--dt-border:var(--b,#e2e8f0)"><header data-cosmic-mini-hero="true" class="mx-auto max-w-5xl px-6 py-16 text-center"><p class="text-xs font-semibold uppercase tracking-[.28em]" style="color:var(--dt-primary)">Archive</p><h1 class="mt-4 text-4xl font-semibold tracking-[-.05em] sm:text-5xl">Browse entries</h1></header><div data-cosmic-catalog="true" class="mx-auto max-w-5xl px-6 pb-14">{{#entries}}<a href="{{ url }}" class="grid gap-5 border-t py-7 sm:grid-cols-[120px_1fr_auto] sm:items-center" style="border-color:var(--dt-border)"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-square w-full rounded-xl object-cover"><div><p class="text-xs font-semibold uppercase tracking-wider" style="color:var(--dt-primary)">{{ category }}</p><h2 class="mt-2 text-xl font-semibold">{{ title }}</h2><p class="mt-2 text-sm" style="color:var(--dt-muted)">{{ excerpt }}</p></div><span style="color:var(--dt-primary)">→</span></a>{{/entries}}</div><section data-cosmic-post-spark="true" class="border-t px-6 py-10" style="border-color:var(--dt-border)"><p class="mx-auto max-w-5xl text-sm" style="color:var(--dt-muted)">A clean archive that automatically expands with your published content.</p></section></section>`,
    },
    {
        id: 'magazine', name: 'Magazine', description: 'Image-forward cards, editorial rhythm and layered highlights.',
        single: `<article data-cosmic-dynamic-theme="magazine" class="bg-white text-slate-950" style="--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-muted:var(--m,#64748b);--dt-border:var(--b,#e2e8f0)"><header data-cosmic-mini-hero="true" class="mx-auto max-w-7xl px-6 py-14 lg:px-8"><div class="grid gap-8 lg:grid-cols-[1.05fr_.95fr] lg:items-center"><div><p class="text-xs font-black uppercase tracking-[.22em]" style="color:var(--dt-primary)">{{ category }}</p><h1 class="mt-4 text-5xl font-black leading-[.95] tracking-[-.055em] sm:text-7xl">{{ title }}</h1><p class="mt-6 max-w-xl text-lg leading-8" style="color:var(--dt-muted)">{{ excerpt }}</p></div><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[4/3] w-full rounded-[2rem] object-cover"></div></header><section data-cosmic-post-content="true" class="mx-auto max-w-7xl px-6 pb-16 lg:px-8"><div class="grid gap-10 lg:grid-cols-[220px_1fr]"><aside class="text-sm" style="color:var(--dt-muted)">{{ tags }}<div class="mt-4">{{ published_at }}</div></aside><div class="max-w-3xl text-lg leading-9">{{ content }}<div class="mt-12">{{ gallery }}</div></div></div></section><section data-cosmic-post-spark="true" class="px-6 pb-16 lg:px-8"><div class="mx-auto max-w-7xl rounded-[2rem] p-8 text-white" style="background:var(--dt-primary)"><p class="text-xs font-black uppercase tracking-[.2em] opacity-75">Featured spark</p><p class="mt-3 max-w-3xl text-2xl font-bold">{{ title }}</p></div></section></article>`,
        archive: `<section data-cosmic-dynamic-theme="magazine" class="bg-white text-slate-950" style="--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-muted:var(--m,#64748b);--dt-border:var(--b,#e2e8f0)"><header data-cosmic-mini-hero="true" class="mx-auto max-w-7xl px-6 py-16 lg:px-8"><div class="flex flex-col gap-5 border-b pb-8 sm:flex-row sm:items-end sm:justify-between" style="border-color:var(--dt-border)"><div><p class="text-xs font-black uppercase tracking-[.22em]" style="color:var(--dt-primary)">Magazine</p><h1 class="mt-3 text-5xl font-black tracking-[-.055em]">Fresh reads</h1></div><p class="max-w-md text-sm" style="color:var(--dt-muted)">An image-forward catalog of your latest published content.</p></div></header><div data-cosmic-catalog="true" class="mx-auto grid max-w-7xl gap-8 px-6 pb-14 md:grid-cols-2 lg:px-8">{{#entries}}<a href="{{ url }}" class="group"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[16/10] w-full rounded-[1.75rem] object-cover"><p class="mt-5 text-xs font-black uppercase tracking-wider" style="color:var(--dt-primary)">{{ category }}</p><h2 class="mt-2 text-3xl font-black tracking-[-.04em]">{{ title }}</h2><p class="mt-3 text-sm leading-6" style="color:var(--dt-muted)">{{ excerpt }}</p></a>{{/entries}}</div><section data-cosmic-post-spark="true" class="mx-auto max-w-7xl px-6 pb-16 lg:px-8"><div class="grid gap-4 sm:grid-cols-3"><div class="rounded-2xl border p-5 sm:col-span-2" style="border-color:var(--dt-border)"><p class="text-sm font-bold">Catalog spark</p><p class="mt-2 text-sm" style="color:var(--dt-muted)">Built for visual storytelling and ongoing publishing.</p></div><div class="rounded-2xl p-5 text-white" style="background:var(--dt-primary)"><p class="text-sm font-bold">Theme-aware</p></div></div></section></section>`,
    },
    {
        id: 'bold', name: 'Bold', description: 'High-impact contrast, oversized type and confident branded accents.',
        single: `<article data-cosmic-dynamic-theme="bold" class="bg-white text-slate-950" style="--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-muted:var(--m,#64748b);--dt-border:var(--b,#e2e8f0)"><header data-cosmic-mini-hero="true" class="px-6 py-16 text-white lg:px-8 lg:py-24" style="background:var(--dt-primary)"><div class="mx-auto max-w-7xl"><p class="text-xs font-black uppercase tracking-[.26em] opacity-75">{{ category }}</p><h1 class="mt-5 max-w-6xl text-5xl font-black uppercase leading-[.9] tracking-[-.06em] sm:text-7xl lg:text-8xl">{{ title }}</h1><p class="mt-7 max-w-2xl text-lg leading-8 opacity-80">{{ excerpt }}</p></div></header><section data-cosmic-post-content="true" class="mx-auto max-w-7xl px-6 py-14 lg:px-8"><div class="grid gap-10 lg:grid-cols-[1fr_320px]"><div><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[16/9] w-full rounded-3xl object-cover"><div class="mt-12 max-w-3xl text-lg leading-9">{{ content }}</div><div class="mt-12">{{ gallery }}</div></div><aside class="h-fit rounded-3xl border p-6" style="border-color:var(--dt-border)"><p class="text-xs font-black uppercase tracking-[.2em]" style="color:var(--dt-primary)">Post details</p><div class="mt-5 text-sm" style="color:var(--dt-muted)">{{ tags }}</div><div class="mt-4 text-sm" style="color:var(--dt-muted)">{{ published_at }}</div></aside></div></section><section data-cosmic-post-spark="true" class="border-t px-6 py-12 lg:px-8" style="border-color:var(--dt-border)"><div class="mx-auto max-w-7xl text-3xl font-black uppercase tracking-[-.03em]">Keep exploring <span style="color:var(--dt-primary)">→</span></div></section></article>`,
        archive: `<section data-cosmic-dynamic-theme="bold" class="bg-white text-slate-950" style="--dt-primary:var(--p,var(--cosmic-primary,#047857));--dt-muted:var(--m,#64748b);--dt-border:var(--b,#e2e8f0)"><header data-cosmic-mini-hero="true" class="px-6 py-16 text-white lg:px-8" style="background:var(--dt-primary)"><div class="mx-auto max-w-7xl"><p class="text-xs font-black uppercase tracking-[.26em] opacity-75">Archive</p><h1 class="mt-3 text-5xl font-black uppercase tracking-[-.055em] sm:text-7xl">All entries</h1></div></header><div data-cosmic-catalog="true" class="mx-auto max-w-7xl px-6 py-12 lg:px-8">{{#entries}}<a href="{{ url }}" class="grid gap-5 border-b py-7 md:grid-cols-[180px_1fr_auto] md:items-center" style="border-color:var(--dt-border)"><img src="{{ featured_image_url }}" alt="{{ title }}" class="aspect-[4/3] w-full rounded-2xl object-cover"><div><p class="text-xs font-black uppercase tracking-wider" style="color:var(--dt-primary)">{{ category }}</p><h2 class="mt-2 text-3xl font-black uppercase tracking-[-.035em]">{{ title }}</h2><p class="mt-3 max-w-2xl text-sm" style="color:var(--dt-muted)">{{ excerpt }}</p></div><span class="text-3xl font-black" style="color:var(--dt-primary)">→</span></a>{{/entries}}</div><section data-cosmic-post-spark="true" class="px-6 pb-16 lg:px-8"><div class="mx-auto max-w-7xl rounded-3xl p-8 text-white" style="background:var(--dt-primary)"><p class="text-xs font-black uppercase tracking-[.2em] opacity-75">Post spark</p><p class="mt-3 text-xl font-bold">A bold catalog that always inherits the website primary theme.</p></div></section></section>`,
    },
];


const escapeHtml = (value = '') => String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));

const templatePreviewDocument = (markup = '', type, mode, entry = null, interactive = false, website = null, miniBannerImageUrl = '') => {
    const custom = entry?.custom_fields || {};
    const samples = {
        title: entry?.title || (type?.singular_name ? `Sample ${type.singular_name}` : 'Sample entry title'),
        slug: entry?.slug || 'sample-entry',
        excerpt: entry?.excerpt || 'A polished preview of how real dynamic content will flow through this Cosmic AI-designed template.',
        content: entry?.content || '<p>This is sample rich content for layout preview. Your saved entries remain separate from the template design.</p><h2>Dynamic content, premium presentation</h2><p>When the template is used, Cosmic CMS resolves these bindings from the selected entry.</p>',
        category: entry?.category || 'Featured',
        tags: `<span data-cosmic-tags="true" class="inline-flex flex-wrap gap-2">${(entry?.tags?.length ? entry.tags : ['Design','Updates','Cosmic']).map((tag) => `<span class="inline-flex rounded-full border border-slate-200 px-3 py-1 text-sm">${escapeHtml(tag)}</span>`).join('')}</span>`,
        featured_image_url: entry?.featured_image_url || 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=1600&q=80',
        gallery: `<div data-cosmic-gallery="true" class="grid gap-4 sm:grid-cols-2">${(entry?.gallery?.length ? entry.gallery : [{url:'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=80'},{url:'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=900&q=80'}]).map((image) => `<figure class="overflow-hidden rounded-2xl bg-slate-100"><img src="${escapeHtml(image?.url || image || '')}" alt="${escapeHtml(image?.alt_text || '')}" class="h-full w-full object-cover"></figure>`).join('')}</div>`,
        published_at: entry?.published_at || 'August 12, 2026',
        updated_at: entry?.updated_at || 'August 12, 2026',
        url: '#',
    };
    (type?.schema || []).forEach((field) => {
        const raw = custom[field.key];
        let value = raw;
        if (field.type === 'boolean') value = raw ? 'Yes' : 'No';
        else if (field.type === 'gallery') value = Array.isArray(raw) ? raw.map((image) => image?.alt_text || image?.url || image).join(', ') : '';
        else if (field.type === 'repeater') value = Array.isArray(raw) ? `${raw.length} ${field.label || 'items'}` : `0 ${field.label || 'items'}`;
        else if (field.type === 'group') value = raw && typeof raw === 'object' ? Object.values(raw).filter(Boolean).join(' · ') : '';
        else if (Array.isArray(raw)) value = raw.join(', ');
        else if (raw && typeof raw === 'object') value = Object.values(raw).filter(Boolean).join(' · ');
        samples[`custom_fields.${field.key}`] = value || (field.type === 'number' ? '24' : field.type === 'date' ? 'August 12, 2026' : field.label || 'Sample value');
    });

    const editable = (key, html) => interactive ? `<span data-cosmic-edit-key="${escapeHtml(key)}" class="cosmic-edit-target">${html}</span>` : html;
    const tokenReplace = (fragment, allowEditable) => fragment.replace(/{{\s*([^{}#\/][^{}]*)\s*}}/g, (_, key) => {
        const clean = key.trim();
        const value = samples[clean] ?? clean;
        if (clean === 'content') return allowEditable ? editable(clean, `<div data-cosmic-richtext="true">${value}</div>`) : escapeHtml(String(value).replace(/<[^>]*>/g, ' '));
        if (clean === 'gallery' || clean === 'tags') return allowEditable ? editable(clean, value) : escapeHtml(String(value).replace(/<[^>]*>/g, ' '));
        return allowEditable ? editable(clean, escapeHtml(value)) : escapeHtml(value);
    });
    const renderBindings = (html) => interactive
        ? html.split(/(<[^>]+>)/g).map((part) => part.startsWith('<') ? tokenReplace(part, false) : tokenReplace(part, true)).join('')
        : tokenReplace(html, false);

    let body = markup || '<div class="mx-auto max-w-3xl px-6 py-20 text-center"><h1 class="text-4xl font-extrabold">Design with Cosmic AI</h1><p class="mt-4 text-slate-500">Describe the layout you want, then preview it here before saving.</p></div>';
    if (interactive) {
        body = body.replace(/<img([^>]*?)src=["']{{\s*featured_image_url\s*}}["']([^>]*)>/gi, `<img$1data-cosmic-edit-key="featured_image_url" src="${escapeHtml(samples.featured_image_url)}"$2>`);
        (type?.schema || []).filter((field) => field.type === 'image').forEach((field) => {
            const key = `custom_fields.${field.key}`;
            const url = custom[field.key] || 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=1200&q=80';
            const rx = new RegExp(`<img([^>]*?)src=["']{{\\s*${key.replace('.', '\\.') }\\s*}}["']([^>]*)>`, 'gi');
            body = body.replace(rx, `<img$1data-cosmic-edit-key="${key}" src="${escapeHtml(url)}"$2>`);
        });
    }
    if (mode === 'archive') {
        body = body.replace(/{{#entries}}([\s\S]*?){{\/entries}}/g, (_, inner) => [0, 1, 2].map((i) => renderBindings(inner).replace(/Sample entry title/g, `Sample entry ${i + 1}`)).join(''));
    }
    body = renderBindings(body);

    const themeSettings = website?.theme_settings || website?.themeSettings || {};
    const themeKey = String(website?.theme || website?.theme_id || themeSettings?.id || themeSettings?.primary || 'midnight').toLowerCase();
    const catalogPalette = colorFamilies?.[themeKey]?.palette || {};
    const explicitPalette = themeSettings?.palette || {};
    const previewPrimary = explicitPalette.primary || (String(themeSettings.primary || '').startsWith('#') ? themeSettings.primary : '') || website?.primary_color || website?.primaryColor || catalogPalette.primary || catalogPalette.background || '#243447';
    const previewAccent = explicitPalette.accent || (String(themeSettings.accent || '').startsWith('#') ? themeSettings.accent : '') || website?.accent_color || website?.accentColor || catalogPalette.accent || '#10b981';
    // Dynamic post templates intentionally keep the reading canvas light. Theme color is
    // reserved for the mini banner/accent so dark families such as Midnight do not
    // turn the article body into a dark page.
    const previewBackground = '#ffffff';
    const previewSurfaceColor = '#f8fafc';
    const previewText = '#0f172a';
    const previewMuted = explicitPalette.muted || themeSettings.muted || catalogPalette.muted || '#64748b';
    const previewBorder = explicitPalette.border || themeSettings.border || catalogPalette.border || '#e2e8f0';
    const previewThemeVars = `:root{--p:${previewPrimary};--cosmic-primary:${previewPrimary};--a:${previewAccent};--cosmic-accent:${previewAccent};--bg:${previewBackground};--s:${previewSurfaceColor};--t:${previewText};--m:${previewMuted};--b:${previewBorder};}`;

    if (interactive) {
        body = body.replace(/data-cosmic-mini-hero=["']true["']/i, 'data-cosmic-mini-hero="true" data-cosmic-template-edit="mini_banner"');
    }

    const rawPreviewPageStyle = String(website?.page_style || website?.published_page_style || 'balanced').toLowerCase();
    const previewPageStyle = ['balanced','clean','premium'].includes(rawPreviewPageStyle) ? rawPreviewPageStyle : 'balanced';
    const previewSurface = previewPageStyle === 'clean' ? 'clean' : 'primary';
    const resolvedBannerImage = String(miniBannerImageUrl || '').trim();
    if (resolvedBannerImage) {
        const safeBannerImage = escapeHtml(resolvedBannerImage);
        const overlay = previewSurface === 'clean'
            ? 'linear-gradient(rgba(255,255,255,.88),rgba(255,255,255,.88))'
            : `linear-gradient(color-mix(in srgb, ${previewPrimary} 78%, rgba(0,0,0,.35)),color-mix(in srgb, ${previewPrimary} 78%, rgba(0,0,0,.35)))`;
        body = body.replace(/(<[^>]+data-cosmic-mini-hero=["']true["'][^>]*)(>)/i, (match, start, end) => {
            const styleMatch = start.match(/style=["']([^"']*)["']/i);
            const bannerStyle = `background-image:${overlay},url('${safeBannerImage}')!important;background-size:cover!important;background-position:center!important;background-repeat:no-repeat!important;`;
            if (styleMatch) return `${start.replace(styleMatch[0], `style="${styleMatch[1]}${bannerStyle}"`)}${end}`;
            return `${start} style="${bannerStyle}"${end}`;
        });
    }

    const headerConfig = website?.global_header || website?.globalHeader || {};
    const footerConfig = website?.global_footer || website?.globalFooter || {};
    const logoUrl = headerConfig?.logo_image_url || website?.logo_url || '';
    const logoText = headerConfig?.logo_text || website?.name || 'Website';
    const menuItems = Array.isArray(headerConfig?.menu) && headerConfig.menu.length
        ? headerConfig.menu.slice(0, 7)
        : [{label:'Home',url:'#'},{label:'About',url:'#'},{label:'Contact',url:'#'}];
    const previewHeader = `<header id="cosmic-template-preview-header" data-cosmic-preview-site-header="true" class="cosmic-preview-site-header"><div class="cosmic-preview-shell cosmic-preview-header-shell"><a href="#" class="cosmic-preview-brand" aria-label="${escapeHtml(logoText)}">${logoUrl ? `<img src="${escapeHtml(logoUrl)}" alt="${escapeHtml(logoText)}">` : `<strong>${escapeHtml(logoText)}</strong>`}</a><nav aria-label="Primary navigation">${menuItems.map((item)=>`<a href="#">${escapeHtml(item?.label || 'Page')}</a>`).join('')}</nav><a href="#" class="cosmic-preview-cta">${escapeHtml(headerConfig?.cta_label || headerConfig?.button_text || 'Get Started')}</a></div></header>`;
    const footerLogoUrl = footerConfig?.logo_image_url || logoUrl;
    const previewFooter = `<footer id="cosmic-template-preview-footer" data-cosmic-preview-site-footer="true" class="cosmic-preview-site-footer"><div class="cosmic-preview-shell cosmic-preview-footer-shell"><div class="cosmic-preview-brand">${footerLogoUrl ? `<img src="${escapeHtml(footerLogoUrl)}" alt="${escapeHtml(logoText)}">` : `<strong>${escapeHtml(logoText)}</strong>`}</div><span>${escapeHtml(footerConfig?.copyright || `© ${new Date().getFullYear()} ${logoText}`)}</span></div></footer>`;

    const editorCss = interactive ? `
        [data-cosmic-generic-copy]{position:relative;cursor:pointer;border-radius:6px;outline:2px solid transparent;outline-offset:5px;transition:.16s}
        [data-cosmic-generic-copy]:hover{outline-color:#8b5cf6;background:rgba(139,92,246,.08)}
        [data-cosmic-generic-copy]::after{content:'✎';position:absolute;right:-28px;top:50%;transform:translateY(-50%) scale(.9);display:grid;place-items:center;width:22px;height:22px;border-radius:999px;background:#7c3aed;color:#fff;font:800 11px/1 Manrope,sans-serif;opacity:0;box-shadow:0 8px 20px rgba(15,23,42,.22);transition:.16s;z-index:50}
        [data-cosmic-generic-copy]:hover::after{opacity:1;transform:translateY(-50%) scale(1)}
        [data-cosmic-template-edit='mini_banner']{position:relative;outline:2px solid transparent;outline-offset:-2px;transition:.16s}
        [data-cosmic-template-edit='mini_banner']:hover{outline-color:rgba(124,58,237,.7)}
        [data-cosmic-template-edit='mini_banner']::after{content:'✎ Mini banner';position:absolute;right:22px;top:22px;padding:8px 11px;border-radius:999px;background:#111827;color:#fff;font:800 10px/1 Manrope,sans-serif;letter-spacing:.02em;opacity:0;transform:translateY(-4px);box-shadow:0 10px 30px rgba(15,23,42,.28);transition:.16s;z-index:80;pointer-events:none}
        [data-cosmic-template-edit='mini_banner']:hover::after{opacity:1;transform:none}
    ` : '';
    const editorJs = interactive ? `<script>
        document.addEventListener('click',function(e){
            var generic=e.target.closest('[data-cosmic-generic-copy]');
            if(generic){e.preventDefault();e.stopPropagation();parent.postMessage({type:'cosmic-template-copy-edit',key:generic.getAttribute('data-cosmic-generic-copy')},'*');return;}
            var mini=e.target.closest('[data-cosmic-template-edit="mini_banner"]');
            if(mini && !e.target.closest('a,button')){e.preventDefault();e.stopPropagation();parent.postMessage({type:'cosmic-template-copy-edit',key:'mini_banner'},'*');return;}
            var a=e.target.closest('a');if(a)e.preventDefault();
        },true);
    </script>` : '';
    return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet"><style>${previewThemeVars}html,body{font-family:Manrope,ui-sans-serif,system-ui,sans-serif;margin:0;background:#fff;color:#0f172a}body{min-height:100vh}.cosmic-dynamic-single[data-cosmic-page-style-surface='primary'] [data-cosmic-mini-hero='true']{background:var(--p)!important;background-color:var(--p)!important;background-image:none!important;color:#fff!important;border-color:transparent!important}.cosmic-dynamic-single[data-cosmic-page-style-surface='primary'] [data-cosmic-mini-hero='true'] :is(h1,h2,h3,h4,p,span,a,div){color:#fff!important;-webkit-text-fill-color:currentColor!important}.cosmic-dynamic-single[data-cosmic-page-style-surface='clean'] [data-cosmic-mini-hero='true']{background:var(--bg,#fff)!important;background-image:none!important;color:var(--t,#0f172a)!important}.cosmic-dynamic-single[data-cosmic-page-style-surface='clean'] [data-cosmic-mini-hero='true'] :is(h1,h2,h3){color:var(--t,#0f172a)!important}.cosmic-dynamic-single[data-cosmic-page-style-surface='clean'] [data-cosmic-mini-hero='true'] p{color:var(--m,#64748b)!important}.cosmic-dynamic-single[data-cosmic-page-style-surface='clean'] :is(a,button)[class*='bg-[#'],.cosmic-dynamic-single[data-cosmic-page-style-surface='clean'] :is(a,button)[class*='bg-primary'],.cosmic-dynamic-single[data-cosmic-page-style-surface='clean'] :is(a,button)[class*='bg-[var(--p)]'],.cosmic-dynamic-single[data-cosmic-page-style-surface='clean'] :is(a,button).cosmic-brand-bg{background:var(--p)!important;background-color:var(--p)!important;border-color:var(--p)!important;color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important}[data-cosmic-dynamic-single]{width:100%;overflow:hidden}[data-cosmic-dynamic-single] [data-cosmic-richtext]{font-size:1.0625rem;line-height:1.88;text-wrap:pretty}[data-cosmic-dynamic-single] [data-cosmic-richtext] p{margin:0 0 1.35em}[data-cosmic-dynamic-single] [data-cosmic-richtext] h2{font-size:clamp(1.75rem,3vw,2.35rem);line-height:1.15;margin:1.8em 0 .65em;letter-spacing:-.035em}[data-cosmic-gallery] figure{aspect-ratio:4/3}[data-cosmic-gallery] img{width:100%;height:100%;object-fit:cover}.cosmic-preview-site-header,.cosmic-preview-site-footer{background:#fff;color:#0f172a;border-color:#e2e8f0}.cosmic-preview-site-header{position:relative;z-index:90;border-bottom:1px solid #e2e8f0;box-shadow:0 1px 0 rgba(15,23,42,.02)}.cosmic-preview-site-footer{border-top:1px solid #e2e8f0;margin-top:0}.cosmic-preview-shell{width:100%;max-width:1440px;margin:0 auto;display:flex;align-items:center;gap:28px;padding-left:clamp(22px,5.5vw,86px);padding-right:clamp(22px,5.5vw,86px)}.cosmic-preview-header-shell{min-height:88px;justify-content:flex-start}.cosmic-preview-brand{display:flex;align-items:center;flex:0 0 auto;min-width:150px;text-decoration:none;color:#0f172a}.cosmic-preview-brand img{display:block;max-width:210px;max-height:56px;width:auto;height:auto;object-fit:contain}.cosmic-preview-brand strong{font-size:22px;font-weight:800;letter-spacing:-.035em}.cosmic-preview-site-header nav{display:flex;align-items:center;justify-content:flex-end;gap:clamp(16px,1.9vw,30px);margin-left:auto;flex:0 1 auto}.cosmic-preview-site-header nav a{color:#64748b;text-decoration:none;font-size:15px;font-weight:500;line-height:1.4;white-space:nowrap;transition:color .16s ease}.cosmic-preview-site-header nav a:hover{color:#0f172a}.cosmic-preview-cta{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;margin-left:4px;border-radius:999px;background:var(--p);color:#fff!important;text-decoration:none;padding:12px 28px;font-size:14px;font-weight:600;line-height:1.2;box-shadow:0 12px 28px rgba(15,23,42,.10)}.cosmic-preview-footer-shell{justify-content:space-between;color:#64748b;font-size:12px;min-height:96px}.cosmic-dynamic-single>[data-cosmic-mini-hero='true']{border-radius:0!important}.cosmic-dynamic-single[data-cosmic-mini-banner-image='true'][data-cosmic-page-style-surface='primary'] [data-cosmic-mini-hero='true']{background-color:var(--p)!important;background-image:linear-gradient(color-mix(in srgb,var(--p) 76%,rgba(0,0,0,.34)),color-mix(in srgb,var(--p) 76%,rgba(0,0,0,.34))),var(--cosmic-mini-banner-image)!important;background-size:cover!important;background-position:center!important;background-repeat:no-repeat!important;color:#fff!important}.cosmic-dynamic-single[data-cosmic-mini-banner-image='true'][data-cosmic-page-style-surface='clean'] [data-cosmic-mini-hero='true']{background-color:#fff!important;background-image:linear-gradient(rgba(255,255,255,.90),rgba(255,255,255,.90)),var(--cosmic-mini-banner-image)!important;background-size:cover!important;background-position:center!important;background-repeat:no-repeat!important;color:var(--t,#0f172a)!important}@media(max-width:900px){.cosmic-preview-site-header nav{display:none}.cosmic-preview-header-shell{min-height:76px}.cosmic-preview-brand img{max-height:46px}.cosmic-preview-cta{padding:11px 18px}}${editorCss}</style></head><body>${previewHeader}<div data-cosmic-dynamic-single="true" data-cosmic-page-style="${escapeHtml(previewPageStyle)}" data-cosmic-page-style-surface="${previewSurface}" data-cosmic-mini-banner-image="${resolvedBannerImage ? 'true' : 'false'}"${resolvedBannerImage ? ` style="--cosmic-mini-banner-image:url('${escapeHtml(resolvedBannerImage)}')"` : ''} class="cosmic-dynamic-single">${body}</div>${previewFooter}${editorJs}</body></html>`;
};

const emptyEntry = (type) => ({
    id: null,
    title: '',
    slug: '',
    excerpt: '',
    content: '',
    status: 'draft',
    category: '',
    tags: [],
    featured_image_url: '',
    gallery: [],
    custom_fields: Object.fromEntries((type?.schema || []).map((field) => [field.key, fieldDefaultValue(field)])),
    seo_title: '',
    seo_description: '',
    og_image_url: '',
    is_featured: false,
});

const imageAccept = 'image/jpeg,image/png,image/webp,image/avif,image/gif,image/heic,image/heif';

async function uploadContentImage(websiteId, file, kind = 'custom') {
    if (!file) return '';

    const allowed = ['image/jpeg','image/png','image/gif','image/webp','image/avif','image/heic','image/heif'];
    if (file.size > 8 * 1024 * 1024) throw new Error('Image must be 8 MB or smaller.');
    if (file.type && !allowed.includes(file.type.toLowerCase())) throw new Error('Use JPG, PNG, WebP, GIF, AVIF, HEIC or HEIF images.');

    const getFreshCsrfToken = async () => {
        const response = await fetch('/session/csrf-token', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });
        const data = await response.json().catch(() => ({}));
        const token = data?.token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        if (!response.ok || !token) throw new Error('Your secure upload session could not be refreshed. Reload the page and try again.');
        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
        return token;
    };

    const sendUpload = async (token) => {
        const form = new FormData();
        form.append('image', file, file.name);
        form.append('kind', kind);
        return fetch(`/websites/${websiteId}/content/media`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: form,
        });
    };

    // Inertia can keep this modal alive across a Laravel session regeneration, leaving
    // the document meta token stale. Refresh the token before multipart uploads and
    // retry once if the session rotates during the request.
    let token = await getFreshCsrfToken();
    let response = await sendUpload(token);
    if (response.status === 419) {
        token = await getFreshCsrfToken();
        response = await sendUpload(token);
    }

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const validationMessage = Object.values(data?.errors || {}).flat?.()?.[0];
        const message = response.status === 419
            ? 'Your secure session changed while uploading. Reload the page and try again.'
            : (validationMessage || data?.message || 'Image upload failed.');
        throw new Error(message);
    }
    if (!data?.url) throw new Error('Upload completed without an image URL. Please try again.');
    return data.url;
}

function RichTextField({ value, onChange, placeholder }) {
    const editorRef = useRef(null);
    const command = (name, argument = null) => {
        editorRef.current?.focus();
        document.execCommand(name, false, argument);
        onChange(editorRef.current?.innerHTML || '');
    };
    return <div className="mt-1 overflow-hidden rounded-xl border border-white/10 bg-white/[0.045] focus-within:border-violet-400/50 focus-within:ring-2 focus-within:ring-violet-400/10">
        <div className="flex flex-wrap gap-1 border-b border-white/10 bg-black/10 p-2">
            {[['bold','B'],['italic','I'],['underline','U'],['insertUnorderedList','• List'],['insertOrderedList','1. List']].map(([cmd,label]) => <button key={cmd} type="button" onMouseDown={(e) => { e.preventDefault(); command(cmd); }} className="rounded-md border border-white/10 px-2 py-1 text-[11px] font-semibold text-slate-300 hover:bg-white/10 hover:text-white">{label}</button>)}
            <button type="button" onMouseDown={(e) => { e.preventDefault(); command('formatBlock', 'h2'); }} className="rounded-md border border-white/10 px-2 py-1 text-[11px] font-semibold text-slate-300 hover:bg-white/10">H2</button>
            <button type="button" onMouseDown={(e) => { e.preventDefault(); command('formatBlock', 'p'); }} className="rounded-md border border-white/10 px-2 py-1 text-[11px] font-semibold text-slate-300 hover:bg-white/10">P</button>
        </div>
        <div ref={editorRef} contentEditable suppressContentEditableWarning onInput={(e) => onChange(e.currentTarget.innerHTML)} dangerouslySetInnerHTML={{ __html: value || '' }} data-placeholder={placeholder || 'Write rich content…'} className="min-h-[150px] px-3 py-3 text-sm leading-7 text-white outline-none empty:before:text-slate-500 empty:before:content-[attr(data-placeholder)]" />
    </div>;
}

function ImageField({ websiteId, value, onChange, showAdvancedUrl = true, kind = 'custom' }) {
    const [uploading, setUploading] = useState(false);
    const upload = async (file) => {
        try { setUploading(true); const url = await uploadContentImage(websiteId, file, kind); if (url) onChange(url); }
        catch (error) { showCosmicNotification({ title: 'Could not upload image', message: error.message, tone: 'error' }); }
        finally { setUploading(false); }
    };
    return <div className="mt-1 rounded-xl border border-white/10 bg-white/[0.025] p-3">
        {value ? <img src={value} alt="" className="mb-3 aspect-[16/9] w-full rounded-lg object-cover" /> : <div className="mb-3 flex aspect-[16/7] items-center justify-center rounded-lg border border-dashed border-white/10 text-xs text-slate-500">No image selected</div>}
        <div className="flex flex-wrap gap-2"><label className="cursor-pointer rounded-lg bg-violet-500/15 px-3 py-2 text-xs font-semibold text-violet-200"><input type="file" accept={imageAccept} className="hidden" disabled={uploading} onChange={(e) => { upload(e.target.files?.[0]); e.target.value=''; }} />{uploading ? 'Uploading…' : 'Upload image'}</label>{value ? <button type="button" onClick={() => onChange('')} className="rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-300">Remove</button> : null}</div>
        {showAdvancedUrl ? <details className="mt-2"><summary className="cursor-pointer text-[11px] text-slate-500">Advanced: image URL</summary><input value={value || ''} onChange={(e) => onChange(e.target.value)} className="mt-2 w-full rounded-lg border border-white/10 bg-black/10 px-2.5 py-2 text-xs text-white" placeholder="https://..." /></details> : null}
    </div>;
}

function GalleryField({ websiteId, value, onChange }) {
    const images = Array.isArray(value) ? value : [];
    const [uploading, setUploading] = useState(false);
    const upload = async (files) => {
        const list = Array.from(files || []).slice(0, Math.max(0, 30 - images.length));
        if (!list.length) return;
        try { setUploading(true); const uploaded=[]; for (const file of list) { const url=await uploadContentImage(websiteId,file,'gallery'); if(url) uploaded.push({url,alt_text:''}); } onChange([...images,...uploaded]); }
        catch (error) { showCosmicNotification({ title:'Could not upload gallery', message:error.message, tone:'error' }); }
        finally { setUploading(false); }
    };
    const move = (index, direction) => { const next=[...images]; const target=index+direction; if(target<0||target>=next.length)return; [next[index],next[target]]=[next[target],next[index]]; onChange(next); };
    return <div className="mt-1 rounded-xl border border-white/10 bg-white/[0.025] p-3">
        <div className="flex items-center justify-between gap-3"><span className="text-xs text-slate-500">{images.length}/30 images</span><label className="cursor-pointer rounded-lg border border-white/10 px-3 py-2 text-xs font-semibold text-slate-200"><input type="file" multiple accept={imageAccept} className="hidden" disabled={uploading} onChange={(e)=>{upload(e.target.files);e.target.value='';}} />{uploading?'Uploading…':'+ Add images'}</label></div>
        {images.length ? <div className="mt-3 grid gap-2 sm:grid-cols-2">{images.map((image,index)=><div key={`${image?.url || image}-${index}`} className="overflow-hidden rounded-lg border border-white/10 bg-black/10"><img src={image?.url || image} alt={image?.alt_text || ''} className="aspect-[4/3] w-full object-cover"/><div className="p-2"><input value={image?.alt_text || ''} onChange={(e)=>onChange(images.map((item,i)=>i===index?{...(typeof item==='string'?{url:item}:item),alt_text:e.target.value}:item))} placeholder="Alt text" className="w-full rounded-md border border-white/10 bg-black/10 px-2 py-1.5 text-[11px] text-white"/><div className="mt-2 flex justify-between"><div className="flex gap-1"><button type="button" disabled={index===0} onClick={()=>move(index,-1)} className="rounded border border-white/10 px-2 py-1 text-[10px] text-slate-300 disabled:opacity-30">←</button><button type="button" disabled={index===images.length-1} onClick={()=>move(index,1)} className="rounded border border-white/10 px-2 py-1 text-[10px] text-slate-300 disabled:opacity-30">→</button></div><button type="button" onClick={()=>onChange(images.filter((_,i)=>i!==index))} className="text-[10px] font-semibold text-rose-300">Remove</button></div></div></div>)}</div> : <div className="mt-3 rounded-lg border border-dashed border-white/10 px-3 py-6 text-center text-xs text-slate-500">Upload one or more images.</div>}
    </div>;
}

const simpleNestedFieldTypes = [
    ['text','Text'],['textarea','Textarea'],['richtext','Rich text'],['image','Image'],['gallery','Gallery'],['select','Select'],['date','Date'],['datetime','Date & time'],['url','URL'],['number','Number'],['boolean','Boolean'],['relation','Relation'],
];

const fieldDefaultValue = (field) => {
    if (field?.type === 'boolean') return false;
    if (field?.type === 'gallery' || field?.type === 'repeater' || (field?.type === 'relation' && field?.multiple)) return [];
    if (field?.type === 'group') return Object.fromEntries((field.fields || []).map((child) => [child.key, fieldDefaultValue(child)]));
    return '';
};

function RelationField({ field, value, onChange, types }) {
    const target = (types || []).find((type) => Number(type.id) === Number(field.related_type_id));
    const entries = target?.entries || [];
    if (!field.related_type_id) return <div className="mt-1 rounded-xl border border-amber-400/20 bg-amber-400/5 px-3 py-3 text-xs text-amber-200">Choose a related content type in the field settings first.</div>;
    if (field.multiple) {
        const selected = Array.isArray(value) ? value.map(Number) : [];
        return <div className="mt-1 max-h-52 space-y-1 overflow-y-auto rounded-xl border border-white/10 bg-white/[0.025] p-2">{entries.length ? entries.map((entry) => <label key={entry.id} className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-slate-300 hover:bg-white/5"><input type="checkbox" checked={selected.includes(Number(entry.id))} onChange={(e) => onChange(e.target.checked ? [...selected, Number(entry.id)] : selected.filter((id) => id !== Number(entry.id)))} className="rounded border-white/20 bg-white/5"/><span className="min-w-0 flex-1 truncate">{entry.title}</span><span className="text-[10px] uppercase tracking-wide text-slate-600">{entry.status}</span></label>) : <p className="px-2 py-3 text-xs text-slate-500">No {target?.name || 'related'} entries yet.</p>}</div>;
    }
    return <select className="cosmic-content-select mt-1 w-full rounded-xl border border-white/10 bg-[#15151a] px-3 py-2 text-sm text-white outline-none focus:border-violet-400/50" value={value || ''} onChange={(e)=>onChange(e.target.value ? Number(e.target.value) : '')}><option value="">Select {target?.singular_name || 'entry'}…</option>{entries.map((entry)=><option key={entry.id} value={entry.id}>{entry.title}</option>)}</select>;
}

function GroupField({ field, value, onChange, websiteId, types }) {
    const group = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    return <div className="mt-1 space-y-3 rounded-2xl border border-white/10 bg-white/[0.02] p-4">{(field.fields || []).length ? (field.fields || []).map((child)=><div key={child.key}><label className="text-xs font-semibold text-slate-300">{child.label}</label><Field field={child} value={group[child.key]} onChange={(next)=>onChange({...group,[child.key]:next})} websiteId={websiteId} types={types}/></div>) : <p className="text-xs text-slate-500">This group has no sub-fields yet.</p>}</div>;
}

function RepeaterField({ field, value, onChange, websiteId, types }) {
    const rows = Array.isArray(value) ? value : [];
    const maxRows = Math.max(1, Number(field.max_rows || 20));
    const newRow = () => Object.fromEntries((field.fields || []).map((child)=>[child.key, fieldDefaultValue(child)]));
    const updateRow = (index, key, nextValue) => onChange(rows.map((row,i)=>i===index?{...(row || {}),[key]:nextValue}:row));
    const move = (index, direction) => { const target=index+direction; if(target<0||target>=rows.length)return; const next=[...rows]; [next[index],next[target]]=[next[target],next[index]]; onChange(next); };
    return <div className="mt-1 rounded-2xl border border-white/10 bg-white/[0.02] p-3"><div className="flex items-center justify-between gap-3"><p className="text-xs text-slate-500">{rows.length}/{maxRows} rows</p><button type="button" disabled={rows.length>=maxRows || !(field.fields || []).length} onClick={()=>onChange([...rows,newRow()])} className="rounded-lg border border-violet-400/20 bg-violet-500/10 px-3 py-2 text-xs font-bold text-violet-200 disabled:opacity-40">+ Add row</button></div>{rows.length ? <div className="mt-3 space-y-3">{rows.map((row,index)=><div key={index} className="rounded-xl border border-white/10 bg-black/10 p-3"><div className="mb-3 flex items-center justify-between"><span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Row {index+1}</span><div className="flex gap-1"><button type="button" disabled={index===0} onClick={()=>move(index,-1)} className="rounded border border-white/10 px-2 py-1 text-[10px] text-slate-300 disabled:opacity-30">↑</button><button type="button" disabled={index===rows.length-1} onClick={()=>move(index,1)} className="rounded border border-white/10 px-2 py-1 text-[10px] text-slate-300 disabled:opacity-30">↓</button><button type="button" disabled={rows.length>=maxRows} onClick={()=>onChange([...rows.slice(0,index+1),JSON.parse(JSON.stringify(row || {})),...rows.slice(index+1)])} className="rounded border border-white/10 px-2 py-1 text-[10px] font-semibold text-slate-300 disabled:opacity-30">Duplicate</button><button type="button" onClick={()=>onChange(rows.filter((_,i)=>i!==index))} className="rounded border border-rose-400/20 px-2 py-1 text-[10px] font-semibold text-rose-300">Remove</button></div></div><div className="grid gap-3 sm:grid-cols-2">{(field.fields || []).map((child)=><div key={child.key} className={['richtext','gallery'].includes(child.type)?'sm:col-span-2':''}><label className="text-xs font-semibold text-slate-300">{child.label}</label><Field field={child} value={row?.[child.key]} onChange={(next)=>updateRow(index,child.key,next)} websiteId={websiteId} types={types}/></div>)}</div></div>)}</div> : <div className="mt-3 rounded-xl border border-dashed border-white/10 px-3 py-5 text-center text-xs text-slate-500">Add a row to start entering repeatable data.</div>}</div>;
}

function Field({ field, value, onChange, websiteId, types }) {
    const common = 'mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white outline-none transition focus:border-violet-400/50 focus:ring-2 focus:ring-violet-400/10';
    if (field.type === 'boolean') return <label className="mt-2 flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" checked={Boolean(value)} onChange={(e) => onChange(e.target.checked)} className="rounded border-white/20 bg-white/5" /> Enabled</label>;
    if (field.type === 'textarea') return <textarea rows={4} className={common} value={value || ''} onChange={(e) => onChange(e.target.value)} placeholder={field.placeholder || ''} />;
    if (field.type === 'richtext') return <RichTextField value={value} onChange={onChange} placeholder={field.placeholder} />;
    if (field.type === 'image') return <ImageField websiteId={websiteId} value={value} onChange={onChange} />;
    if (field.type === 'gallery') return <GalleryField websiteId={websiteId} value={value} onChange={onChange} />;
    if (field.type === 'select') return <select className={`${common} cosmic-content-select`} value={value || ''} onChange={(e)=>onChange(e.target.value)}><option value="">Select…</option>{(field.options || []).map((option)=><option key={option} value={option}>{option}</option>)}</select>;
    if (field.type === 'relation') return <RelationField field={field} value={value} onChange={onChange} types={types}/>;
    if (field.type === 'group') return <GroupField field={field} value={value} onChange={onChange} websiteId={websiteId} types={types}/>;
    if (field.type === 'repeater') return <RepeaterField field={field} value={value} onChange={onChange} websiteId={websiteId} types={types}/>;
    return <input type={['date','datetime-local','url','number'].includes(field.type) ? (field.type === 'datetime' ? 'datetime-local' : field.type) : 'text'} className={common} value={value || ''} onChange={(e) => onChange(e.target.value)} placeholder={field.placeholder || ''} />;
}

function NestedFieldsEditor({ fields = [], onChange, types }) {
    const add = () => onChange([...fields,{key:`sub_field_${fields.length+1}`,label:'New sub-field',type:'text'}]);
    const update = (index, patch) => onChange(fields.map((field,i)=>i===index?{...field,...patch}:field));
    return <div className="mt-3 rounded-xl border border-white/10 bg-black/10 p-3"><div className="flex items-center justify-between"><div><p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Sub-fields</p><p className="mt-1 text-[11px] text-slate-600">Up to 20 fields per group/repeater.</p></div><button type="button" disabled={fields.length>=20} onClick={add} className="rounded-lg border border-white/10 px-2.5 py-1.5 text-[11px] font-semibold text-slate-300 disabled:opacity-40">+ Sub-field</button></div><div className="mt-3 space-y-2">{fields.map((field,index)=><div key={`sub-field-${index}`} className="grid gap-2 rounded-lg border border-white/10 p-2 md:grid-cols-[1fr_1fr_130px_auto]"><input value={field.label || ''} onChange={(e)=>update(index,{label:e.target.value,key:field.key?.startsWith('sub_field_')?(slugify(e.target.value).replace(/-/g,'_')||field.key):field.key})} className="rounded-lg border border-white/10 bg-white/[0.04] px-2 py-1.5 text-[11px] text-white" placeholder="Label"/><input value={field.key || ''} onChange={(e)=>update(index,{key:slugify(e.target.value).replace(/-/g,'_')})} className="rounded-lg border border-white/10 bg-white/[0.04] px-2 py-1.5 text-[11px] text-white" placeholder="field_key"/><select value={field.type || 'text'} onChange={(e)=>update(index,{type:e.target.value})} className="cosmic-content-select rounded-lg border border-white/10 bg-[#15151a] px-2 py-1.5 text-[11px] text-white">{simpleNestedFieldTypes.map(([value,label])=><option key={value} value={value}>{label}</option>)}</select><button type="button" onClick={()=>onChange(fields.filter((_,i)=>i!==index))} className="rounded-lg border border-rose-400/20 px-2 py-1.5 text-[10px] text-rose-300">Delete</button>{field.type==='select'?<input value={(field.options||[]).join(', ')} onChange={(e)=>update(index,{options:e.target.value.split(',').map(v=>v.trim()).filter(Boolean).slice(0,50)})} className="md:col-span-4 rounded-lg border border-white/10 bg-white/[0.03] px-2 py-1.5 text-[11px] text-slate-300" placeholder="Select options, comma separated"/>:null}{field.type==='relation'?<div className="md:col-span-4 grid gap-2 sm:grid-cols-[1fr_auto]"><select value={field.related_type_id || ''} onChange={(e)=>update(index,{related_type_id:e.target.value?Number(e.target.value):null})} className="cosmic-content-select rounded-lg border border-white/10 bg-[#15151a] px-2 py-1.5 text-[11px] text-white"><option value="">Choose related content type…</option>{(types||[]).map((type)=><option key={type.id} value={type.id}>{type.name}</option>)}</select><label className="flex items-center gap-2 rounded-lg border border-white/10 px-3 py-1.5 text-[11px] text-slate-300"><input type="checkbox" checked={Boolean(field.multiple)} onChange={(e)=>update(index,{multiple:e.target.checked})}/> Multiple</label></div>:null}</div>)}</div></div>;
}



const contentTypeStarters = [
    { id:'news', name:'News', singular_name:'News article', slug:'news', icon:'◉', description:'Company news, announcements, press updates, and timely stories.', schema:[
        {key:'author',label:'Author',type:'text'}, {key:'source',label:'Source',type:'text'}, {key:'news_date',label:'News date',type:'date'}, {key:'external_url',label:'External URL',type:'url'}
    ]},
    { id:'team', name:'Team', singular_name:'Team member', slug:'team', icon:'◎', description:'People, leadership, roles, bios, and team profiles.', schema:[
        {key:'role',label:'Role',type:'text'}, {key:'department',label:'Department',type:'text'}, {key:'email',label:'Email',type:'text'}, {key:'linkedin_url',label:'LinkedIn URL',type:'url'}, {key:'bio',label:'Bio',type:'richtext'}
    ]},
    { id:'services', name:'Services', singular_name:'Service', slug:'services', icon:'✦', description:'Service offerings, capabilities, deliverables, and expertise.', schema:[
        {key:'service_icon',label:'Service image',type:'image'}, {key:'short_description',label:'Short description',type:'textarea'}, {key:'features',label:'Features',type:'repeater',max_rows:12,fields:[{key:'feature',label:'Feature',type:'text'}]}, {key:'cta_url',label:'CTA URL',type:'url'}
    ]},
    { id:'testimonials', name:'Testimonials', singular_name:'Testimonial', slug:'testimonials', icon:'❝', description:'Customer quotes, reviews, client stories, and social proof.', schema:[
        {key:'person_name',label:'Person name',type:'text'}, {key:'person_role',label:'Role / company',type:'text'}, {key:'person_photo',label:'Photo',type:'image'}, {key:'rating',label:'Rating',type:'number'}, {key:'quote',label:'Quote',type:'richtext'}
    ]},
    { id:'jobs', name:'Jobs', singular_name:'Job', slug:'jobs', icon:'▣', description:'Open roles, departments, locations, and application details.', schema:[
        {key:'department',label:'Department',type:'text'}, {key:'location',label:'Location',type:'text'}, {key:'employment_type',label:'Employment type',type:'select',options:['Full-time','Part-time','Contract','Internship']}, {key:'salary',label:'Salary / range',type:'text'}, {key:'requirements',label:'Requirements',type:'richtext'}, {key:'apply_url',label:'Apply URL',type:'url'}, {key:'closing_date',label:'Closing date',type:'date'}
    ]},
    { id:'faq', name:'FAQs', singular_name:'FAQ', slug:'faqs', icon:'?', description:'Frequently asked questions grouped into reusable structured entries.', schema:[
        {key:'question',label:'Question',type:'text'}, {key:'answer',label:'Answer',type:'richtext'}, {key:'topic',label:'Topic',type:'text'}, {key:'sort_priority',label:'Sort priority',type:'number'}
    ]},
];

const starterDemoEntry = (starterId, singular = 'Item') => {
    const common = { status:'published', tags:['Demo'], gallery:[], featured_image_url:'', og_image_url:'', is_featured:true };
    const samples = {
        news:{title:'A new chapter begins',excerpt:'A polished sample news story ready for your own announcement.',content:'<p>Use this demo entry to preview your News templates, then replace it with your own story.</p>',category:'Company news',custom_fields:{author:'Editorial Team',source:'Company newsroom',news_date:new Date().toISOString().slice(0,10),external_url:''}},
        team:{title:'Alex Morgan',excerpt:'Creative lead focused on thoughtful digital experiences.',content:'<p>A sample team profile to help you preview the design.</p>',category:'Leadership',custom_fields:{role:'Creative Director',department:'Design',email:'alex@example.com',linkedin_url:'',bio:'<p>Alex leads multidisciplinary teams and turns complex ideas into clear digital experiences.</p>'}},
        services:{title:'Digital Strategy',excerpt:'Clear positioning, practical planning, and a focused roadmap.',content:'<p>A sample service entry ready to customize.</p>',category:'Strategy',custom_fields:{short_description:'Strategy and planning for ambitious digital products.',features:[{feature:'Discovery workshop'},{feature:'Roadmap planning'},{feature:'Launch strategy'}],cta_url:''}},
        testimonials:{title:'A thoughtful partnership',excerpt:'A sample client story for your testimonial archive.',content:'<p>Replace this sample with a real customer story.</p>',category:'Client story',custom_fields:{person_name:'Jamie Lee',person_role:'Founder, Northstar',rating:5,quote:'<p>The team made a complex project feel clear, collaborative, and easy to move forward.</p>'}},
        jobs:{title:'Senior Product Designer',excerpt:'Join a collaborative team building thoughtful digital products.',content:'<p>This is a sample role. Update the description, requirements, and application details before publishing.</p>',category:'Design',custom_fields:{department:'Design',location:'Remote',employment_type:'Full-time',salary:'Competitive',requirements:'<p>Strong product thinking, visual craft, and collaboration skills.</p>',apply_url:'',closing_date:''}},
        faq:{title:'How does this work?',excerpt:'A sample frequently asked question.',content:'<p>Edit this entry with the question and answer your visitors need most.</p>',category:'General',custom_fields:{question:'How does this work?',answer:'<p>Create structured entries once, then reuse them across pages with dynamic content blocks.</p>',topic:'General',sort_priority:1}},
    };
    return { ...common, ...(samples[starterId] || {title:`Sample ${singular}`,excerpt:'Sample content ready to customize.',content:'<p>Replace this demo content with your own.</p>',category:'Demo',custom_fields:{}}), slug:'' };
};

const demoEntrySlugByType = {
    blog: 'welcome-to-the-journal',
    events: 'community-open-day',
    projects: 'northstar-launch',
    news: 'a-new-chapter-begins',
    team: 'alex-morgan',
    services: 'digital-strategy',
    testimonials: 'a-thoughtful-partnership',
    jobs: 'senior-product-designer',
    faqs: 'how-does-this-work',
};

const supportsDemoContent = (type) => Boolean(type && (type.is_system || demoEntrySlugByType[String(type.slug || '').toLowerCase()] || demoEntrySlugByType[String(type.preset_key || '').toLowerCase()]));
const hasInstalledDemoContent = (type) => {
    if (!type) return false;
    const key = String(type.slug || '').toLowerCase();
    const preset = String(type.preset_key || '').toLowerCase();
    const expected = demoEntrySlugByType[key] || demoEntrySlugByType[preset];
    return Boolean(expected && (type.entries || []).some((entry) => String(entry.slug || '').toLowerCase() === expected));
};


export default function PostsUpdatesWorkspace({ website, initialWorkspace = { types: [] } }) {
    const [types, setTypes] = useState(initialWorkspace?.types || []);
    const [activeTypeId, setActiveTypeId] = useState(initialWorkspace?.types?.[0]?.id || null);
    const [editingEntry, setEditingEntry] = useState(null);
    const [entryDraft, setEntryDraft] = useState(null);
    const [slugTouched, setSlugTouched] = useState(false);
    const [saving, setSaving] = useState(false);
    const [showTypeModal, setShowTypeModal] = useState(false);
    const [showStarterPicker, setShowStarterPicker] = useState(false);
    const [selectedStarter, setSelectedStarter] = useState(null);
    const [initialDesignStage, setInitialDesignStage] = useState(null);
    const [showInstallModal, setShowInstallModal] = useState(false);
    const [installing, setInstalling] = useState(false);
    const [installingPageTypeId, setInstallingPageTypeId] = useState(null);
    const [installingDemoTypeId, setInstallingDemoTypeId] = useState(null);
    const [editingType, setEditingType] = useState(null);
    const [typeDraft, setTypeDraft] = useState({ name: '', singular_name: '', slug: '', description: '', icon: '◇', schema: [] });
    const [templateMode, setTemplateMode] = useState(null);
    const [templateDraft, setTemplateDraft] = useState({ id: null, name: '', description: '', markup: '', mini_banner_image_url: '' });
    const [templateBannerPickerOpen, setTemplateBannerPickerOpen] = useState(false);
    const [templateSaving, setTemplateSaving] = useState(false);
    const [templateAiPrompt, setTemplateAiPrompt] = useState('');
    const [templateGenerating, setTemplateGenerating] = useState(false);
    const [templateDesignSummary, setTemplateDesignSummary] = useState('');
    const [templatePreviewOpen, setTemplatePreviewOpen] = useState(false);
    const [templateThemeApplying, setTemplateThemeApplying] = useState(false);
    const [selectedDynamicThemeId, setSelectedDynamicThemeId] = useState(null);
    const [templateViewport, setTemplateViewport] = useState('desktop');
    const [templateSparkCopy, setTemplateSparkCopy] = useState({});
    const [templateEditKey, setTemplateEditKey] = useState(null);
    const [templateRewriteInstruction, setTemplateRewriteInstruction] = useState('');
    const [templateRewriteBusy, setTemplateRewriteBusy] = useState(false);
    const [templateSampleText, setTemplateSampleText] = useState('');
    const [templateSampleIndex, setTemplateSampleIndex] = useState(0);
    const [showContentAiModal, setShowContentAiModal] = useState(false);
    const [contentAiPrompt, setContentAiPrompt] = useState('');
    const [contentGenerating, setContentGenerating] = useState(false);
    const [contentGenerationStep, setContentGenerationStep] = useState(0);
    const [showFieldsAiModal, setShowFieldsAiModal] = useState(false);
    const [fieldsAiPrompt, setFieldsAiPrompt] = useState('');
    const [fieldsGenerating, setFieldsGenerating] = useState(false);
    const [fieldSampleText, setFieldSampleText] = useState('');
    const [fieldSampleIndex, setFieldSampleIndex] = useState(0);

    useEffect(() => {
        const onTemplateMessage = (event) => {
            if (event?.data?.type !== 'cosmic-template-copy-edit') return;
            const key = event.data.key;
            if (key === 'mini_banner' || Object.prototype.hasOwnProperty.call(templateSparkCopy || {}, key)) {
                setTemplateEditKey(key);
            }
        };
        window.addEventListener('message', onTemplateMessage);
        return () => window.removeEventListener('message', onTemplateMessage);
    }, [templateSparkCopy]);

    const aiPricing = initialWorkspace?.ai_pricing || {};
    const contentAiCost = Number(aiPricing.generate_content_entry ?? 30);
    const fieldsAiCost = Number(aiPricing.generate_content_fields ?? 20);
    const templateAiCost = Number(aiPricing.template_ai_personalize ?? 50);
    const templateRewriteCost = 10;

    const activeType = useMemo(() => types.find((type) => type.id === activeTypeId) || types[0] || null, [types, activeTypeId]);

    useEffect(() => {
        if (!showFieldsAiModal) return undefined;
        const samples = fieldPromptSamples(editingType || activeType || typeDraft);
        const target = samples[fieldSampleIndex % samples.length] || '';
        let cursor = 0;
        setFieldSampleText('');
        const timer = window.setInterval(() => {
            cursor += 1;
            setFieldSampleText(target.slice(0, cursor));
            if (cursor >= target.length) window.clearInterval(timer);
        }, 22);
        return () => window.clearInterval(timer);
    }, [showFieldsAiModal, fieldSampleIndex, editingType, activeType]);

    useEffect(() => {
        if (!templateMode || !activeType) return undefined;
        const samples = templatePromptSamples(activeType, templateMode);
        const target = samples[templateSampleIndex % samples.length] || '';
        let cursor = 0;
        setTemplateSampleText('');
        const timer = window.setInterval(() => {
            cursor += 1;
            setTemplateSampleText(target.slice(0, cursor));
            if (cursor >= target.length) window.clearInterval(timer);
        }, 20);
        return () => window.clearInterval(timer);
    }, [templateMode, templateSampleIndex, activeTypeId]);

    const replaceEntry = (typeId, entry) => setTypes((current) => current.map((type) => type.id !== typeId ? type : ({
        ...type,
        entries: type.entries.some((item) => item.id === entry.id) ? type.entries.map((item) => item.id === entry.id ? entry : item) : [entry, ...type.entries],
        entries_count: type.entries.some((item) => item.id === entry.id) ? type.entries_count : type.entries_count + 1,
    })));

    const openNewEntry = () => {
        if (!activeType) return;
        setEditingEntry(null);
        setSlugTouched(false);
        setEntryDraft(emptyEntry(activeType));
    };

    const openEntry = (entry) => {
        setEditingEntry(entry);
        setSlugTouched(true);
        setEntryDraft({ ...emptyEntry(activeType), ...entry, tags: entry.tags || [], gallery: entry.gallery || [], custom_fields: entry.custom_fields || {} });
    };

    const setEntryField = (key, value) => {
        setEntryDraft((current) => {
            const next = { ...current, [key]: value };
            if (key === 'title' && !slugTouched) next.slug = slugify(value);
            return next;
        });
    };

    const generateEntryContent = async () => {
        if (!activeType || !contentAiPrompt.trim() || contentGenerating) return;
        setShowContentAiModal(false);
        setContentGenerating(true);
        setContentGenerationStep(0);
        const timers = [
            setTimeout(() => setContentGenerationStep(1), 900),
            setTimeout(() => setContentGenerationStep(2), 2200),
            setTimeout(() => setContentGenerationStep(3), 3800),
        ];
        try {
            const response = await axios.post(route('content-entries.generate', [website.id, activeType.id]), {
                prompt: contentAiPrompt.trim(),
                current: entryDraft || {},
            });
            const generated = response.data?.entry || {};
            setEntryDraft({ ...emptyEntry(activeType), ...generated, status: entryDraft?.status || generated.status || 'draft', custom_fields: generated.custom_fields || {}, gallery: generated.gallery || [], tags: generated.tags || [] });
            setSlugTouched(true);
            setContentGenerationStep(4);
            showCosmicNotification({ title: 'Cosmic AI content ready', message: `${response.data?.credits_spent || 0} credits used. Review the generated fields, then save when ready.`, tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to generate content', message: error.response?.data?.message || 'Cosmic AI could not generate this entry. Please try again.', tone: 'error' });
        } finally {
            timers.forEach(clearTimeout);
            setTimeout(() => setContentGenerating(false), 350);
        }
    };

    const saveEntry = async () => {
        if (!activeType || !entryDraft?.title?.trim()) return;
        setSaving(true);
        try {
            const payload = { ...entryDraft, tags: Array.isArray(entryDraft.tags) ? entryDraft.tags : String(entryDraft.tags || '').split(',').map((v) => v.trim()).filter(Boolean) };
            const response = editingEntry
                ? await axios.put(route('content-entries.update', [website.id, activeType.id, editingEntry.id]), payload)
                : await axios.post(route('content-entries.store', [website.id, activeType.id]), payload);
            const savedEntry = response.data.entry;
            replaceEntry(activeType.id, savedEntry);
            // The API now returns the canonical preview URL with the saved entry.
            // Keep it in local state immediately so View works without a workspace refresh.
            setEditingEntry(null);
            setEntryDraft(null);
            setSlugTouched(false);
            setShowContentAiModal(false);
            showCosmicNotification({ title: editingEntry ? 'Entry updated' : 'Entry created', message: `${activeType.singular_name} saved successfully.`, tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to save entry', message: error.response?.data?.message || 'Check the fields and try again.', tone: 'error' });
        } finally { setSaving(false); }
    };

    const duplicateEntry = async (entry) => {
        try {
            const response = await axios.post(route('content-entries.duplicate', [website.id, activeType.id, entry.id]));
            replaceEntry(activeType.id, response.data.entry);
            showCosmicNotification({ title: 'Entry duplicated', message: 'A draft copy is ready to edit.', tone: 'success' });
        } catch { showCosmicNotification({ title: 'Unable to duplicate entry', tone: 'error' }); }
    };

    const deleteEntry = async (entry) => {
        const ok = await confirmCosmicAction({ title: `Delete ${entry.title}?`, message: 'This entry will be permanently removed.', confirmLabel: 'Delete', tone: 'danger' });
        if (!ok) return;
        await axios.delete(route('content-entries.destroy', [website.id, activeType.id, entry.id]));
        setTypes((current) => current.map((type) => type.id !== activeType.id ? type : ({ ...type, entries: type.entries.filter((item) => item.id !== entry.id), entries_count: Math.max(0, type.entries_count - 1) })));
        if (editingEntry?.id === entry.id) { setEditingEntry(null); setEntryDraft(null); }
    };

    const ensurePremiumDefaultTemplates = async (candidateTypes, onlyTypeId = null) => {
        let latestWorkspace = null;
        for (const type of (candidateTypes || [])) {
            if (onlyTypeId && Number(type.id) !== Number(onlyTypeId)) continue;
            if (type.single_template && type.archive_template) continue;
            const preset = buildSchemaAwareDynamicTheme(dynamicThemePresets[0], type);
            for (const mode of ['single', 'archive']) {
                const current = mode === 'single' ? type.single_template : type.archive_template;
                if (current) continue;
                const response = await axios.post(route('content-templates.store', [website.id, type.id]), {
                    template_type: mode,
                    name: `${type.singular_name || type.name} ${mode === 'single' ? 'Single' : 'Archive'} · Premium Default`,
                    description: `Premium Default ${mode} template for ${type.name}.`,
                    markup: preset[mode],
                    mini_banner_image_url: '',
                });
                latestWorkspace = response.data?.workspace || latestWorkspace;
            }
        }
        return latestWorkspace;
    };

    const installContent = async (withDemo) => {
        setInstalling(true);
        try {
            const response = await axios.post(route('content.install', website.id), { with_demo: withDemo, add_navigation: true });
            let workspace = response.data?.workspace;
            const seeded = await ensurePremiumDefaultTemplates(workspace?.types || types);
            workspace = seeded || workspace;
            setTypes(workspace?.types || types);
            setShowInstallModal(false);
            const result = response.data?.result || {};
            showCosmicNotification({ title: 'Content pages installed', message: `${result.pages || 0} pages ready${withDemo ? ` with ${result.demo || 0} demo entries` : ''}.`, tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to install content pages', message: error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat()[0] || 'Check page slugs and try again.', tone: 'error' });
        } finally { setInstalling(false); }
    };

    const installActiveTypePage = async () => {
        if (!activeType || installingPageTypeId) return;
        setInstallingPageTypeId(activeType.id);
        try {
            const response = await axios.post(route('content-types.page.install', [website.id, activeType.id]), { add_navigation: true });
            let workspace = response.data?.workspace;
            const seeded = await ensurePremiumDefaultTemplates(workspace?.types || types, activeType.id);
            workspace = seeded || workspace;
            const nextTypes = workspace?.types || types;
            setTypes(nextTypes);
            const refreshed = nextTypes.find((type) => type.id === activeType.id);
            showCosmicNotification({
                title: activeType.installed_page ? `${activeType.name} page repaired` : `${activeType.name} page installed`,
                message: `A Standard Page with Mini Banner + dynamic Content Loop is ready in the Builder.`,
                tone: 'success',
            });
        } catch (error) {
            showCosmicNotification({ title: `Unable to install ${activeType.name} page`, message: error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat()[0] || 'Check the page slug and try again.', tone: 'error' });
        } finally {
            setInstallingPageTypeId(null);
        }
    };

    const installActiveTypeDemo = async () => {
        if (!activeType || installingDemoTypeId || !supportsDemoContent(activeType)) return;
        setInstallingDemoTypeId(activeType.id);
        try {
            const response = await axios.post(route('content-types.demo.install', [website.id, activeType.id]), { add_navigation: true });
            let workspace = response.data?.workspace;
            const seeded = await ensurePremiumDefaultTemplates(workspace?.types || types, activeType.id);
            workspace = seeded || workspace;
            const nextTypes = workspace?.types || types;
            setTypes(nextTypes);
            const result = response.data?.result || {};
            showCosmicNotification({
                title: result.created > 0 ? `${activeType.name} demo content installed` : `${activeType.name} demo content already installed`,
                message: result.created > 0
                    ? `${result.created} sample ${result.created === 1 ? activeType.singular_name.toLowerCase() : activeType.name.toLowerCase()} added. The archive page and navigation are ready too.`
                    : `No duplicates were added. Existing demo entries and the ${activeType.name} page were kept intact.`,
                tone: 'success',
            });
        } catch (error) {
            showCosmicNotification({
                title: `Unable to install ${activeType.name} demo content`,
                message: error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat()[0] || 'Please try again.',
                tone: 'error',
            });
        } finally {
            setInstallingDemoTypeId(null);
        }
    };

    const openNewType = () => {
        setEditingType(null);
        setSelectedStarter(null);
        setShowStarterPicker(true);
    };

    const chooseStarter = (starter) => {
        setShowStarterPicker(false);
        setEditingType(null);
        setSelectedStarter(starter?.id || 'custom');
        setTypeDraft(starter ? { name: starter.name, singular_name: starter.singular_name, slug: starter.slug, description: starter.description, icon: starter.icon || '◇', schema: starter.schema || [] } : { name: '', singular_name: '', slug: '', description: '', icon: '◇', schema: [] });
        setShowTypeModal(true);
    };

    const openEditType = () => {
        if (!activeType) return;
        setEditingType(activeType);
        setTypeDraft({ name: activeType.name, singular_name: activeType.singular_name, slug: activeType.slug, description: activeType.description || '', icon: activeType.icon || '◇', schema: activeType.schema || [] });
        setShowTypeModal(true);
    };

    const openTemplateManager = (mode) => {
        if (!activeType) return;
        const current = mode === 'single' ? activeType.single_template : activeType.archive_template;
        const currentThemeId = dynamicThemeIdFromMarkup(current?.markup || '') || 'premium';
        const currentPreset = dynamicThemePresets.find((preset) => preset.id === currentThemeId) || dynamicThemePresets[0];
        const generatedMarkup = buildSchemaAwareDynamicTheme(currentPreset, activeType)[mode];
        const initialMarkup = current?.markup && String(current.markup).includes('data-cosmic-generic-post-sparks') ? current.markup : generatedMarkup;
        const initialCopy = extractGenericPostSparkCopy(initialMarkup, activeType, mode);
        setTemplateMode(mode);
        setTemplateAiPrompt('');
        setTemplateSampleIndex(0);
        setTemplateDesignSummary('');
        setTemplatePreviewOpen(true);
        setTemplateViewport('desktop');
        setSelectedDynamicThemeId(currentThemeId);
        setTemplateSparkCopy(initialCopy);
        setTemplateDraft({
            id: current?.id || null,
            name: current?.name || `${activeType.singular_name || activeType.name} ${mode === 'single' ? 'Single' : 'Archive'} Template`,
            description: current?.description || `${mode === 'single' ? 'Single entry' : 'Archive listing'} template for ${activeType.name}.`,
            markup: applyGenericPostSparkCopy(initialMarkup, initialCopy),
            mini_banner_image_url: current?.metadata?.mini_banner_image_url || '',
        });
    };

    const previewDynamicTheme = (preset) => {
        if (!activeType || !templateMode || !preset) return;
        const resolved = buildSchemaAwareDynamicTheme(preset, activeType)[templateMode];
        const withCopy = applyGenericPostSparkCopy(resolved, templateSparkCopy);
        setSelectedDynamicThemeId(preset.id);
        setTemplateDraft((current) => ({
            ...current,
            name: `${activeType.singular_name || activeType.name} ${templateMode === 'single' ? 'Single' : 'Archive'} · ${preset.name}`,
            description: `${preset.name} ${templateMode} template for ${activeType.name}.`,
            markup: withCopy,
        }));
        setTemplatePreviewOpen(true);
    };

    const updateTemplateSparkCopy = (key, value) => {
        setTemplateSparkCopy((current) => {
            const next = { ...current, [key]: value };
            setTemplateDraft((draft) => ({ ...draft, markup: applyGenericPostSparkCopy(draft.markup, next) }));
            return next;
        });
    };

    const rewriteTemplateSparkCopy = async () => {
        if (!templateEditKey || templateEditKey === 'mini_banner' || templateRewriteBusy) return;
        const currentValue = String(templateSparkCopy[templateEditKey] || '').trim();
        if (!currentValue) return;
        setTemplateRewriteBusy(true);
        try {
            const response = await axios.post(`/websites/${website.id}/content/template-copy/rewrite`, {
                current_value: currentValue,
                instruction: templateRewriteInstruction.trim(),
                field_role: String(templateEditKey).endsWith('_copy') ? 'supporting paragraph' : 'section heading',
            });
            const text = String(response.data?.text || '').trim();
            if (!text) throw new Error('No text returned.');
            updateTemplateSparkCopy(templateEditKey, text);
            setTemplateRewriteInstruction('');
            showCosmicNotification({ title: 'Rewrite ready', message: `${templateRewriteCost} Cosmic Credits used.`, tone: 'success' });
        } catch (error) {
            console.error(error);
            showCosmicNotification({ title: 'Cosmic AI could not rewrite this copy', message: error.response?.data?.message || 'Generation failed. No credits were charged.', tone: 'error' });
        } finally {
            setTemplateRewriteBusy(false);
        }
    };

    const applyDynamicTheme = async (preset) => {
        if (!activeType || templateThemeApplying || !preset) return;
        setTemplateThemeApplying(true);
        try {
            const resolvedPreset = buildSchemaAwareDynamicTheme(preset, activeType);
            let latestWorkspace = null;
            const saveMode = async (mode) => {
                const current = mode === 'single' ? activeType.single_template : activeType.archive_template;
                const payload = {
                    template_type: mode,
                    name: current?.name || `${activeType.singular_name || activeType.name} ${mode === 'single' ? 'Single' : 'Archive'} · ${preset.name}`,
                    description: `${preset.name} ${mode} template for ${activeType.name}.`,
                    markup: resolvedPreset[mode],
                    mini_banner_image_url: current?.metadata?.mini_banner_image_url || '',
                };
                const response = current?.id
                    ? await axios.put(route('content-templates.update', [website.id, activeType.id, current.id]), { name: payload.name, description: payload.description, markup: payload.markup, mini_banner_image_url: payload.mini_banner_image_url })
                    : await axios.post(route('content-templates.store', [website.id, activeType.id]), payload);
                latestWorkspace = response.data?.workspace || latestWorkspace;
                return response;
            };
            await saveMode('single');
            await saveMode('archive');
            const nextTypes = latestWorkspace?.types || types;
            setTypes(nextTypes);
            const refreshed = nextTypes.find((type) => type.id === activeType.id);
            const current = templateMode === 'single' ? refreshed?.single_template : refreshed?.archive_template;
            setTemplateDraft({
                id: current?.id || null,
                name: current?.name || `${activeType.singular_name || activeType.name} ${templateMode === 'single' ? 'Single' : 'Archive'} · ${preset.name}`,
                description: current?.description || `${preset.name} ${templateMode} template for ${activeType.name}.`,
                markup: resolvedPreset[templateMode],
                mini_banner_image_url: current?.metadata?.mini_banner_image_url || '',
            });
            setSelectedDynamicThemeId(preset.id);
            setTemplateDesignSummary(`${preset.name} applied to both Single and Archive templates. No AI credits used.`);
            setTemplatePreviewOpen(false);
            showCosmicNotification({ title: `${preset.name} theme applied`, message: 'Single and Archive templates updated together. 0 AI credits used.', tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to apply theme', message: error.response?.data?.message || 'The matched dynamic theme could not be applied. Please try again.', tone: 'error' });
        } finally { setTemplateThemeApplying(false); }
    };

    const generateDynamicTemplate = async () => {
        if (!activeType || !templateMode || templateGenerating) return;
        setTemplateGenerating(true);
        setTemplatePreviewOpen(false);
        try {
            const response = await axios.post(route('content-templates.generate', [website.id, activeType.id]), {
                template_type: templateMode,
                prompt: templateAiPrompt.trim(),
            });
            setTemplateDraft((current) => ({ ...current, markup: response.data?.markup || current.markup }));
            setTemplateDesignSummary(response.data?.design_summary || 'Cosmic AI generated a new dynamic layout.');
            setTemplatePreviewOpen(true);
            showCosmicNotification({ title: 'Cosmic AI design ready', message: `${response.data?.credits_spent || 0} credits used. Preview the layout, then save when you are happy with it.`, tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to generate template', message: error.response?.data?.message || 'Cosmic AI could not generate this layout. Please try again.', tone: 'error' });
        } finally { setTemplateGenerating(false); }
    };

    const saveDynamicTemplate = async () => {
        if (!activeType || !templateMode || !templateDraft.name.trim()) return;
        setTemplateSaving(true);
        try {
            const payload = {
                template_type: templateMode,
                name: templateDraft.name.trim(),
                description: templateDraft.description || '',
                markup: templateDraft.markup || '',
                mini_banner_image_url: templateDraft.mini_banner_image_url || '',
            };
            const response = templateDraft.id
                ? await axios.put(route('content-templates.update', [website.id, activeType.id, templateDraft.id]), payload)
                : await axios.post(route('content-templates.store', [website.id, activeType.id]), payload);
            setTypes(response.data?.workspace?.types || types);
            setTemplateDraft((current) => ({ ...current, id: response.data?.template?.id || current.id, markup: response.data?.template?.markup || current.markup, mini_banner_image_url: response.data?.template?.metadata?.mini_banner_image_url || current.mini_banner_image_url || '' }));
            showCosmicNotification({ title: `${templateMode === 'single' ? 'Single' : 'Archive'} template saved`, message: 'Field bindings are validated and this template is now the default for this content type.', tone: 'success' });
            setTemplateEditKey(null);
            setTemplateBannerPickerOpen(false);
            setTemplateMode(null);
        } catch (error) {
            showCosmicNotification({ title: 'Unable to save dynamic template', message: error.response?.data?.message || 'Check the template bindings and try again.', tone: 'error' });
        } finally { setTemplateSaving(false); }
    };

    const insertBinding = (key) => {
        setTemplateDraft((current) => ({ ...current, markup: `${current.markup || ''}${current.markup ? '\n' : ''}{{ ${key} }}` }));
    };

    const generateFieldsWithAi = async () => {
        if (!fieldsAiPrompt.trim() || fieldsGenerating) return;
        setFieldsGenerating(true);
        try {
            const response = await axios.post(route('content-fields.generate', website.id), {
                prompt: fieldsAiPrompt.trim(),
                name: typeDraft.name || editingType?.name || activeType?.name || 'Custom content',
                singular_name: typeDraft.singular_name || editingType?.singular_name || activeType?.singular_name || 'Item',
                description: typeDraft.description || editingType?.description || activeType?.description || '',
                current_schema: typeDraft.schema || [],
            });
            setTypeDraft((current) => ({
                ...current,
                name: response.data?.name || current.name,
                singular_name: response.data?.singular_name || current.singular_name,
                slug: response.data?.slug || current.slug,
                description: response.data?.description || current.description,
                schema: response.data?.schema || current.schema || [],
            }));
            setShowFieldsAiModal(false);
            showCosmicNotification({
                title: 'Cosmic AI fields ready',
                message: `${response.data?.credits_spent || fieldsAiCost} credits used. Cosmic AI populated the content type details and fields. Review everything, then save when ready.`,
                tone: 'success',
            });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to generate fields', message: error.response?.data?.message || 'Cosmic AI could not generate the field schema. Please try again.', tone: 'error' });
        } finally { setFieldsGenerating(false); }
    };

    const createInitialTemplate = async (type, mode, markup, firstDesign = false) => {
        let finalMarkup = markup;
        if (!finalMarkup) {
            setInitialDesignStage(mode);
            const generated = await axios.post(route('content-templates.generate', [website.id, type.id]), {
                template_type: mode,
                first_design: firstDesign,
                prompt: `Create a premium ${mode === 'single' ? 'individual entry' : 'archive listing'} design for ${type.name}. Use every useful custom field naturally and keep the result production-ready.`,
            });
            finalMarkup = generated.data?.markup || '';
        }
        const saved = await axios.post(route('content-templates.store', [website.id, type.id]), {
            template_type: mode,
            name: `${type.singular_name || type.name} ${mode === 'single' ? 'Single' : 'Archive'} Template`,
            description: `${mode === 'single' ? 'Single entry' : 'Archive listing'} template for ${type.name}.`,
            markup: finalMarkup,
        });
        return saved.data?.workspace;
    };

    const saveType = async () => {
        if (!typeDraft.name.trim()) return;
        setSaving(true);
        try {
            if (editingType) {
                const response = await axios.put(route('content-types.update', [website.id, editingType.id]), { ...typeDraft, singular_name: typeDraft.singular_name || typeDraft.name.replace(/s$/i, '') });
                setTypes(response.data?.workspace?.types || ((current) => current.map((type) => type.id === editingType.id ? { ...type, ...response.data.type, schema: response.data.type.schema || [] } : type)));
                const refreshedType = response.data?.workspace?.types?.find((type) => type.id === editingType.id);
                showCosmicNotification({ title: 'Content type updated', message: refreshedType?.template_needs_refresh ? `${typeDraft.name} fields changed. Existing templates stay live until you choose Update design with AI.` : `${typeDraft.name} settings saved.`, tone: 'success' });
            } else {
                const response = await axios.post(route('content-types.store', website.id), { ...typeDraft, slug: typeDraft.slug || slugify(typeDraft.name), singular_name: typeDraft.singular_name || typeDraft.name.replace(/s$/i, ''), preset_key: selectedStarter && selectedStarter !== 'custom' ? selectedStarter : null });
                let type = { ...response.data.type, entries: [], entries_count: 0, schema: response.data.type.schema || [] };
                let workspace = response.data?.workspace;
                setShowTypeModal(false);

                if (selectedStarter && selectedStarter !== 'custom') {
                    setInitialDesignStage('starter');
                    const starterTheme = buildSchemaAwareDynamicTheme(dynamicThemePresets[0], type);
                    workspace = await createInitialTemplate(type, 'single', starterTheme.single, false);
                    workspace = await createInitialTemplate(type, 'archive', starterTheme.archive, false);
                } else {
                    workspace = await createInitialTemplate(type, 'single', null, true);
                    workspace = await createInitialTemplate(type, 'archive', null, true);
                }
                if (workspace?.types) {
                    setTypes(workspace.types);
                    type = workspace.types.find((item) => item.id === type.id) || type;
                } else setTypes((current) => [...current, type]);
                setActiveTypeId(type.id);
                showCosmicNotification({ title: `${type.name} ready`, message: selectedStarter && selectedStarter !== 'custom' ? 'Starter schema and premium Single + Archive templates are installed.' : 'Your first Single + Archive design was created free with Cosmic AI.', tone: 'success' });
            }
            setShowTypeModal(false);
            setEditingType(null);
            setSelectedStarter(null);
            setInitialDesignStage(null);
            setTypeDraft({ name: '', singular_name: '', slug: '', description: '', icon: '◇', schema: [] });
        } catch (error) {
            setInitialDesignStage(null);
            showCosmicNotification({ title: `Unable to ${editingType ? 'update' : 'create'} content type`, message: error.response?.data?.message || 'Check the fields and try again.', tone: 'error' });
        } finally { setSaving(false); }
    };

    const deleteType = async () => {
        if (!activeType || activeType.is_system) return;
        const ok = await confirmCosmicAction({
            title: `Delete ${activeType.name}?`,
            message: `This permanently removes the ${activeType.name} content type and all of its entries.`,
            confirmLabel: 'Delete content type',
            tone: 'danger',
        });
        if (!ok) return;
        try {
            const removedId = activeType.id;
            await axios.delete(route('content-types.destroy', [website.id, removedId]));
            setTypes((current) => {
                const next = current.filter((type) => type.id !== removedId);
                setActiveTypeId(next[0]?.id || null);
                return next;
            });
            setEntryDraft(null);
            setEditingEntry(null);
            showCosmicNotification({ title: 'Content type deleted', message: `${activeType.name} and its entries were removed.`, tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to delete content type', message: error.response?.data?.message || 'Please try again.', tone: 'error' });
        }
    };

    const activeDynamicThemeId = templateMode && activeType
        ? dynamicThemeIdFromMarkup((templateMode === 'single' ? activeType.single_template?.markup : activeType.archive_template?.markup) || '')
        : null;

    return <div className="cosmic-posts-workspace space-y-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><p className="text-sm font-semibold text-white">Posts / Updates</p><p className="mt-1 text-sm text-slate-400">Structured content for blogs, events, projects, news, and custom post types.</p></div>
            <div className="flex flex-wrap gap-2"><button type="button" onClick={openNewType} className="rounded-xl bg-violet-500 px-4 py-2 text-sm font-bold text-white hover:bg-violet-400">+ Content type</button></div>
        </div>

        <div className="grid gap-3 lg:grid-cols-[250px_minmax(0,1fr)]">
            <aside className="rounded-2xl border border-white/10 bg-white/[0.025] p-2">
                {(types || []).map((type) => <button key={type.id} type="button" onClick={() => { setActiveTypeId(type.id); setEntryDraft(null); setEditingEntry(null); }} className={`mb-1 flex w-full items-center justify-between rounded-xl px-3 py-3 text-left transition ${activeType?.id === type.id ? 'bg-violet-500/15 text-violet-100 ring-1 ring-violet-400/20' : 'text-slate-300 hover:bg-white/5'}`}>
                    <span><span className="mr-2">{type.icon || '◇'}</span><span className="font-semibold">{type.name}</span></span><span className="rounded-full bg-white/5 px-2 py-0.5 text-[10px] text-slate-500">{type.entries_count || 0}</span>
                </button>)}
                {!types.length ? <p className="p-3 text-xs text-slate-500">No content types yet.</p> : null}
            </aside>

            <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-4">
                {activeType ? <>
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-white/10 pb-4">
                        <div><div className="flex items-center gap-2"><h3 className="text-base font-bold text-white">{activeType.name}</h3><span className="rounded-full bg-white/5 px-2 py-0.5 text-[10px] uppercase tracking-wider text-slate-500">/{activeType.slug}</span></div><p className="mt-1 text-sm text-slate-400">{activeType.description}</p></div>
                        <div className="flex flex-wrap gap-2">{activeType.installed_page ? <><a href={activeType.installed_page.preview_url || activeType.archive_url || '#'} target="_blank" rel="noreferrer" className="rounded-xl border border-white/10 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/5">View page ↗</a><a href={activeType.installed_page.builder_url || '#'} className="rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 text-xs font-bold text-emerald-100 hover:bg-emerald-400/15">Edit page</a></> : <button type="button" disabled={installingPageTypeId === activeType.id} onClick={installActiveTypePage} className="rounded-xl border border-emerald-400/25 bg-emerald-400/10 px-3 py-2 text-xs font-bold text-emerald-100 hover:bg-emerald-400/15 disabled:opacity-50">{installingPageTypeId === activeType.id ? 'Installing page…' : `Install ${activeType.name} Page`}</button>}{activeType.entries?.[0]?.url ? <a href={activeType.entries[0].url} target="_blank" rel="noreferrer" className="rounded-xl border border-white/10 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/5">View sample single ↗</a> : null}{supportsDemoContent(activeType) && !hasInstalledDemoContent(activeType) ? <button type="button" disabled={installingDemoTypeId === activeType.id} onClick={installActiveTypeDemo} className="rounded-xl border border-amber-400/25 bg-amber-400/10 px-3 py-2 text-xs font-bold text-amber-100 hover:bg-amber-400/15 disabled:opacity-50">{installingDemoTypeId === activeType.id ? 'Installing demo…' : 'Install demo content'}</button> : null}<button type="button" onClick={openEditType} className="rounded-xl border border-white/10 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/5">Edit type</button>{!activeType.is_system ? <button type="button" onClick={deleteType} className="rounded-xl border border-rose-400/20 px-3 py-2 text-xs font-semibold text-rose-300 hover:bg-rose-400/10">Delete type</button> : null}<button type="button" onClick={openNewEntry} className="rounded-xl border border-violet-400/30 bg-violet-400/10 px-3 py-2 text-xs font-bold text-violet-100 hover:bg-violet-400/15">+ Add {activeType.singular_name}</button></div>
                    </div>
                    {activeType.template_needs_refresh ? <div className="mt-4 flex flex-col gap-3 rounded-2xl border border-amber-400/25 bg-amber-400/[0.07] p-4 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-xs font-bold text-amber-200">Content fields changed</p><p className="mt-1 text-xs leading-5 text-slate-400">Your current Single/Archive templates remain live and entry data is untouched. Update only the design that needs the new fields when you are ready.</p></div><div className="flex flex-wrap gap-2">{activeType.single_template_needs_refresh ? <button type="button" onClick={() => openTemplateManager('single')} className="rounded-xl bg-violet-500 px-3 py-2 text-xs font-bold text-white hover:bg-violet-400">Edit Single template</button> : null}{activeType.archive_template_needs_refresh ? <button type="button" onClick={() => openTemplateManager('archive')} className="rounded-xl bg-violet-500 px-3 py-2 text-xs font-bold text-white hover:bg-violet-400">Edit Archive template</button> : null}</div></div> : null}
                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                        {[['single', 'Single template', activeType.single_template, 'Controls the individual entry layout.'], ['archive', 'Archive template', activeType.archive_template, 'Controls the content listing/archive layout.']].map(([mode, label, template, help]) => <button key={mode} type="button" onClick={() => openTemplateManager(mode)} className="group rounded-2xl border border-white/10 bg-white/[0.025] p-4 text-left transition hover:border-violet-400/30 hover:bg-violet-500/[0.05]">
                            <span className="flex items-center justify-between gap-3"><span className="text-[10px] font-bold uppercase tracking-[0.16em] text-violet-300">{mode}</span><span className={`rounded-full px-2 py-1 text-[10px] font-bold ${template ? 'bg-emerald-400/10 text-emerald-300' : 'bg-white/5 text-slate-500'}`}>{(mode === 'single' ? activeType.single_template_needs_refresh : activeType.archive_template_needs_refresh) ? 'UPDATE NEEDED' : (template ? 'DEFAULT' : 'NOT SET')}</span></span>
                            <span className="mt-2 block text-sm font-bold text-white">{template?.name || label}</span>
                            <span className="mt-1 block text-xs leading-5 text-slate-400">{template?.description || help}</span>
                            <span className="mt-3 block text-xs font-semibold text-violet-300">{template ? 'Edit visual template →' : 'Create visual template →'}</span>
                        </button>)}
                    </div>
                    <div className="mt-4 space-y-2">
                        {(activeType.entries || []).map((entry) => <div key={entry.id} className="flex flex-col gap-3 rounded-xl border border-white/10 bg-black/10 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <button type="button" onClick={() => openEntry(entry)} className="flex min-w-0 flex-1 items-center gap-3 text-left">
                                <div className="h-14 w-16 shrink-0 overflow-hidden rounded-lg border border-white/10 bg-white/5">{entry.featured_image_url ? <img src={entry.featured_image_url} alt="" className="h-full w-full object-cover" /> : <div className="flex h-full items-center justify-center text-slate-600">◇</div>}</div>
                                <span className="min-w-0"><span className="block truncate text-sm font-bold text-white">{entry.title}</span><span className="mt-1 block text-xs text-slate-500">/{activeType.slug}/{entry.slug} · {entry.status}</span></span>
                            </button>
                            <div className="flex gap-2">{entry.url ? <a href={entry.url} target="_blank" rel="noreferrer" className="rounded-lg border border-violet-400/20 px-3 py-1.5 text-xs font-semibold text-violet-200 hover:bg-violet-400/10">View ↗</a> : <button type="button" disabled className="cursor-not-allowed rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-600">View ↗</button>}<button type="button" onClick={() => openEntry(entry)} className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-white/5">Edit</button><button type="button" onClick={() => duplicateEntry(entry)} className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-white/5">Duplicate</button><button type="button" onClick={() => deleteEntry(entry)} className="rounded-lg border border-rose-400/20 px-3 py-1.5 text-xs font-semibold text-rose-300 hover:bg-rose-400/10">Delete</button></div>
                        </div>)}
                        {!activeType.entries?.length ? <div className="rounded-xl border border-dashed border-white/10 p-8 text-center"><p className="text-sm font-semibold text-slate-300">No {activeType.name.toLowerCase()} yet</p><p className="mt-1 text-xs text-slate-500">Create your first entry to populate future dynamic Sparks.</p></div> : null}
                    </div>
                </> : null}
            </div>
        </div>


        {showStarterPicker ? <div className="fixed inset-0 z-[155] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"><div className="cosmic-content-modal w-full max-w-5xl rounded-3xl border border-white/10 bg-[#0d0d10] p-5 shadow-2xl"><div className="flex items-start justify-between gap-4"><div><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-violet-300">Posts / Updates</p><h2 className="mt-1 text-xl font-bold text-white">Choose a content type</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Start with a premade schema or create a custom type. Premium Default Single + Archive templates are prepared automatically; demo content stays optional.</p></div><button type="button" onClick={()=>setShowStarterPicker(false)} className="rounded-lg px-3 py-2 text-slate-400 hover:bg-white/5">✕</button></div><div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{contentTypeStarters.map((starter)=><button key={starter.id} type="button" onClick={()=>chooseStarter(starter)} className="group rounded-2xl border border-white/10 bg-white/[0.025] p-4 text-left transition hover:border-violet-400/35 hover:bg-violet-500/[0.06]"><span className="flex items-center justify-between gap-3"><span className="flex h-9 w-9 items-center justify-center rounded-xl border border-violet-400/20 bg-violet-500/10 text-lg text-violet-200">{starter.icon}</span><span className="rounded-full border border-white/10 bg-white/5 px-2 py-1 text-[9px] font-bold uppercase tracking-wider text-slate-500">Premade</span></span><span className="mt-4 block text-sm font-extrabold text-white">{starter.name}</span><span className="mt-1 block text-xs leading-5 text-slate-400">{starter.description}</span><span className="mt-3 block text-[11px] font-semibold text-violet-300">{starter.schema.length} structured fields →</span></button>)}<button type="button" onClick={()=>chooseStarter(null)} className="rounded-2xl border border-dashed border-white/15 bg-white/[0.015] p-4 text-left transition hover:border-violet-400/35 hover:bg-white/[0.035]"><span className="flex h-9 w-9 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-lg text-slate-300">＋</span><span className="mt-4 block text-sm font-extrabold text-white">Custom content type</span><span className="mt-1 block text-xs leading-5 text-slate-400">Start blank and define your own fields manually or with Cosmic AI.</span><span className="mt-3 block text-[11px] font-semibold text-violet-300">Create custom →</span></button></div></div></div> : null}

        {entryDraft && activeType ? <div className="fixed inset-0 z-[150] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"><div className="cosmic-content-modal flex max-h-[calc(100vh-3rem)] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-white/10 bg-[#0d0d10] shadow-2xl">
            <div className="z-10 flex shrink-0 items-start justify-between gap-4 border-b border-white/10 bg-[#0d0d10]/95 px-5 py-4 backdrop-blur"><div><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-violet-300">{activeType.singular_name}</p><h2 className="mt-1 text-xl font-bold text-white">{editingEntry ? `Edit ${entryDraft.title}` : `Add ${activeType.singular_name}`}</h2><p className="mt-1 text-xs text-slate-500">Edit structured content here. The public design is inherited automatically from this content type’s Single template.</p></div><div className="flex items-center gap-2"><button type="button" disabled={contentGenerating} onClick={() => setShowContentAiModal(true)} className="rounded-xl border border-violet-400/25 bg-violet-500/10 px-3 py-2 text-xs font-bold text-violet-100 transition hover:bg-violet-500/15 disabled:opacity-50">✦ Generate Content · {contentAiCost} credits</button><button type="button" onClick={() => { setEntryDraft(null); setEditingEntry(null); }} className="rounded-lg px-3 py-2 text-slate-400 hover:bg-white/5">✕</button></div></div>
            <div className="grid min-h-0 flex-1 gap-5 overflow-y-auto p-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(280px,.7fr)]">
                <div className="space-y-4">
                    <div><label className="text-xs font-semibold text-slate-300">Title</label><input className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400/50" value={entryDraft.title} onChange={(e) => setEntryField('title', e.target.value)} /></div>
                    <div><label className="text-xs font-semibold text-slate-300">Slug</label><input className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white outline-none focus:border-violet-400/50" value={entryDraft.slug} onChange={(e) => { setSlugTouched(true); setEntryField('slug', slugify(e.target.value)); }} /></div>
                    <div><label className="text-xs font-semibold text-slate-300">Excerpt</label><textarea rows={3} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white outline-none focus:border-violet-400/50" value={entryDraft.excerpt || ''} onChange={(e) => setEntryField('excerpt', e.target.value)} /></div>
                    <div><label className="text-xs font-semibold text-slate-300">Content</label><textarea rows={10} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white outline-none focus:border-violet-400/50" value={entryDraft.content || ''} onChange={(e) => setEntryField('content', e.target.value)} /></div>
                    <div className="rounded-2xl border border-emerald-400/15 bg-emerald-400/[0.05] p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><p className="text-xs font-bold text-emerald-200">Design inherited from Single template</p><p className="mt-1 text-xs leading-5 text-slate-400">Entries only store content and structured fields. Mini hero, post content styling, media treatment, and Post Sparks are controlled once from the Single template.</p></div>{activeType.single_template ? <button type="button" onClick={() => openTemplateManager('single')} className="rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 text-xs font-bold text-emerald-100 hover:bg-emerald-400/15">Edit Single template</button> : null}</div></div>
                    {activeType.schema?.length ? <div className="rounded-2xl border border-white/10 bg-white/[0.02] p-4"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">{activeType.name} fields</p><div className="mt-3 grid gap-3 sm:grid-cols-2">{activeType.schema.map((field) => <div key={field.key}><label className="text-xs font-semibold text-slate-300">{field.label}</label><Field field={field} websiteId={website.id} types={types} value={entryDraft.custom_fields?.[field.key]} onChange={(value) => setEntryField('custom_fields', { ...(entryDraft.custom_fields || {}), [field.key]: value })} /></div>)}</div></div> : null}
                </div>
                <aside className="space-y-4">
                    <div className="rounded-2xl border border-white/10 bg-white/[0.02] p-4"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Publishing</p><select value={entryDraft.status} onChange={(e) => setEntryField('status', e.target.value)} className="mt-3 w-full rounded-xl border border-white/10 bg-[#15151a] px-3 py-2 text-sm text-white"><option value="draft">Draft</option><option value="published">Published</option></select><label className="mt-3 flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" checked={Boolean(entryDraft.is_featured)} onChange={(e) => setEntryField('is_featured', e.target.checked)} /> Featured entry</label></div>
                    <div className="rounded-2xl border border-white/10 bg-white/[0.02] p-4"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Media</p><label className="mt-3 block text-xs font-semibold text-slate-300">Featured image</label><ImageField websiteId={website.id} value={entryDraft.featured_image_url || ''} onChange={(value) => setEntryField('featured_image_url', value)} showAdvancedUrl={false} kind="featured" /><label className="mt-4 block text-xs font-semibold text-slate-300">Gallery</label><p className="mt-1 text-[11px] leading-4 text-slate-500">Optional supporting images used by Single templates that include a gallery region.</p><GalleryField websiteId={website.id} value={entryDraft.gallery || []} onChange={(value) => setEntryField('gallery', value)} /></div>
                    <div className="rounded-2xl border border-white/10 bg-white/[0.02] p-4"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Organization</p><label className="mt-3 block text-xs font-semibold text-slate-300">Category</label><input value={entryDraft.category || ''} onChange={(e) => setEntryField('category', e.target.value)} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" /><label className="mt-3 block text-xs font-semibold text-slate-300">Tags</label><input value={(entryDraft.tags || []).join(', ')} onChange={(e) => setEntryField('tags', e.target.value.split(',').map((v) => v.trim()).filter(Boolean))} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" placeholder="design, news, launch" /></div>
                    <div className="rounded-2xl border border-white/10 bg-white/[0.02] p-4"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">SEO</p><input value={entryDraft.seo_title || ''} onChange={(e) => setEntryField('seo_title', e.target.value)} className="mt-3 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" placeholder="SEO title" /><textarea rows={3} value={entryDraft.seo_description || ''} onChange={(e) => setEntryField('seo_description', e.target.value)} className="mt-2 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" placeholder="Meta description" /></div>
                </aside>
            </div>
            <div className="flex shrink-0 justify-end gap-2 border-t border-white/10 bg-[#0d0d10]/95 px-5 py-4 backdrop-blur"><button type="button" onClick={() => { setEntryDraft(null); setEditingEntry(null); }} className="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300">Cancel</button><button type="button" onClick={saveEntry} disabled={saving || !entryDraft.title.trim()} className="rounded-xl bg-violet-500 px-5 py-2 text-sm font-bold text-white disabled:opacity-50">{saving ? 'Saving…' : 'Save entry'}</button></div>
        </div></div> : null}


        {showContentAiModal && activeType ? <div className="fixed inset-0 z-[190] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"><div className="cosmic-content-modal w-full max-w-2xl rounded-3xl border border-white/10 bg-[#0d0d10] p-5 shadow-2xl"><div className="flex items-start justify-between gap-4"><div><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-violet-300">Cosmic AI · Content</p><h2 className="mt-1 text-xl font-bold text-white">Generate {activeType.singular_name} content</h2><p className="mt-2 text-sm leading-6 text-slate-400">Describe what you want to publish. Cosmic AI will populate the title, slug, category, SEO, featured image, gallery, body, and compatible custom fields. Nothing is saved until you review and click Save entry.</p></div><button type="button" onClick={() => setShowContentAiModal(false)} className="rounded-lg px-3 py-2 text-slate-400 hover:bg-white/5">✕</button></div><div className="mt-5"><label className="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">What should Cosmic AI create?</label><textarea autoFocus rows={6} value={contentAiPrompt} onChange={(e)=>setContentAiPrompt(e.target.value)} className="mt-2 w-full rounded-2xl border border-white/10 bg-white/[0.045] px-4 py-3 text-sm leading-6 text-white outline-none placeholder:text-slate-600 focus:border-violet-400/50" placeholder={`Example: Write a premium ${activeType.singular_name.toLowerCase()} about our new launch. Make it useful, credible, and SEO-friendly with strong editorial imagery.`}/><div className="mt-3 rounded-xl border border-white/10 bg-white/[0.025] px-3 py-2.5 text-xs leading-5 text-slate-500">Cosmic AI uses the current <span className="font-semibold text-slate-300">{activeType.name}</span> schema, including custom fields, select options, relations, groups, and repeaters.</div><p className="mt-3 text-xs text-slate-500">Cost: <span className="font-bold text-amber-300">{contentAiCost} Cosmic Credits</span>. Failed generations are refunded.</p></div><div className="mt-5 flex justify-end gap-2"><button type="button" onClick={()=>setShowContentAiModal(false)} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300">Cancel</button><button type="button" disabled={!contentAiPrompt.trim()} onClick={generateEntryContent} className="rounded-xl bg-violet-500 px-5 py-2.5 text-sm font-bold text-white hover:bg-violet-400 disabled:opacity-45">✦ Generate content · {contentAiCost} credits</button></div></div></div> : null}

        {contentGenerating ? <div className="fixed inset-0 z-[210] flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-md"><div className="cosmic-content-modal w-full max-w-xl rounded-3xl border border-violet-400/15 bg-[#0d0d10] p-7 text-center shadow-2xl"><div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-violet-400/20 bg-violet-500/10"><span className="text-2xl text-violet-200">✦</span></div><p className="mt-5 text-[10px] font-bold uppercase tracking-[0.2em] text-violet-300">Cosmic AI content engine</p><h3 className="mt-2 text-2xl font-extrabold text-white">Creating your {activeType?.singular_name || 'content'}</h3><p className="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-400">Cosmic AI is writing structured content against your exact schema and finding matching editorial imagery.</p><div className="mt-6 space-y-2 text-left">{['Understanding your prompt & schema','Writing title, body & SEO','Populating custom fields & relationships','Finding featured and gallery imagery','Content ready for review'].map((label,index)=><div key={label} className={`flex items-center gap-3 rounded-xl border px-3 py-2.5 transition ${index < contentGenerationStep ? 'border-emerald-400/15 bg-emerald-400/[0.055] text-emerald-100' : index === contentGenerationStep ? 'border-violet-400/25 bg-violet-500/[0.08] text-violet-100' : 'border-white/5 bg-white/[0.015] text-slate-600'}`}><span className={`flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold ${index < contentGenerationStep ? 'bg-emerald-400/15' : index === contentGenerationStep ? 'bg-violet-400/15' : 'bg-white/5'}`}>{index < contentGenerationStep ? '✓' : index + 1}</span><span className="text-xs font-semibold">{label}</span>{index === contentGenerationStep && index < 4 ? <span className="ml-auto h-3.5 w-3.5 animate-spin rounded-full border-2 border-violet-300/25 border-t-violet-200"/> : null}</div>)}</div><div className="mt-6 overflow-hidden rounded-full bg-white/5"><div className="h-1.5 rounded-full bg-violet-400 transition-all duration-700" style={{width:`${Math.min(100, 18 + contentGenerationStep * 21)}%`}} /></div></div></div> : null}

        {templateMode && activeType ? <div id="cosmic-post-template-editor" data-cosmic-post-template-editor className="fixed inset-0 z-[175] bg-[#09090b] text-white">
            <div className="flex h-screen min-h-0 flex-col">
                <header className="shrink-0 border-b border-white/10 bg-[#0d0d10]">
                    <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 lg:px-5">
                        <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><p className="text-[10px] font-extrabold uppercase tracking-[0.18em] text-violet-300">Visual template editor</p><span className="rounded-full border border-white/10 bg-white/5 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">{templateMode}</span></div><h2 className="mt-1 truncate text-lg font-extrabold">{activeType.name} · {dynamicThemePresets.find((preset) => preset.id === selectedDynamicThemeId)?.name || 'Custom layout'}</h2></div>
                        <div className="flex flex-wrap items-center gap-2"><span className="rounded-lg border border-white/10 bg-white/[0.04] px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-300">Page style: {website?.page_style || website?.published_page_style || 'Auto'}</span><span className="rounded-lg border border-white/10 bg-white/[0.04] px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-300">Theme: {website?.theme_name || website?.theme || website?.theme_settings?.primary || 'Website theme'}</span><div className="hidden overflow-hidden rounded-xl border border-white/10 sm:flex">{[['desktop','Desktop'],['tablet','Tablet'],['mobile','Mobile']].map(([id,label])=><button key={id} type="button" onClick={()=>setTemplateViewport(id)} className={`px-3 py-2 text-[11px] font-bold ${templateViewport===id?'bg-white text-slate-950':'text-slate-400 hover:bg-white/5'}`}>{label}</button>)}</div><button type="button" onClick={()=>setTemplateEditKey('mini_banner')} className="rounded-xl border border-white/10 px-3 py-2 text-xs font-bold text-slate-300 hover:bg-white/5">Mini banner</button><button type="button" onClick={()=>setTemplateMode(null)} className="rounded-xl border border-white/10 px-3 py-2 text-sm font-bold text-slate-300 hover:bg-white/5">Cancel</button><button type="button" onClick={saveDynamicTemplate} disabled={templateSaving || !templateDraft.name.trim()} className="rounded-xl bg-violet-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-violet-400 disabled:opacity-50">{templateSaving ? 'Applying…' : 'Apply template'}</button></div>
                    </div>
                    <div className="flex items-center gap-2 overflow-x-auto border-t border-white/5 px-4 py-2.5 lg:px-5">
                        <span className="mr-1 shrink-0 text-[10px] font-extrabold uppercase tracking-[.14em] text-slate-500">Layout</span>
                        {dynamicThemePresets.map((preset)=><button key={preset.id} type="button" onClick={()=>previewDynamicTheme(preset)} className={`shrink-0 rounded-xl border px-3 py-2 text-left transition ${selectedDynamicThemeId===preset.id?'border-violet-400/60 bg-violet-500/15 text-white':'border-white/10 bg-white/[0.025] text-slate-300 hover:border-violet-400/30 hover:bg-white/5'}`}><span className="flex items-center gap-2 text-xs font-extrabold">{preset.name}{preset.badge?<span className="rounded-full bg-emerald-400/10 px-1.5 py-0.5 text-[8px] font-bold uppercase text-emerald-300">{preset.badge}</span>:null}</span></button>)}
                    </div>
                </header>
                <main className="min-h-0 flex-1 overflow-auto bg-[#151519] p-3 sm:p-4 lg:p-5">
                    <div className={`mx-auto min-h-full transition-all ${templateViewport==='mobile'?'max-w-[390px]':templateViewport==='tablet'?'max-w-[820px]':'max-w-[1500px]'}`}>
                        <iframe title="Visual dynamic template preview" srcDoc={templatePreviewDocument(templateDraft.markup, activeType, templateMode, null, true, website, templateDraft.mini_banner_image_url)} className="h-[calc(100vh-155px)] min-h-[680px] w-full rounded-2xl border border-white/10 bg-white shadow-2xl" sandbox="allow-scripts" />
                    </div>
                </main>
            </div>

            {templateEditKey ? <div className="fixed inset-0 z-[205] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm" onMouseDown={(e)=>{if(e.target===e.currentTarget)setTemplateEditKey(null)}}><div className="w-full max-w-lg rounded-3xl border border-white/10 bg-[#111116] p-5 shadow-2xl">
                {templateEditKey === 'mini_banner' ? <><div className="flex items-start justify-between gap-4"><div><p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-violet-300">Mini banner</p><h3 className="mt-1 text-lg font-extrabold text-white">Banner appearance</h3><p className="mt-1 text-xs leading-5 text-slate-400">The sample title and real post data stay read-only. Choose an optional background image; the global Page Style controls primary/clean treatment.</p></div><button type="button" onClick={()=>setTemplateEditKey(null)} className="rounded-lg px-2 py-1 text-slate-400 hover:bg-white/5">✕</button></div>{templateDraft.mini_banner_image_url?<img src={templateDraft.mini_banner_image_url} alt="Mini banner" className="mt-4 h-36 w-full rounded-2xl object-cover"/>:<div className="mt-4 flex h-28 items-center justify-center rounded-2xl border border-dashed border-white/10 bg-white/[0.025] text-xs text-slate-500">Using Page Style background</div>}<div className="mt-4 flex justify-end gap-2">{templateDraft.mini_banner_image_url?<button type="button" onClick={()=>setTemplateDraft((d)=>({...d,mini_banner_image_url:''}))} className="rounded-xl border border-rose-400/20 px-3 py-2 text-xs font-bold text-rose-300">Remove</button>:null}<button type="button" onClick={()=>{setTemplateEditKey(null);setTemplateBannerPickerOpen(true)}} className="rounded-xl bg-violet-500 px-4 py-2 text-xs font-extrabold text-white">{templateDraft.mini_banner_image_url?'Change image':'Choose image'}</button></div></> : <><div className="flex items-start justify-between gap-4"><div><p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-violet-300">Post Spark</p><h3 className="mt-1 text-lg font-extrabold text-white">Edit template copy</h3><p className="mt-1 text-xs leading-5 text-slate-400">This is generic template copy only. The actual post title, body and structured fields remain read-only.</p></div><button type="button" onClick={()=>setTemplateEditKey(null)} className="rounded-lg px-2 py-1 text-slate-400 hover:bg-white/5">✕</button></div><label className="mt-4 block"><span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{String(templateEditKey).replace(/_/g,' ')}</span>{String(templateEditKey).endsWith('_copy')?<textarea autoFocus rows={5} value={templateSparkCopy[templateEditKey]||''} onChange={(e)=>updateTemplateSparkCopy(templateEditKey,e.target.value)} className="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-3 py-3 text-sm leading-6 text-white outline-none focus:border-violet-400/50"/>:<input autoFocus value={templateSparkCopy[templateEditKey]||''} onChange={(e)=>updateTemplateSparkCopy(templateEditKey,e.target.value)} className="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-3 py-3 text-sm text-white outline-none focus:border-violet-400/50"/>}</label><div className="mt-4 rounded-2xl border border-violet-400/15 bg-violet-400/[0.05] p-3"><div className="flex items-center justify-between gap-3"><div><p className="text-xs font-extrabold text-violet-200">Rewrite with Cosmic AI</p><p className="mt-0.5 text-[11px] text-slate-500">Optional instruction · {templateRewriteCost} credits</p></div><button type="button" onClick={rewriteTemplateSparkCopy} disabled={templateRewriteBusy} className="rounded-xl border border-violet-400/25 bg-violet-500 px-3 py-2 text-xs font-extrabold text-white disabled:opacity-50">{templateRewriteBusy?'Rewriting…':`Rewrite · ${templateRewriteCost}`}</button></div><input value={templateRewriteInstruction} onChange={(e)=>setTemplateRewriteInstruction(e.target.value)} placeholder="e.g. Make it premium, friendly, concise…" className="mt-3 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-xs text-white outline-none focus:border-violet-400/50" /></div><div className="mt-4 flex justify-end"><button type="button" onClick={()=>{setTemplateEditKey(null);setTemplateRewriteInstruction('')}} className="rounded-xl bg-violet-500 px-4 py-2 text-xs font-extrabold text-white">Done</button></div></>}
            </div></div> : null}
        </div> : null}

        {templateBannerPickerOpen ? <MediaPickerModal open={templateBannerPickerOpen} websiteId={website?.id} title="Choose mini banner background" kind={`dynamic-${templateMode || 'content'}-mini-banner`} onClose={()=>setTemplateBannerPickerOpen(false)} onSelect={(selection)=>{ const asset=Array.isArray(selection)?selection[0]:selection; if(asset?.url){setTemplateDraft((d)=>({...d,mini_banner_image_url:asset.url}));} setTemplateBannerPickerOpen(false); }} /> : null}
        {showTypeModal ? <div className="fixed inset-0 z-[160] flex items-center justify-center bg-slate-950/80 p-4"><div className="cosmic-content-modal max-h-[calc(100vh-3rem)] w-full max-w-4xl overflow-y-auto rounded-3xl border border-white/10 bg-[#0d0d10] p-5 shadow-2xl"><div className="flex justify-between"><div><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-violet-300">Posts / Updates</p><h2 className="mt-1 text-xl font-bold text-white">{editingType ? `Edit ${editingType.name}` : 'Add content type'}</h2></div><button onClick={() => setShowTypeModal(false)} className="text-slate-400">✕</button></div><div className="mt-5 grid gap-3 sm:grid-cols-2"><div><label className="text-xs font-semibold text-slate-300">Plural name</label><input value={typeDraft.name} onChange={(e) => setTypeDraft((d) => ({ ...d, name: e.target.value, slug: slugify(e.target.value) }))} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" placeholder="Jobs" /></div><div><label className="text-xs font-semibold text-slate-300">Singular name</label><input value={typeDraft.singular_name} onChange={(e) => setTypeDraft((d) => ({ ...d, singular_name: e.target.value }))} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" placeholder="Job" /></div><div className="sm:col-span-2"><label className="text-xs font-semibold text-slate-300">Slug</label><input value={typeDraft.slug} onChange={(e) => setTypeDraft((d) => ({ ...d, slug: slugify(e.target.value) }))} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" /></div><div className="sm:col-span-2"><label className="text-xs font-semibold text-slate-300">Description</label><textarea rows={3} value={typeDraft.description} onChange={(e) => setTypeDraft((d) => ({ ...d, description: e.target.value }))} className="mt-1 w-full rounded-xl border border-white/10 bg-white/[0.045] px-3 py-2 text-sm text-white" /></div></div><div className="mt-5 rounded-2xl border border-white/10 bg-white/[0.02] p-4"><div className="flex flex-wrap items-center justify-between gap-2"><div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Custom fields</p><p className="mt-1 text-xs text-slate-500">These fields appear automatically in every {typeDraft.singular_name || 'entry'} editor.</p></div><div className="flex flex-wrap gap-2"><button type="button" onClick={() => setTypeDraft((d) => ({ ...d, schema: [...(d.schema || []), { key: `field_${(d.schema || []).length + 1}`, label: 'New field', type: 'text' }] }))} className="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300">+ Field</button></div></div><div className="mt-3 space-y-2">{(typeDraft.schema || []).map((field, index) => <div key={`field-${index}`} className="grid gap-2 rounded-xl border border-white/10 p-3 sm:grid-cols-[1fr_1fr_130px_auto]"><input value={field.label || ''} onChange={(e) => setTypeDraft((d) => ({ ...d, schema: d.schema.map((item, i) => i === index ? { ...item, label: e.target.value, key: item.key?.startsWith('field_') ? slugify(e.target.value).replace(/-/g, '_') || item.key : item.key } : item) }))} className="rounded-lg border border-white/10 bg-white/[0.045] px-2.5 py-2 text-xs text-white" placeholder="Label" /><input value={field.key || ''} onChange={(e) => setTypeDraft((d) => ({ ...d, schema: d.schema.map((item, i) => i === index ? { ...item, key: slugify(e.target.value).replace(/-/g, '_') } : item) }))} className="rounded-lg border border-white/10 bg-white/[0.045] px-2.5 py-2 text-xs text-white" placeholder="field_key" /><select value={field.type || 'text'} onChange={(e) => setTypeDraft((d) => ({ ...d, schema: d.schema.map((item, i) => i === index ? { ...item, type: e.target.value } : item) }))} className="cosmic-content-select rounded-lg border border-white/10 bg-[#15151a] px-2 py-2 text-xs text-white"><option value="text">Text</option><option value="textarea">Textarea</option><option value="richtext">Rich text</option><option value="image">Image</option><option value="gallery">Gallery</option><option value="select">Select</option><option value="date">Date</option><option value="datetime">Date & time</option><option value="url">URL</option><option value="number">Number</option><option value="boolean">Boolean</option><option value="relation">Relation</option><option value="group">Group</option><option value="repeater">Repeater</option></select><button type="button" onClick={() => setTypeDraft((d) => ({ ...d, schema: d.schema.filter((_, i) => i !== index) }))} className="rounded-lg border border-rose-400/20 px-2.5 py-2 text-xs text-rose-300">Delete</button>{field.type === 'select' ? <div className="sm:col-span-4"><label className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Select options</label><input value={(field.options || []).join(', ')} onChange={(e) => setTypeDraft((d) => ({ ...d, schema: d.schema.map((item, i) => i === index ? { ...item, options: e.target.value.split(',').map((value) => value.trim()).filter(Boolean).slice(0, 50) } : item) }))} className="mt-1 w-full rounded-lg border border-white/10 bg-white/[0.045] px-2.5 py-2 text-xs text-white" placeholder="Option one, Option two, Option three" /></div> : null}{field.type === 'relation' ? <div className="sm:col-span-4 grid gap-2 sm:grid-cols-[1fr_auto]"><select value={field.related_type_id || ''} onChange={(e)=>setTypeDraft((d)=>({...d,schema:d.schema.map((item,i)=>i===index?{...item,related_type_id:e.target.value?Number(e.target.value):null}:item)}))} className="cosmic-content-select rounded-lg border border-white/10 bg-[#15151a] px-2.5 py-2 text-xs text-white"><option value="">Choose related content type…</option>{types.map((type)=><option key={type.id} value={type.id}>{type.name}</option>)}</select><label className="flex items-center gap-2 rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-300"><input type="checkbox" checked={Boolean(field.multiple)} onChange={(e)=>setTypeDraft((d)=>({...d,schema:d.schema.map((item,i)=>i===index?{...item,multiple:e.target.checked}:item)}))}/> Allow multiple entries</label></div> : null}{['group','repeater'].includes(field.type) ? <div className="sm:col-span-4">{field.type === 'repeater' ? <div className="mb-2 flex items-center gap-2"><label className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Maximum rows</label><input type="number" min="1" max="50" value={field.max_rows || 20} onChange={(e)=>setTypeDraft((d)=>({...d,schema:d.schema.map((item,i)=>i===index?{...item,max_rows:Math.max(1,Math.min(50,Number(e.target.value)||20))}:item)}))} className="w-20 rounded-lg border border-white/10 bg-white/[0.04] px-2 py-1.5 text-xs text-white"/></div> : null}<NestedFieldsEditor fields={field.fields || []} types={types} onChange={(fields)=>setTypeDraft((d)=>({...d,schema:d.schema.map((item,i)=>i===index?{...item,fields}:item)}))}/></div> : null}<div className="sm:col-span-4"><input value={field.placeholder || ''} onChange={(e) => setTypeDraft((d) => ({ ...d, schema: d.schema.map((item, i) => i === index ? { ...item, placeholder: e.target.value } : item) }))} className="w-full rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-2 text-[11px] text-slate-300" placeholder="Optional helper / placeholder text" /></div></div>)}</div></div>{editingType && JSON.stringify(typeDraft.schema || []) !== JSON.stringify(editingType.schema || []) ? <div className="mt-4 rounded-2xl border border-amber-400/20 bg-amber-400/[0.06] p-3"><p className="text-xs font-bold text-amber-200">Template-safe schema update</p><p className="mt-1 text-xs leading-5 text-slate-400">Saving these field changes will not alter entry data or replace the current public design. Cosmic CMS will flag Single/Archive templates that should be updated with AI.</p></div> : null}<div className="mt-5 flex justify-end gap-2"><button onClick={() => setShowTypeModal(false)} className="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Cancel</button><button onClick={saveType} disabled={saving || !typeDraft.name.trim()} className="rounded-xl bg-violet-500 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Create type</button></div></div></div> : null}
    </div>;
}
