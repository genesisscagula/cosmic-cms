import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroSaasDashboardPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="dashboard"
            badge="SaaS"
        />
    );
}
