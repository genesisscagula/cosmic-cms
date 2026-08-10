import ContentPreviewShell from "./ContentPreviewShell";

export default function EventsGridPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="collection"
            badge="Events"
        />
    );
}
