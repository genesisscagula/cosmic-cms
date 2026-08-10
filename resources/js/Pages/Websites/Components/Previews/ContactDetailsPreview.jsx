import ContentPreviewShell from "./ContentPreviewShell";

export default function ContactDetailsPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="details"
            badge="Contact"
        />
    );
}
