import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroVideoStylePreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="split"
            badge="Video"
        />
    );
}
