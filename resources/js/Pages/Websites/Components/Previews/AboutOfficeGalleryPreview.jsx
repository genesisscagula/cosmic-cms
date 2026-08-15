import ContentPreviewShell from "./ContentPreviewShell";
export default function AboutOfficeGalleryPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
  return <ContentPreviewShell previewVariant={previewVariant} websiteTheme={websiteTheme} pattern="gallery" imageSide="left" badge="Premium About" />;
}
