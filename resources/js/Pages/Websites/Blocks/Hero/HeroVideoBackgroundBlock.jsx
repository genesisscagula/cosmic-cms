import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";

export const HeroVideoBackgroundSchema = {
    type: "hero_video_background",
    title: "Hero Video Background",
    category: "Hero",
    purpose: "Create an immersive first impression with a full-width autoplay background video.",
    description: "A cinematic hero with an autoplaying muted video, dark readability overlay, editable content, two actions, and a fallback poster image.",
    tags: [
        "hero",
        "video",
        "background",
        "autoplay",
        "cinematic",
        "cta",
    ],
    defaults: {
        theme: "auto",
        tagline: "STEP INTO THE EXPERIENCE",
        heading: "Make every first impression unforgettable.",
        text: "Introduce your business through motion, strong storytelling, and a clear next step for every visitor.",
        primary_label: "Get started",
        primary_url: "#",
        secondary_label: "Explore more",
        secondary_url: "#",
        video_url: "/storage/cms-videos/hero-placeholder.mp4",
        poster_image_url: "/storage/cms-images/background/background-1.avif",
        video_badge: "Discover what makes us different",
        scroll_label: "Explore",
    },
};

function getBackgroundVideoEmbedUrl(videoUrl) {
    if (!videoUrl || videoUrl.startsWith("/")) {
        return null;
    }

    try {
        const parsedUrl = new URL(videoUrl);
        const host = parsedUrl.hostname.toLowerCase().replace(/^www\./, "");
        let videoId = null;

        if (host === "youtu.be") {
            videoId = parsedUrl.pathname.split("/").filter(Boolean)[0];
        } else if (host === "youtube.com" || host === "m.youtube.com") {
            videoId = parsedUrl.searchParams.get("v");

            if (!videoId) {
                const match = parsedUrl.pathname.match(/\/(?:embed|shorts)\/([^/?]+)/);
                videoId = match?.[1];
            }
        }

        if (videoId && /^[A-Za-z0-9_-]{6,}$/.test(videoId)) {
            return `https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1&mute=1&loop=1&playlist=${videoId}&controls=0&playsinline=1&rel=0&modestbranding=1`;
        }

        if (host === "vimeo.com" || host.endsWith(".vimeo.com")) {
            const match = parsedUrl.pathname.match(/\/(\d+)/);

            if (match?.[1]) {
                return `https://player.vimeo.com/video/${match[1]}?autoplay=1&muted=1&loop=1&background=1&title=0&byline=0&portrait=0`;
            }
        }
    } catch {
        // Relative storage video paths are handled by the native video element.
    }

    return null;
}

