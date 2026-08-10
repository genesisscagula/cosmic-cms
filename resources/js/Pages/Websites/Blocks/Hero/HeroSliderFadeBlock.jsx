import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { usePage } from '@inertiajs/react';
import { EditableImage } from '../Shared/EditableImage';
import { getEffectiveTheme } from '../../../../theme/Theme';
import { colorFamilies } from '../../../../theme/colorFamilies';
import { resolveMediaOverlay, effectiveMediaOverlayOpacity } from "../../../../theme/mediaOverlay";

const DEFAULT_SLIDES = [
    {
        image_url: '/storage/cms-images/background/background-1.avif',
        eyebrow: 'Built for what is next',
        heading: 'Make a confident first impression',
        description: 'Present your business with clear messaging, purposeful imagery, and a direct next step.',
        button_1_text: 'Get started',
        button_1_url: '#',
        button_2_text: 'Explore services',
        button_2_url: '#',
        button_3_text: 'View our work',
        button_3_url: '#',
        button_4_text: 'Learn more',
        button_4_url: '#',
    },
    {
        image_url: '/storage/cms-images/background/background-2.avif',
        eyebrow: 'Designed around your audience',
        heading: 'Turn attention into meaningful action',
        description: 'Guide visitors from their first impression to the information and action that matter most.',
        button_1_text: 'Explore services',
        button_1_url: '#',
        button_2_text: 'Our process',
        button_2_url: '#',
        button_3_text: 'Case studies',
        button_3_url: '#',
        button_4_text: 'See details',
        button_4_url: '#',
    },
    {
        image_url: '/storage/cms-images/background/background-3.avif',
        eyebrow: 'Ready when you are',
        heading: 'Build trust with every visit',
        description: 'Use focused content and a polished experience to make your business easier to choose.',
        button_1_text: 'Contact us',
        button_1_url: '#',
        button_2_text: 'Book a call',
        button_2_url: '#',
        button_3_text: 'View pricing',
        button_3_url: '#',
        button_4_text: 'Get started',
        button_4_url: '#',
    },
];

const normalizeSlide = (slide = {}) => ({
    image_url: slide.image_url || slide.image || slide.background_image || '',
    eyebrow: slide.eyebrow || '',
    heading: slide.heading || '',
    description: slide.description || '',
    button_1_text: slide.button_1_text || slide.button_text || '',
    button_1_url: slide.button_1_url || slide.button_url || '#',
    button_2_text: slide.button_2_text ?? 'Explore services',
    button_2_url: slide.button_2_url ?? '#',
    button_3_text: slide.button_3_text ?? 'View our work',
    button_3_url: slide.button_3_url ?? '#',
    button_4_text: slide.button_4_text ?? 'Learn more',
    button_4_url: slide.button_4_url ?? '#',
});

const emptySlide = () => ({
    image_url: '',
    eyebrow: 'New slide',
    heading: 'Add a clear, compelling headline',
    description: 'Describe the value visitors should understand from this slide.',
    button_1_text: 'Get started',
    button_1_url: '#',
    button_2_text: 'Explore more',
    button_2_url: '#',
    button_3_text: 'View our work',
    button_3_url: '#',
    button_4_text: 'Learn more',
    button_4_url: '#',
});

export const HeroSliderFadeSchema = {
    type: 'hero_slider_fade',
    theme: 'auto',
    category: 'hero',
    autoplay_interval: 6000,
    slides: DEFAULT_SLIDES,
};

const CTA_STYLES = [
    'bg-white text-slate-950 hover:bg-white/90',
    'border border-white/35 bg-black/20 text-white hover:border-white/60 hover:bg-black/35',
    'border border-white/35 bg-black/20 text-white hover:border-white/60 hover:bg-black/35',
    'border border-white/25 bg-black/35 text-white hover:border-white/50 hover:bg-black/55',
];

