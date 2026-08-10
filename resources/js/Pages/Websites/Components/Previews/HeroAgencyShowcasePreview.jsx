import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroAgencyShowcasePreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="split"
            badge="Agency"
        />
    );
}
