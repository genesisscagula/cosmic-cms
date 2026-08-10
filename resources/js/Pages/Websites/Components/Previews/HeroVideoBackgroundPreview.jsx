import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroVideoBackgroundPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="cinematic"
            badge="Video"
        />
    );
}
