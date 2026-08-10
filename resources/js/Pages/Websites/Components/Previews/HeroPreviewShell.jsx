import { colorFamilies } from "../../../../theme/colorFamilies";

const safePalette = (websiteTheme) => {
    const themeId = typeof websiteTheme === "string"
        ? websiteTheme
        : (websiteTheme?.primary || websiteTheme?.base_family || "midnight");

    const customPalette = websiteTheme?.custom_brand_theme?.palette || websiteTheme?.palette || null;
    const family = colorFamilies[themeId] || colorFamilies.midnight || {};
    const palette = customPalette || family.palette || {};

    return {
        primary: palette.background || palette.primary || "#243447",
        surface: palette.surface || "#30475E",
        accent: palette.accent || "#60A5FA",
        text: palette.text || "#F8FAFC",
    };
};

const lightText = "#0F172A";
const lightMuted = "#64748B";
const white = "#FFFFFF";

export function resolveHeroPreviewTheme(websiteTheme, previewVariant = "primary") {
    const palette = safePalette(websiteTheme);

    if (previewVariant === "white") {
        return {
            background: white,
            surface: "#F1F5F9",
            text: lightText,
            muted: lightMuted,
            accent: palette.accent,
            border: "#E2E8F0",
            button: palette.primary,
            buttonText: white,
        };
    }

    if (previewVariant === "surface") {
        return {
            background: palette.surface,
            surface: palette.primary,
            text: palette.text,
            muted: palette.text,
            accent: palette.accent,
            border: "rgba(255,255,255,.18)",
            button: palette.accent,
            buttonText: lightText,
        };
    }

    return {
        background: palette.primary,
        surface: palette.surface,
        text: palette.text,
        muted: palette.text,
        accent: palette.accent,
        border: "rgba(255,255,255,.16)",
        button: white,
        buttonText: lightText,
    };
}

const patternLayouts = {
    centered: "items-center text-center",
    split: "items-center text-left",
    editorial: "items-end text-left",
    bento: "items-stretch text-left",
    dashboard: "items-center text-left",
    cinematic: "items-end text-left",
};

export default function HeroPreviewShell({
    previewVariant = "primary",
    websiteTheme = "midnight",
    pattern = "centered",
    badge = "Hero",
}) {
    const theme = resolveHeroPreviewTheme(websiteTheme, previewVariant);
    const split = ["split", "dashboard"].includes(pattern);
    const bento = pattern === "bento";
    const cinematic = ["cinematic", "editorial"].includes(pattern);

    return (
        <div
            className="relative h-full min-h-[158px] overflow-hidden rounded-xl border"
            style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}
        >
            <div
                className="absolute -right-10 -top-10 h-28 w-28 rounded-full blur-3xl"
                style={{ backgroundColor: `${theme.accent}38` }}
            />
            <div
                className="absolute -bottom-12 -left-8 h-24 w-24 rounded-full blur-3xl"
                style={{ backgroundColor: `${theme.surface}88` }}
            />

            <div className={`relative flex h-full gap-4 px-5 py-5 ${patternLayouts[pattern] || patternLayouts.centered}`}>
                <div className={`${split ? "w-[56%]" : bento ? "w-[58%]" : "w-full"} ${pattern === "centered" ? "mx-auto max-w-[230px]" : ""}`}>
                    <div className={`mb-3 flex ${pattern === "centered" ? "justify-center" : "justify-start"}`}>
                        <span
                            className="rounded-full border px-2 py-1 text-[8px] font-bold uppercase tracking-[0.18em]"
                            style={{ borderColor: theme.border, backgroundColor: `${theme.surface}66`, color: theme.muted }}
                        >
                            {badge}
                        </span>
                    </div>

                    <div
                        className={`h-3 rounded ${pattern === "centered" ? "mx-auto w-4/5" : "w-full"}`}
                        style={{ backgroundColor: theme.text, opacity: .92 }}
                    />
                    <div
                        className={`mt-2 h-3 rounded ${pattern === "centered" ? "mx-auto w-3/5" : "w-4/5"}`}
                        style={{ backgroundColor: theme.text, opacity: .78 }}
                    />
                    <div
                        className={`mt-3 h-1.5 rounded ${pattern === "centered" ? "mx-auto w-4/5" : "w-11/12"}`}
                        style={{ backgroundColor: theme.muted, opacity: .42 }}
                    />

                    <div className={`mt-4 flex gap-2 ${pattern === "centered" ? "justify-center" : ""}`}>
                        <span className="h-6 w-16 rounded-full" style={{ backgroundColor: theme.button }} />
                        <span className="h-6 w-14 rounded-full border" style={{ borderColor: theme.border, backgroundColor: `${theme.surface}66` }} />
                    </div>
                </div>

                {split && (
                    <div
                        className="relative h-full min-h-[100px] flex-1 overflow-hidden rounded-lg border"
                        style={{ backgroundColor: theme.surface, borderColor: theme.border }}
                    >
                        <div className="absolute inset-3 rounded-md border" style={{ borderColor: theme.border, backgroundColor: `${theme.accent}18` }} />
                        <span className="absolute bottom-3 left-3 h-2 w-10 rounded-full" style={{ backgroundColor: theme.accent }} />
                    </div>
                )}

                {bento && (
                    <div className="grid flex-1 grid-cols-2 gap-2">
                        <div className="col-span-2 rounded-lg border" style={{ backgroundColor: theme.surface, borderColor: theme.border }} />
                        <div className="rounded-lg border" style={{ backgroundColor: `${theme.accent}26`, borderColor: theme.border }} />
                        <div className="rounded-lg border" style={{ backgroundColor: theme.surface, borderColor: theme.border }} />
                    </div>
                )}
            </div>

            {cinematic && (
                <div className="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-black/25 to-transparent" />
            )}
        </div>
    );
}
