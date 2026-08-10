import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroEditorialOverlayPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="editorial"
            badge="Editorial"
        />
    );
}
