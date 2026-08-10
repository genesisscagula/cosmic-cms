import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroCenteredPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="centered"
            badge="CTA"
        />
    );
}
