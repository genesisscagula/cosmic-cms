import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroSplitEditorialPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="split"
            badge="Editorial"
        />
    );
}
