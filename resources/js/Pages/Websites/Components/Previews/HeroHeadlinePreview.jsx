import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroHeadlinePreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="centered"
            badge="Headline"
        />
    );
}