function EditableVideoSource({ value, onSave, isOpen, onClose }) {
    const [videoUrl, setVideoUrl] = useState(value || "");

    useEffect(() => {
        if (isOpen) {
            setVideoUrl(value || "");
        }
    }, [isOpen, value]);

    const close = () => {
        setVideoUrl(value || "");
        onClose();
    };

    return (
        <>
            {isOpen && createPortal(
                <div
                    className="fixed inset-0 z-[999999] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm"
                    role="presentation"
                    onMouseDown={close}
                >
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="background-video-title"
                        className="w-full max-w-lg rounded-2xl border border-white/10 bg-[#17181c] p-6 text-left shadow-2xl"
                        onMouseDown={(event) => event.stopPropagation()}
                    >
                        <div className="flex items-start justify-between gap-5">
                            <div>
                                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-violet-300">Background media</p>
                                <h3 id="background-video-title" className="mt-2 text-xl font-semibold text-white">Edit background video</h3>
                                <p className="mt-2 text-sm leading-6 text-slate-400">
                                    Paste a direct MP4, YouTube, or Vimeo link. YouTube and Vimeo play muted in the background.
                                </p>
                            </div>
                            <button type="button" onClick={close} className="rounded-lg p-1 text-slate-400 transition hover:bg-white/5 hover:text-white" aria-label="Close video editor">
                                ×
                            </button>
                        </div>

                        <label className="mt-6 block">
                            <span className="text-xs font-medium text-slate-300">Video URL</span>
                            <input
                                autoFocus
                                type="url"
                                value={videoUrl}
                                onChange={(event) => setVideoUrl(event.target.value)}
                                placeholder="https://www.youtube.com/watch?v=..."
                                className="mt-2 w-full rounded-xl border border-white/10 bg-black/25 px-3 py-3 text-sm text-white outline-none placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20"
                            />
                        </label>

                        <p className="mt-3 text-xs leading-5 text-slate-500">
                            Use a direct .mp4 URL for a self-hosted video. The poster image remains editable from the thumbnail.
                        </p>

                        <div className="mt-6 flex justify-end gap-3">
                            <button type="button" onClick={close} className="rounded-lg px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white">Cancel</button>
                            <button
                                type="button"
                                disabled={!videoUrl.trim()}
                                onClick={() => {
                                    onSave(videoUrl.trim());
                                    onClose();
                                }}
                                className="rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Save video
                            </button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </>
    );
}

export function HeroVideoBackgroundBlock({
    block,
    blockIndex,
    onUpdate,
    globalTheme,
}) {
    const theme = getEffectiveTheme(
        block.resolvedTheme,
        globalTheme
    );

    const primaryTheme =
        colorFamilies[globalTheme?.primary] ||
        colorFamilies.emerald;
    const isLightMediaTheme = ["white", "surface", "stone"].includes(block.resolvedTheme);
    const mediaStyle = isLightMediaTheme
        ? {
            overlay: "bg-white opacity-[0.72]",
            gradientX: "from-white/95 via-white/65 to-white/20",
            gradientY: "from-white/65 via-transparent to-white/20",
            tagline: "text-slate-700",
            heading: "text-slate-950",
            body: "text-slate-700",
            secondary: "border-slate-900/20 bg-white/50 text-slate-950 hover:bg-white/75",
            pill: "border-slate-900/15 bg-white/55",
            pillText: "text-slate-900",
            editButton: "border-slate-900/15 bg-white/55 text-slate-800 hover:bg-white/80 hover:text-slate-950 focus-visible:ring-slate-900/50",
            scroll: "text-slate-700",
            scrollBorder: "border-slate-900/30",
            scrollDot: "bg-slate-900",
            mediaCard: "border-slate-900/15 bg-white/45",
        }
        : {
            overlay: `${primaryTheme.bg} opacity-[0.58]`,
            gradientX: "from-slate-950/70 via-slate-950/35 to-transparent",
            gradientY: "from-slate-950/55 via-transparent to-slate-950/15",
            tagline: "text-white/70",
            heading: "text-white",
            body: "text-white/75",
            secondary: "border-white/30 bg-white/10 text-white hover:bg-white/20",
            pill: "border-white/15 bg-slate-950/35",
            pillText: "text-white",
            editButton: "border-white/15 bg-slate-950/35 text-white/80 hover:bg-slate-950/55 hover:text-white focus-visible:ring-white/80",
            scroll: "text-white/70",
            scrollBorder: "border-white/30",
            scrollDot: "bg-white",
            mediaCard: "border-white/20 bg-slate-950/35",
        };

    const data = {
        ...HeroVideoBackgroundSchema.defaults,
        ...block,
    };

    const videoUrl = data.video_url || HeroVideoBackgroundSchema.defaults.video_url;
    const posterImageUrl = data.poster_image_url || HeroVideoBackgroundSchema.defaults.poster_image_url;
    const embeddedVideoUrl = getBackgroundVideoEmbedUrl(videoUrl);
    const [isVideoEditorOpen, setIsVideoEditorOpen] = useState(false);
    const openVideoEditor = (event) => {
        event.preventDefault();
        event.stopPropagation();
        setIsVideoEditorOpen(true);
    };

    const { props } = usePage();

    const websiteId =
        props.page?.website_id ||
        props.website?.id;

    return (
        <section
            className={`relative isolate min-h-[680px] cursor-pointer overflow-hidden ${theme.bg}`}
        >
            <div className="absolute inset-0">
                {embeddedVideoUrl ? (
                    <div className="absolute inset-0 overflow-hidden">
                        <iframe
                            key={embeddedVideoUrl}
                            src={embeddedVideoUrl}
                            title="Background video"
                            allow="autoplay; fullscreen; picture-in-picture"
                            className="pointer-events-none absolute left-1/2 top-1/2 h-[56.25vw] min-h-full w-[177.78vh] min-w-full -translate-x-1/2 -translate-y-1/2 border-0"
                        />
                    </div>
                ) : (
                    <video
                        key={videoUrl}
                        autoPlay
                        muted
                        loop
                        playsInline
                        preload="metadata"
                        poster={posterImageUrl}
                        className="h-full w-full object-cover"
                    >
                        <source
                            src={videoUrl}
                            type="video/mp4"
                        />
                    </video>
                )}

                {/* Keep the video readable while letting the website's active primary family tint the hero. */}
                <div
                    className={`absolute inset-0 ${mediaStyle.overlay}`}
                />

                <div
                    className={`absolute inset-0 bg-gradient-to-r ${mediaStyle.gradientX}`}
                />

                <div className={`absolute inset-0 bg-gradient-to-t ${mediaStyle.gradientY}`} />
            </div>

            <button
                type="button"
                aria-label="Edit background video"
                onPointerDown={openVideoEditor}
                className="absolute inset-0 z-[5] cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-violet-300"
            />

            <div
                className={`pointer-events-none absolute -left-40 top-16 h-96 w-96 rounded-full ${primaryTheme.bg} opacity-[0.18] blur-[150px]`}
            />

            <div className="pointer-events-none relative z-10 mx-auto flex min-h-[680px] max-w-7xl items-center px-7 py-24 sm:px-10 lg:px-12">
                <div className="pointer-events-auto max-w-3xl">
                    <EditableText
                        value={data.tagline}
                        className={`block text-xs font-semibold uppercase tracking-[0.34em] ${mediaStyle.tagline}`}
                        onSave={(tagline) =>
                            onUpdate({ tagline })
                        }
                    />

                    <EditableText
                        value={data.heading}
                        className={`mt-6 block text-5xl font-bold leading-[0.98] tracking-tight sm:text-6xl lg:text-8xl ${mediaStyle.heading}`}
                        onSave={(heading) =>
                            onUpdate({ heading })
                        }
                    />

                    <EditableText
                        value={data.text}
                        isTextArea
                        className={`mt-7 block max-w-2xl text-base leading-7 sm:text-lg sm:leading-8 ${mediaStyle.body}`}
                        onSave={(text) =>
                            onUpdate({ text })
                        }
                    />

                    <div className="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <EditableButton
                            label={data.primary_label}
                            url={data.primary_url}
                            className={`inline-flex min-h-[52px] items-center justify-center rounded-full px-8 font-bold shadow-xl transition hover:-translate-y-0.5 hover:opacity-90 ${primaryTheme.bg} ${primaryTheme.text}`}
                            onSave={(
                                primary_label,
                                primary_url
                            ) =>
                                onUpdate({
                                    primary_label,
                                    primary_url,
                                })
                            }
                        />

                        <EditableButton
                            label={data.secondary_label}
                            url={data.secondary_url}
                            className={`inline-flex min-h-[52px] items-center justify-center rounded-full border px-8 font-bold backdrop-blur transition ${mediaStyle.secondary}`}
                            onSave={(
                                secondary_label,
                                secondary_url
                            ) =>
                                onUpdate({
                                    secondary_label,
                                    secondary_url,
                                })
                            }
                        />
                    </div>

                    <div className="mt-10 flex flex-wrap items-center gap-4">
                        <div className={`flex items-center gap-3 rounded-full border px-4 py-2.5 backdrop-blur ${mediaStyle.pill}`}>
                            <span
                                className={`flex h-8 w-8 items-center justify-center rounded-full ${primaryTheme.bg} ${primaryTheme.text}`}
                            >
                                ▶
                            </span>

                            <EditableText
                                value={data.video_badge}
                                className={`block text-sm font-semibold ${mediaStyle.pillText}`}
                                onSave={(video_badge) =>
                                    onUpdate({
                                        video_badge,
                                    })
                                }
                            />
                        </div>

                        <button
                            type="button"
                            onClick={() => setIsVideoEditorOpen(true)}
                            className={`rounded-full border px-4 py-2.5 text-sm font-semibold backdrop-blur transition focus-visible:outline-none focus-visible:ring-2 ${mediaStyle.editButton}`}
                        >
                            Edit video
                        </button>
                    </div>
                </div>
            </div>

            <div className="pointer-events-none absolute bottom-0 left-0 right-0 z-10">
                <div className="mx-auto flex max-w-7xl items-end justify-between gap-6 px-7 pb-7 sm:px-10 lg:px-12">
                    <div className={`pointer-events-auto flex items-center gap-3 ${mediaStyle.scroll}`}>
                        <span className={`flex h-9 w-6 items-start justify-center rounded-full border p-1.5 ${mediaStyle.scrollBorder}`}>
                            <span className={`h-1.5 w-1.5 rounded-full ${mediaStyle.scrollDot}`} />
                        </span>

                        <EditableText
                            value={data.scroll_label}
                            className="block text-xs font-semibold uppercase tracking-[0.24em]"
                            onSave={(scroll_label) =>
                                onUpdate({
                                    scroll_label,
                                })
                            }
                        />
                    </div>

                    <div data-editable-media className={`pointer-events-auto hidden w-48 overflow-hidden rounded-2xl border shadow-2xl backdrop-blur sm:block ${mediaStyle.mediaCard}`}>
                        <EditableImage
                            websiteId={websiteId}
                            blockIndex={blockIndex}
                            src={posterImageUrl}
                            className="aspect-video w-full object-cover opacity-80"
                            onSave={(poster_image_url) =>
                                onUpdate({
                                    poster_image_url,
                                })
                            }
                        />
                    </div>
                </div>
            </div>

            <EditableVideoSource
                value={block.video_url || ""}
                isOpen={isVideoEditorOpen}
                onClose={() => setIsVideoEditorOpen(false)}
                onSave={(video_url) => onUpdate({ video_url })}
            />
        </section>
    );
}
