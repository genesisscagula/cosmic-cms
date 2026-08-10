import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroSplitImagePreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="split"
            badge="Split"
        />
    );
}
