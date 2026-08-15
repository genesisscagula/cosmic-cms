import ContentPreviewShell from "./ContentPreviewShell";
export default function PortfolioCaseStudyPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
  return <ContentPreviewShell previewVariant={previewVariant} websiteTheme={websiteTheme} pattern="editorial" imageSide="right" badge="Premium Portfolio" />;
}
