import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroFloatingGlassPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="bento"
            badge="Glass"
        />
    );
}
