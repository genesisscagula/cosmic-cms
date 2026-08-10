import ContentPreviewShell from "./ContentPreviewShell";

export default function ServicesHoverCardsPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="hover"
            imageSide="left"
            badge="Capabilities"
        />
    );
}
