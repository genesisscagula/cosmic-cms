import ContentPreviewShell from "./ContentPreviewShell";
export default function PortfolioFilterablePreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
  return <ContentPreviewShell previewVariant={previewVariant} websiteTheme={websiteTheme} pattern="grid" imageSide="right" badge="Filterable Portfolio" />;
}
