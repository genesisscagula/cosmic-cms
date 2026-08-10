import ContentPreviewShell from "./ContentPreviewShell";

export default function ProcessTimelinePreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="timeline"
            badge="Process"
        />
    );
}
