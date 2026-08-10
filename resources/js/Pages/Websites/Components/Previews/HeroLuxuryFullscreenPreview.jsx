import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroLuxuryFullscreenPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="editorial"
            badge="Luxury"
        />
    );
}
