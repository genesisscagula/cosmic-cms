import ContentPreviewShell from "./ContentPreviewShell";

export default function CaseStudiesGridPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="collection"
            badge="Case Studies"
        />
    );
}
