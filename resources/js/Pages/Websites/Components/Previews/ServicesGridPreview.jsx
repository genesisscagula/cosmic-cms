import ContentPreviewShell from "./ContentPreviewShell";

export default function ServicesGridPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="cards"
            imageSide="left"
            badge="Services"
        />
    );
}
