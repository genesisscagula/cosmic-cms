import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroFloatingCardsPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="bento"
            badge="Cards"
        />
    );
}
