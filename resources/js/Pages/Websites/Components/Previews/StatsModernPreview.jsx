import ContentPreviewShell from "./ContentPreviewShell";

export default function StatsModernPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="stats"
            badge="Stats"
        />
    );
}
