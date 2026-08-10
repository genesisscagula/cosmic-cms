import ContentPreviewShell from "./ContentPreviewShell";

export default function ServicesBentoPremiumPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="bento"
            imageSide="left"
            badge="Premium Services"
        />
    );
}
