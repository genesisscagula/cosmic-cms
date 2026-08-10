import HeroPreviewShell from "./HeroPreviewShell";

export default function HeroAiConversationPreview({ previewVariant = "primary", websiteTheme = "midnight" }) {
    return (
        <HeroPreviewShell
            previewVariant={previewVariant}
            websiteTheme={websiteTheme}
            pattern="split"
            badge="AI"
        />
    );
}
