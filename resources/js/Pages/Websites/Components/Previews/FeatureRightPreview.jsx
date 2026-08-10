import ContentPreviewShell from "./ContentPreviewShell";

export default function FeatureRightPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="feature"
            imageSide="right"
            badge="Feature"
        />
    );
}
