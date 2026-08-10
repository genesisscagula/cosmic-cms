import ContentPreviewShell from "./ContentPreviewShell";

export default function FaqAccordionPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="accordion"
            badge="FAQ"
        />
    );
}
