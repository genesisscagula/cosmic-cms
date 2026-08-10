import ContentPreviewShell from "./ContentPreviewShell";

export default function ServicesFeatureComparisonPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="comparison"
            imageSide="left"
            badge="Compare"
        />
    );
}
