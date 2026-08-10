import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroBentoPremiumPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="bento"
            badge="Bento"
        />
    );
}
