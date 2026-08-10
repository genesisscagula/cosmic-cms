import HeroPreviewShell from "./HeroPreviewShell";

export default function ImageCtaBannerPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="centered"
            badge="Image CTA"
        />
    );
}
