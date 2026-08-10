import ContentPreviewShell from "./ContentPreviewShell";

export default function PricingCardsPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="comparison"
            badge="Pricing"
        />
    );
}
