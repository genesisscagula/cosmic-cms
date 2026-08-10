import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroBackgroundImagePreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="cinematic"
            badge="Image"
        />
    );
}
