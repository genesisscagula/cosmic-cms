import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";

export const HeroVideoStyleSchema = {
    type: "hero_video_style",
    title: "Hero Video Style",
    category: "Hero",
    purpose: "Introduce a business with strong editorial copy and a prominent video-style visual.",
    description: "A modern split hero with editable actions, a large video thumbnail, a play button, and a compact supporting label.",
    tags: ["hero", "video", "image", "play", "cta", "landing"],
    defaults: {
        theme: "auto",
        tagline: "SEE WHAT SETS US APART",
        heading: "A clear vision for what comes next.",
        text: "Introduce your business with a strong message, a compelling visual, and a simple path for visitors to learn more.",
        primary_label: "Get started",
        primary_url: "#",
        video_label: "Watch our story",
        video_url: "#",
        play_label: "Play video",
        image_badge: "Discover our approach",
        image_url: "/storage/cms-images/background/background-1.avif",
    },
};

function getVideoEmbedUrl(videoUrl) {
    if (!videoUrl || videoUrl === "#" || videoUrl.startsWith("/")) {
        return null;
    }

    try {
        const parsedUrl = new URL(videoUrl);
        const host = parsedUrl.hostname.toLowerCase().replace(/^www\./, "");
        let videoId = null;

        if (host === "youtu.be") {
            videoId = parsedUrl.pathname.split("/").filter(Boolean)[0];
        } else if (host === "youtube.com" || host === "m.youtube.com") {
            videoId = parsedUrl.searchParams.get("v") || parsedUrl.pathname.match(/\/(?:embed|shorts)\/([^/?]+)/)?.[1];
        }

        if (videoId && /^[A-Za-z0-9_-]{6,}$/.test(videoId)) {
            return `https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1&mute=1&loop=1&playlist=${videoId}&controls=1&playsinline=1&rel=0&modestbranding=1`;
        }

        if (host === "vimeo.com" || host.endsWith(".vimeo.com")) {
            const videoId = parsedUrl.pathname.match(/\/(\d+)/)?.[1];

            if (videoId) {
                return `https://player.vimeo.com/video/${videoId}?autoplay=1&muted=1&loop=1&title=0&byline=0&portrait=0`;
            }
        }
    } catch {
        // Direct video URLs are rendered by the native video element.
    }

    return null;
}

function hasVideoUrl(videoUrl) {
    return Boolean(videoUrl && videoUrl !== "#");
}

function VideoSourceDialog({ value, posterImageUrl, onSave, onClose }) {
    const [videoUrl, setVideoUrl] = useState(value === "#" ? "" : value || "");
    const previewUrl = videoUrl.trim();
    const embedUrl = getVideoEmbedUrl(previewUrl);

    useEffect(() => {
        setVideoUrl(value === "#" ? "" : value || "");
    }, [value]);

    return createPortal(
        <div className="fixed inset-0 z-[999999] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm" onMouseDown={onClose}>
            <div role="dialog" aria-modal="true" aria-labelledby="video-style-dialog-title" className="w-full max-w-3xl overflow-hidden rounded-2xl border border-white/10 bg-[#17181c] text-left shadow-2xl" onMouseDown={(event) => event.stopPropagation()}>
                <div className="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4 sm:px-6">
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-violet-300">Video media</p>
                        <h3 id="video-style-dialog-title" className="mt-1 text-lg font-semibold text-white">Preview and edit video</h3>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/10 hover:text-white" aria-label="Close video dialog">×</button>
                </div>

                <div className="space-y-4 p-5 sm:p-6">
                    <label className="block">
                        <span className="text-xs font-medium text-slate-300">Video URL</span>
                        <input autoFocus type="url" value={videoUrl} onChange={(event) => setVideoUrl(event.target.value)} placeholder="https://www.youtube.com/watch?v=..." className="mt-2 w-full rounded-xl border border-white/10 bg-black/25 px-3 py-3 text-sm text-white outline-none placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" />
                    </label>

                    <div className="aspect-video overflow-hidden rounded-xl border border-white/10 bg-black">
                        {embedUrl ? (
                            <iframe key={embedUrl} src={embedUrl} title="Video preview" allow="autoplay; fullscreen; picture-in-picture" className="h-full w-full border-0" />
                        ) : hasVideoUrl(previewUrl) ? (
                            <video key={previewUrl} autoPlay muted loop controls playsInline poster={posterImageUrl} className="h-full w-full object-cover">
                                <source src={previewUrl} type="video/mp4" />
                            </video>
                        ) : (
                            <div className="relative flex h-full items-center justify-center">
                                <img src={posterImageUrl} alt="Video placeholder" className="absolute inset-0 h-full w-full object-cover opacity-45" />
                                <p className="relative rounded-full border border-white/15 bg-black/45 px-4 py-2 text-sm text-slate-200">Paste a YouTube, Vimeo, or direct MP4 link to preview it here.</p>
                            </div>
                        )}
                    </div>

                    <p className="text-xs leading-5 text-slate-500">The published site plays a valid video automatically. This editor is only shown inside the Builder.</p>

                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={onClose} className="rounded-lg px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white">Cancel</button>
                        <button type="button" onClick={() => { onSave(previewUrl || "#"); onClose(); }} className="rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-200">Save video</button>
                    </div>
                </div>
            </div>
        </div>,
        document.body
    );
}

