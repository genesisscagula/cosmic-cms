import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroSliderFadePreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="cinematic"
            badge="Slider"
        />
    );
}
