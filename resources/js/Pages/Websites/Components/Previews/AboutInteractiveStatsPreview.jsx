import ContentPreviewShell from "./ContentPreviewShell";
export default function AboutInteractiveStatsPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
  return <ContentPreviewShell previewVariant={previewVariant} websiteTheme={websiteTheme} pattern="stats" imageSide="left" badge="Premium About" />;
}
