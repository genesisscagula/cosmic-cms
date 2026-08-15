import ContentPreviewShell from "./ContentPreviewShell";
export default function PortfolioProjectTimelinePreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
  return <ContentPreviewShell previewVariant={previewVariant} websiteTheme={websiteTheme} pattern="timeline" imageSide="right" badge="Project Timeline" />;
}