export function HeroVideoStyleBlock({
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

    const data = {
        ...HeroVideoStyleSchema.defaults,
        ...block,
    };

    const [isVideoDialogOpen, setIsVideoDialogOpen] = useState(false);

    const { props } = usePage();

    const websiteId =
        props.page?.website_id ||
        props.website?.id;

    const isPrimarySection =
        block.resolvedTheme === "primary";

    const primaryButtonStyle = isPrimarySection
        ? {
              bg: "bg-white",
              text: "text-slate-950",
          }
        : {
              bg: primaryTheme.bg,
              text: primaryTheme.text,
          };

    return (
        <>
        <section
            className={`relative overflow-hidden px-7 py-16 sm:px-10 sm:py-20 lg:px-12 lg:py-24 ${theme.bg} transition-colors duration-500`}
        >
            <div
                className={`pointer-events-none absolute -left-36 top-10 h-96 w-96 rounded-full ${primaryTheme.bg} opacity-[0.08] blur-[130px]`}
            />

            <div
                className={`pointer-events-none absolute -right-36 bottom-0 h-96 w-96 rounded-full ${primaryTheme.bg} opacity-[0.06] blur-[140px]`}
            />

            <div className="relative mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-20">
                <div className="max-w-2xl">
                    <EditableText
                        value={data.tagline}
                        className={`block text-xs font-semibold uppercase tracking-[0.3em] ${theme.sub}`}
                        onSave={(tagline) =>
                            onUpdate({ tagline })
                        }
                    />

                    <EditableText
                        value={data.heading}
                        className={`mt-5 block text-5xl font-black leading-[1.02] tracking-tight sm:text-6xl lg:text-7xl ${theme.text}`}
                        onSave={(heading) =>
                            onUpdate({ heading })
                        }
                    />

                    <EditableText
                        value={data.text}
                        isTextArea
                        className={`mt-6 block max-w-xl text-base leading-7 sm:text-lg sm:leading-8 ${theme.sub}`}
                        onSave={(text) =>
                            onUpdate({ text })
                        }
                    />

                    <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <EditableButton
                            label={data.primary_label}
                            url={data.primary_url}
                            className={`inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold transition hover:opacity-90 ${primaryButtonStyle.bg} ${primaryButtonStyle.text}`}
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
                            label={data.video_label}
                            url={data.video_url}
                            className={`inline-flex min-h-[50px] items-center justify-center gap-3 rounded-full border px-7 font-bold transition hover:opacity-80 ${theme.border} ${theme.text}`}
                            onSave={(
                                video_label,
                                video_url
                            ) =>
                                onUpdate({
                                    video_label,
                                    video_url,
                                })
                            }
                        />
                    </div>

                    <div className={`mt-8 flex items-center gap-3 border-t pt-5 ${theme.border}`}>
                        <div
                            className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${primaryTheme.bg} ${primaryTheme.text}`}
                        >
                            ▶
                        </div>

                        <EditableText
                            value={data.play_label}
                            className={`block text-sm font-semibold ${theme.sub}`}
                            onSave={(play_label) =>
                                onUpdate({ play_label })
                            }
                        />
                    </div>
                </div>

                <div className="relative mx-auto w-full max-w-2xl pb-10 sm:px-6 lg:pb-0">
                    <div
                        className={`group relative overflow-hidden rounded-[2rem] border shadow-2xl ${theme.border}`}
                    >
                        <EditableImage
                            websiteId={websiteId}
                            blockIndex={blockIndex}
                            src={data.image_url}
                            className="aspect-video w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                            onSave={(image_url) =>
                                onUpdate({ image_url })
                            }
                        />

                        <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/70 via-slate-950/10 to-slate-950/10" />

                        <button
                            type="button"
                            onClick={() => setIsVideoDialogOpen(true)}
                            aria-label={data.play_label}
                            className="absolute left-1/2 top-1/2 z-20 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center"
                        >
                            <span
                                className={`flex h-20 w-20 items-center justify-center rounded-full border-4 border-white/30 bg-white text-2xl text-slate-950 shadow-2xl transition duration-300 group-hover:scale-110 sm:h-24 sm:w-24`}
                            >
                                ▶
                            </span>
                        </button>

                        <div className="absolute bottom-5 left-5 right-5 flex items-end justify-between gap-4">
                            <EditableText
                                value={data.image_badge}
                                className="block max-w-[70%] text-sm font-semibold text-white sm:text-base"
                                onSave={(image_badge) =>
                                    onUpdate({ image_badge })
                                }
                            />

                            <span className="rounded-full border border-white/20 bg-slate-950/60 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-white backdrop-blur">
                                Video
                            </span>
                        </div>
                    </div>

                    <div
                        className={`absolute -bottom-3 right-0 rounded-2xl border px-5 py-4 shadow-xl backdrop-blur sm:right-2 ${theme.card} ${theme.border}`}
                    >
                        <div className="flex items-center gap-3">
                            <div
                                className={`flex h-9 w-9 items-center justify-center rounded-full ${primaryTheme.bg} ${primaryTheme.text}`}
                            >
                                ▶
                            </div>

                            <div>
                                <EditableText
                                    value={data.video_label}
                                    className={`block text-sm font-bold ${theme.text}`}
                                    onSave={(video_label) =>
                                        onUpdate({
                                            video_label,
                                        })
                                    }
                                />

                                <EditableText
                                    value={data.play_label}
                                    className={`mt-0.5 block text-xs ${theme.sub}`}
                                    onSave={(play_label) =>
                                        onUpdate({
                                            play_label,
                                        })
                                    }
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        {isVideoDialogOpen && (
            <VideoSourceDialog
                value={data.video_url}
                posterImageUrl={data.image_url}
                onSave={(video_url) => onUpdate({ video_url })}
                onClose={() => setIsVideoDialogOpen(false)}
            />
        )}
        </>
    );
}
