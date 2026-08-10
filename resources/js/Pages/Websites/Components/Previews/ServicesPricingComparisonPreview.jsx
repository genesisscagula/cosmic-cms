import ContentPreviewShell from "./ContentPreviewShell";

export default function ServicesPricingComparisonPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="comparison"
            imageSide="left"
            badge="Pricing"
        />
    );
}
