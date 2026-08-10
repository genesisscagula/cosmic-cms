import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroParallaxPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="cinematic"
            badge="Parallax"
        />
    );
}
