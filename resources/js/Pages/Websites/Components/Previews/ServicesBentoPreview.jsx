import ContentPreviewShell from "./ContentPreviewShell";

export default function ServicesBentoPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="bento"
            imageSide="left"
            badge="Services"
        />
    );
}