export default function HeroSliderFadeBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const normalizedGlobalTheme = typeof globalTheme === 'string' ? { primary: globalTheme } : (globalTheme || {});
    const primaryTheme = colorFamilies[normalizedGlobalTheme.primary] || colorFamilies.midnight;
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const data = { ...HeroSliderFadeSchema, ...(block || {}) };
    const resolvedTheme = block?.resolvedTheme || data?.resolvedTheme || data?.theme || 'surface';
    const mediaOverlay = resolveMediaOverlay(globalTheme, resolvedTheme);
    const isLightMediaTheme = mediaOverlay.isLight;
    const overlayColor = mediaOverlay.overlayColor;
    const sliderMediaStyle = isLightMediaTheme
        ? {
            overlayOpacity: 0.90,
            gradientX: 'from-white/100 via-white/96 to-white/82',
            gradientY: 'from-white/94 via-white/36 to-white/76',
            textWrap: 'text-slate-950',
            eyebrow: 'text-slate-700',
            body: 'text-slate-700',
            primary: `${primaryTheme.bg} text-white hover:opacity-90`,
            secondary: 'border border-slate-900/20 bg-white/78 text-slate-950 hover:bg-white/95',
        }
        : {
            overlayOpacity: 0.50,
            gradientX: 'from-slate-950/32 via-slate-950/12 to-transparent',
            gradientY: 'from-slate-950/48 via-transparent to-slate-950/12',
            textWrap: 'text-white',
            eyebrow: 'text-white/70',
            body: 'text-white/75',
            primary: 'bg-white text-slate-950 hover:bg-white/90',
            secondary: 'border border-white/35 bg-black/20 text-white hover:border-white/60 hover:bg-black/35',
        };
    const rawSlides = Array.isArray(data.slides) && data.slides.length ? data.slides : DEFAULT_SLIDES;
    const slides = useMemo(() => rawSlides.map(normalizeSlide), [rawSlides]);
    getEffectiveTheme(data.theme, globalTheme); // Keep parity with the shared block contract.
    const imageRefs = useRef([]);
    const [activeIndex, setActiveIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const [reducedMotion, setReducedMotion] = useState(false);
    const [editingIndex, setEditingIndex] = useState(null);
    const [draft, setDraft] = useState(null);

    useEffect(() => {
        const media = window.matchMedia('(prefers-reduced-motion: reduce)');
        const update = () => setReducedMotion(media.matches);
        update();
        media.addEventListener?.('change', update);
        return () => media.removeEventListener?.('change', update);
    }, []);

    useEffect(() => {
        if (activeIndex >= slides.length) setActiveIndex(Math.max(0, slides.length - 1));
    }, [activeIndex, slides.length]);

    useEffect(() => {
        if (paused || reducedMotion || slides.length < 2) return undefined;
        const timer = window.setInterval(
            () => setActiveIndex((current) => (current + 1) % slides.length),
            Math.max(3000, Number(data.autoplay_interval || data.interval) || 6000),
        );
        return () => window.clearInterval(timer);
    }, [data.autoplay_interval, data.interval, paused, reducedMotion, slides.length]);

    const updateSlides = (nextSlides) => onUpdate({ slides: nextSlides });
    const updateSlide = (index, patch) => updateSlides(slides.map((slide, slideIndex) => (
        slideIndex === index ? { ...slide, ...patch } : slide
    )));

    const openEditor = (index) => {
        setEditingIndex(index);
        setDraft({ ...slides[index] });
    };

    const closeEditor = () => {
        setEditingIndex(null);
        setDraft(null);
    };

    const saveEditor = () => {
        if (editingIndex === null || !draft) return;
        updateSlide(editingIndex, normalizeSlide(draft));
        closeEditor();
    };

    const addSlide = () => {
        const newSlide = emptySlide();
        const nextSlides = [...slides, newSlide];
        updateSlides(nextSlides);
        setActiveIndex(nextSlides.length - 1);
        setEditingIndex(nextSlides.length - 1);
        setDraft({ ...newSlide });
    };

    const deleteSlide = (index) => {
        if (slides.length <= 1) return;
        const nextSlides = slides.filter((_, slideIndex) => slideIndex !== index);
        updateSlides(nextSlides);
        setActiveIndex(Math.min(index, nextSlides.length - 1));
        closeEditor();
    };

    const previous = () => setActiveIndex((current) => (current - 1 + slides.length) % slides.length);
    const next = () => setActiveIndex((current) => (current + 1) % slides.length);
    const activeSlide = useMemo(() => slides[activeIndex] || slides[0], [activeIndex, slides]);
    const ctas = [1, 2, 3].map((number) => ({
        text: activeSlide[`button_${number}_text`],
        url: activeSlide[`button_${number}_url`] || '#',
    })).filter((cta) => cta.text);
    const floatingCta = activeSlide.button_4_text
        ? { text: activeSlide.button_4_text, url: activeSlide.button_4_url || '#' }
        : null;

    return (
        <section
            className="relative isolate min-h-[620px] overflow-hidden sm:min-h-[700px] lg:min-h-[760px]"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocusCapture={() => setPaused(true)}
            onBlurCapture={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget)) setPaused(false);
            }}
            aria-roledescription="carousel"
            aria-label="Featured content"
        >
            {slides.map((slide, index) => (
                <div
                    key={`hero-slider-${index}`}
                    className={`absolute inset-0 transition-opacity duration-700 ${index === activeIndex ? 'z-10 opacity-100' : 'z-0 opacity-0'}`}
                    aria-hidden={index !== activeIndex}
                >
                    <EditableImage
                        ref={(element) => { imageRefs.current[index] = element; }}
                        websiteId={websiteId}
                        blockIndex={blockIndex}
                        src={slide.image_url}
                        showOverlay={false}
                        isBackground
                        className="absolute inset-0 h-full w-full overflow-hidden"
                        onSave={(value) => updateSlide(index, { image_url: value })}
                    />
                </div>
            ))}

            <div className="absolute inset-0 z-20" style={{ backgroundColor: overlayColor, opacity: sliderMediaStyle.overlayOpacity }} />
            <div className={`absolute inset-0 z-20 bg-gradient-to-r ${sliderMediaStyle.gradientX}`} />
            <div className={`absolute inset-0 z-20 bg-gradient-to-t ${sliderMediaStyle.gradientY}`} />

            <div className="relative z-30 mx-auto flex min-h-[620px] max-w-7xl items-center px-6 py-24 sm:min-h-[700px] sm:px-10 lg:min-h-[760px] lg:px-14">
                <div className={`max-w-3xl ${sliderMediaStyle.textWrap}`} aria-live="polite">
                    {activeSlide.eyebrow && (
                        <p className={`mb-5 text-xs font-bold uppercase tracking-[0.32em] sm:text-sm ${sliderMediaStyle.eyebrow}`}>
                            {activeSlide.eyebrow}
                        </p>
                    )}
                    <h2 className="max-w-3xl text-5xl font-bold leading-[0.98] tracking-[-0.04em] sm:text-6xl lg:text-7xl">
                        {activeSlide.heading}
                    </h2>
                    <p className={`mt-7 max-w-2xl text-base leading-8 sm:text-lg ${sliderMediaStyle.body}`}>
                        {activeSlide.description}
                    </p>
                    <div className="mt-9 flex flex-wrap gap-3">
                        {ctas.map((cta, index) => (
                            <a
                                key={`hero-slider-cta-${index}`}
                                href={cta.url}
                                onClick={(event) => event.preventDefault()}
                                className={`rounded-full px-6 py-3.5 text-sm font-bold backdrop-blur transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 ${index === 0 ? sliderMediaStyle.primary : sliderMediaStyle.secondary}`}
                            >
                                {cta.text}
                            </a>
                        ))}
                    </div>
                </div>
            </div>

            <div className="absolute bottom-6 left-6 z-40 flex items-center gap-2 sm:left-10 lg:left-14" aria-label="Choose slide">
                {slides.map((_, index) => (
                    <button
                        key={`dot-${index}`}
                        type="button"
                        onClick={() => setActiveIndex(index)}
                        className={`h-2.5 rounded-full transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white ${index === activeIndex ? 'w-8 bg-white' : 'w-2.5 bg-white/45 hover:bg-white/70'}`}
                        aria-label={`Show slide ${index + 1}`}
                        aria-current={index === activeIndex ? 'true' : undefined}
                    />
                ))}
            </div>

            <div className="absolute bottom-16 left-1/2 z-40 flex -translate-x-1/2 items-center gap-2 sm:bottom-6">
                <button type="button" onClick={() => openEditor(activeIndex)} className="rounded-full border border-white/25 bg-black/35 px-4 py-2 text-xs font-bold text-white backdrop-blur hover:bg-black/55 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">Edit slide</button>
                <button type="button" onClick={() => imageRefs.current[activeIndex]?.openEditor()} className="rounded-full border border-white/25 bg-black/35 px-4 py-2 text-xs font-bold text-white backdrop-blur hover:bg-black/55 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">Edit image</button>
                <button type="button" onClick={addSlide} className="rounded-full border border-white/25 bg-black/35 px-4 py-2 text-xs font-bold text-white backdrop-blur hover:bg-black/55 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">Add slide</button>
            </div>

            <div className="absolute bottom-6 right-6 z-40 flex items-center gap-3 sm:right-10 lg:right-14">
                {floatingCta && (
                    <a
                        href={floatingCta.url}
                        onClick={(event) => event.preventDefault()}
                        className={`mr-1 rounded-full px-4 py-2 text-xs font-bold backdrop-blur transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-white ${CTA_STYLES[3]}`}
                    >
                        {floatingCta.text}
                    </a>
                )}
                <button type="button" onClick={previous} className="grid h-10 w-10 place-items-center rounded-full border border-white/25 bg-black/35 text-white backdrop-blur hover:bg-black/55 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Previous slide">←</button>
                <button type="button" onClick={next} className="grid h-10 w-10 place-items-center rounded-full border border-white/25 bg-black/35 text-white backdrop-blur hover:bg-black/55 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Next slide">→</button>
            </div>

            {editingIndex !== null && draft && createPortal(
                <div className="fixed inset-0 z-[10000] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) closeEditor(); }}>
                    <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-white/10 bg-[#151518] p-6 text-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="hero-slider-edit-title">
                        <div className="flex items-start justify-between gap-4">
                            <div><p className="text-xs font-bold uppercase tracking-[0.22em] text-violet-300">Hero slider</p><h3 id="hero-slider-edit-title" className="mt-1 text-xl font-bold">Edit slide {editingIndex + 1}</h3></div>
                            <button type="button" onClick={closeEditor} className="text-slate-400 hover:text-white" aria-label="Close editor">×</button>
                        </div>
                        <div className="mt-6 grid gap-4 sm:grid-cols-2">
                            {[
                                ['eyebrow', 'Eyebrow'],
                                ['heading', 'Heading'],
                                ['button_1_text', 'Button 1 text'], ['button_1_url', 'Button 1 URL'],
                                ['button_2_text', 'Button 2 text'], ['button_2_url', 'Button 2 URL'],
                                ['button_3_text', 'Button 3 text'], ['button_3_url', 'Button 3 URL'],
                                ['button_4_text', 'Button 4 text'], ['button_4_url', 'Button 4 URL'],
                            ].map(([key, label]) => (
                                <label key={key} className="grid gap-2 text-sm font-semibold text-slate-200"><span>{label}</span><input value={draft[key] || ''} onChange={(event) => setDraft({ ...draft, [key]: event.target.value })} className="rounded-lg border border-white/10 bg-black/25 px-3 py-2.5 text-white outline-none focus:border-violet-400" /></label>
                            ))}
                            <label className="grid gap-2 text-sm font-semibold text-slate-200 sm:col-span-2"><span>Description</span><textarea rows="4" value={draft.description || ''} onChange={(event) => setDraft({ ...draft, description: event.target.value })} className="resize-y rounded-lg border border-white/10 bg-black/25 px-3 py-2.5 text-white outline-none focus:border-violet-400" /></label>
                        </div>
                        <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-5">
                            <button type="button" onClick={() => deleteSlide(editingIndex)} disabled={slides.length <= 1} className="rounded-lg px-4 py-2 text-sm font-semibold text-rose-300 hover:bg-rose-500/10 disabled:cursor-not-allowed disabled:opacity-40">Delete slide</button>
                            <div className="flex gap-2"><button type="button" onClick={closeEditor} className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-white/5">Cancel</button><button type="button" onClick={saveEditor} className="rounded-lg bg-white px-5 py-2 text-sm font-bold text-slate-950 hover:bg-slate-100">Save slide</button></div>
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </section>
    );
}
