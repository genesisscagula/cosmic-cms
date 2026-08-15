import ContentPreviewShell from "./ContentPreviewShell";
export default function PortfolioAnimatedPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
  return <ContentPreviewShell previewVariant={previewVariant} websiteTheme={websiteTheme} pattern="editorial" imageSide="right" badge="Animated Portfolio" />;
}
