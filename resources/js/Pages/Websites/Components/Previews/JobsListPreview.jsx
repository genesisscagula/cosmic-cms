import ContentPreviewShell from "./ContentPreviewShell";

export default function JobsListPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="list"
            badge="Careers"
        />
    );
}
