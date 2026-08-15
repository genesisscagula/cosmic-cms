import ContentPreviewShell from "./ContentPreviewShell";
export default function PortfolioBeforeAfterPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
  return <ContentPreviewShell previewVariant={previewVariant} websiteTheme={websiteTheme} pattern="split" imageSide="right" badge="Before / After" />;
}
