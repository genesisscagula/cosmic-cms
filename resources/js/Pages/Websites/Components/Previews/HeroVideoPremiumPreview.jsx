import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroVideoPremiumPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="cinematic"
            badge="Premium Video"
        />
    );
}
