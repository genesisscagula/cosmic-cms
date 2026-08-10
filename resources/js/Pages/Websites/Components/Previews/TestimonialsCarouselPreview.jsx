import ContentPreviewShell from "./ContentPreviewShell";

export default function TestimonialsCarouselPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <ContentPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="testimonials"
            badge="Testimonials"
        />
    );
}
