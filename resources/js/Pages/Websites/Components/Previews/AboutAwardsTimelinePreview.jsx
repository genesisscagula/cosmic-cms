import ContentPreviewShell from "./ContentPreviewShell";
export default function AboutAwardsTimelinePreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
  return <ContentPreviewShell previewVariant={previewVariant} websiteTheme={websiteTheme} pattern="timeline" imageSide="left" badge="Premium About" />;
}
