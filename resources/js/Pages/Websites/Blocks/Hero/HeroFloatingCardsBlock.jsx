import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { sparkTw } from "../Shared/sparkTailwindRuntime";

export const HeroFloatingCardsSchema = {
    type: "hero_floating_cards",
    title: "Hero Floating Cards",
    category: "Hero",
    purpose: "Introduce a business with bold copy, a prominent image, and floating proof or service cards.",
    description: "A modern hero with editable actions, a large visual, and layered floating cards for trust, services, or business highlights.",
    tags: ["hero", "floating", "cards", "image", "cta", "modern"],
    defaults: {
        theme: "auto",
        tagline: "BUILT AROUND YOUR NEXT STEP",
        heading: "A better way to move your business forward.",
        text: "Present your strongest message, highlight what makes your business different, and help visitors take action with confidence.",
        primary_label: "Get started",
        primary_url: "#",
        secondary_label: "Explore services",
        secondary_url: "#",
        image_url: "/storage/cms-images/background/background-1.avif",
        image_badge: "Professional service you can rely on",
        card_one_value: "15+",
        card_one_label: "Years of experience",
        card_two_title: "Trusted expertise",
        card_two_text: "Thoughtful service, clear communication, and dependable results.",
    },
};

export function HeroFloatingCardsBlock({
    block,
    blockIndex,
    onUpdate,
    globalTheme,
}) {
    const { requestedTheme, theme } = getHeroThemeState(block, globalTheme);
    const primaryTheme =
        colorFamilies[globalTheme?.primary] || colorFamilies.emerald;

    const data = {
        ...HeroFloatingCardsSchema.defaults,
        ...block,
    };

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
        <section
            className={sparkTw(block, "section", `relative flex items-center overflow-hidden px-7 py-0 sm:px-10 lg:px-12 ${theme.bg} transition-colors duration-500`)} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}
        >
            <div
                className={sparkTw(block, "wrapper", `pointer-events-none absolute -left-40 top-10 h-96 w-96 rounded-full ${primaryTheme.bg} opacity-[0.08] blur-[130px]`)}
            />

            <div
                className={sparkTw(block, "wrapper_2", `pointer-events-none absolute -right-44 bottom-0 h-96 w-96 rounded-full ${primaryTheme.bg} opacity-[0.06] blur-[140px]`)}
            />

            <div className={sparkTw(block, "wrapper_3", "relative mx-auto grid w-full max-w-7xl items-center gap-14 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-20")}>
                <div className={sparkTw(block, "wrapper_4", "max-w-2xl")}>
                    <EditableText
                        value={data.tagline}
                        className={sparkTw(block, "text", `block text-xs font-semibold uppercase tracking-[0.3em] ${theme.sub}`)}
                        onSave={(tagline) =>
                            onUpdate({ tagline })
                        }
                    />

                    <EditableText
                        value={data.heading} cosmicType="h1"
                        className={sparkTw(block, "text_2", `mt-5 block text-4xl font-bold leading-[1.02] tracking-tight sm:text-5xl lg:text-6xl xl:text-7xl ${theme.text}`)}
                        onSave={(heading) =>
                            onUpdate({ heading })
                        }
                    />

                    <EditableText
                        value={data.text}
                        isTextArea
                        className={sparkTw(block, "text_3", `mt-6 block max-w-xl text-base leading-7 sm:text-lg sm:leading-8 ${theme.sub}`)}
                        onSave={(text) =>
                            onUpdate({ text })
                        }
                    />

                    <div className={sparkTw(block, "wrapper_5", "mt-8 flex flex-col gap-3 sm:flex-row sm:items-center")}>
                        <EditableButton
                            label={data.primary_label}
                            url={data.primary_url}
                            className={sparkTw(block, "button", `inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold transition hover:opacity-90 ${primaryButtonStyle.bg} ${primaryButtonStyle.text}`)}
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
                            className={sparkTw(block, "button_2", `inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold transition hover:opacity-80 ${theme.border} ${theme.text}`)}
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
                </div>

                <div className={sparkTw(block, "wrapper_6", "relative mx-auto w-full max-w-2xl pb-16 pt-4 sm:px-8 lg:pb-10")}>
                    <div
                        className={sparkTw(block, "wrapper_7", `relative overflow-hidden rounded-[2rem] border shadow-2xl ${theme.border}`)}
                    >
                        <EditableImage
                            websiteId={websiteId}
                            blockIndex={blockIndex}
                            src={data.image_url}
                            className={sparkTw(block, "image", "aspect-[4/3] w-full object-cover")}
                            onSave={(image_url) =>
                                onUpdate({ image_url })
                            }
                        />

                        <div className={sparkTw(block, "wrapper_8", "pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/40 via-transparent to-transparent")} />

                        <EditableText
                            value={data.image_badge}
                            className={sparkTw(block, "text_4", "absolute bottom-5 left-5 max-w-[calc(100%-2.5rem)] rounded-full bg-slate-950/80 px-4 py-2 text-xs font-semibold text-white backdrop-blur")}
                            onSave={(image_badge) =>
                                onUpdate({ image_badge })
                            }
                        />
                    </div>

                    <div
                        className={sparkTw(block, "wrapper_9", `absolute -bottom-1 left-0 w-[170px] rounded-2xl border p-4 shadow-xl backdrop-blur sm:left-1 sm:w-[190px] ${theme.card} ${theme.border}`)}
                    >
                        <EditableText
                            value={data.card_one_value}
                            className={sparkTw(block, "text_5", `block text-3xl font-bold tracking-tight ${theme.text}`)}
                            onSave={(card_one_value) =>
                                onUpdate({
                                    card_one_value,
                                })
                            }
                        />

                        <EditableText
                            value={data.card_one_label}
                            className={sparkTw(block, "text_6", `mt-1 block text-xs font-semibold leading-5 ${theme.sub}`)}
                            onSave={(card_one_label) =>
                                onUpdate({
                                    card_one_label,
                                })
                            }
                        />
                    </div>

                    <div
                        className={sparkTw(block, "wrapper_10", `absolute -right-1 top-0 w-[205px] rounded-2xl border p-4 shadow-xl backdrop-blur sm:right-0 sm:w-[225px] ${theme.card} ${theme.border}`)}
                    >
                        <div
                            className={sparkTw(block, "wrapper_11", `mb-3 flex h-9 w-9 items-center justify-center rounded-xl ${primaryTheme.bg} ${primaryTheme.text}`)}
                        >
                            ✓
                        </div>

                        <EditableText
                            value={data.card_two_title}
                            className={sparkTw(block, "text_7", `block text-sm font-bold ${theme.text}`)}
                            onSave={(card_two_title) =>
                                onUpdate({
                                    card_two_title,
                                })
                            }
                        />

                        <EditableText
                            value={data.card_two_text}
                            isTextArea
                            className={sparkTw(block, "text_8", `mt-1.5 block text-xs leading-5 ${theme.sub}`)}
                            onSave={(card_two_text) =>
                                onUpdate({
                                    card_two_text,
                                })
                            }
                        />
                    </div>
                </div>
            </div>
        </section>
    );
}
