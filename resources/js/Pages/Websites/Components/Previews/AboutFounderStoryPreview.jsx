import ContentPreviewShell from "./ContentPreviewShell";
export default function AboutFounderStoryPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
  return <ContentPreviewShell previewVariant={previewVariant} websiteTheme={websiteTheme} pattern="editorial" imageSide="left" badge="Premium About" />;
}
