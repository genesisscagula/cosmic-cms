import { useState } from "react";
import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { EditableVideoSource } from "../Shared/EditableVideoSource";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
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

export function HeroVideoStyleBlock({
    block,
    blockIndex,
    onUpdate,
    globalTheme,
}) {
    const { requestedTheme, theme } = getHeroThemeState(block, globalTheme);

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

    const isPrimarySection = requestedTheme === "primary";

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
            className={`relative flex items-center overflow-hidden px-7 py-0 sm:px-10 lg:px-12 ${theme.bg} transition-colors duration-500`} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}
        >
            <div
                className={`pointer-events-none absolute -left-36 top-10 h-96 w-96 rounded-full ${primaryTheme.bg} opacity-[0.08] blur-[130px]`}
            />

            <div
                className={`pointer-events-none absolute -right-36 bottom-0 h-96 w-96 rounded-full ${primaryTheme.bg} opacity-[0.06] blur-[140px]`}
            />

            <div className="relative mx-auto grid w-full max-w-7xl items-center gap-14 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-20">
                <div className="max-w-2xl">
                    <EditableText
                        value={data.tagline}
                        className={`block text-xs font-semibold uppercase tracking-[0.3em] ${theme.sub}`}
                        onSave={(tagline) =>
                            onUpdate({ tagline })
                        }
                    />

                    <EditableText
                        value={data.heading} cosmicType="h1"
                        className={`mt-5 block text-4xl font-bold leading-[1.02] tracking-tight sm:text-5xl lg:text-6xl xl:text-7xl ${theme.text}`}
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
                        data-cosmic-no-luna-hover="true" className={`group relative overflow-hidden rounded-[2rem] border shadow-2xl ${theme.border}`}
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
                                cosmicType="badge"
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
            <EditableVideoSource
                value={data.video_url}
                posterImageUrl={data.image_url}
                title="Edit video"
                isOpen={isVideoDialogOpen}
                onSave={(video_url) => onUpdate({ video_url })}
                onClose={() => setIsVideoDialogOpen(false)}
            />
        )}
        </>
    );
}
