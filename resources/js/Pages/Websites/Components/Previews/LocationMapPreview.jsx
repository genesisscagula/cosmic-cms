import ContentPreviewShell from "./ContentPreviewShell";

export default function LocationMapPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="map"
            badge="Location"
        />
    );
}
