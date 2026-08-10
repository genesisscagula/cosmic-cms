import ContentPreviewShell from "./ContentPreviewShell";

export default function ContactFormPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="contact"
            badge="Contact"
        />
    );
}
