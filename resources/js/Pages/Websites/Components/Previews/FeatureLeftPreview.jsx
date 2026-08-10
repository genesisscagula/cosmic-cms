import ContentPreviewShell from "./ContentPreviewShell";

export default function FeatureLeftPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="feature"
            imageSide="left"
            badge="Feature"
        />
    );
}
