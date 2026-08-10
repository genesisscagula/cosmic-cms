import ContentPreviewShell from "./ContentPreviewShell";

export default function TeamModernPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="team"
            badge="Team"
        />
    );
}
