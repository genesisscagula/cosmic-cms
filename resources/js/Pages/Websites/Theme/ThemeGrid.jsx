import ThemeCard from "./ThemeCard";

export default function ThemeGrid({
    themes,
    selectedTheme,
    onSelect,
    allowedThemeIds = [],
    nextPlan = null
}) {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            {themes.map(theme => (

                <ThemeCard
                    key={theme.id}
                    theme={theme}
                    selected={selectedTheme === theme.id}
                    onSelect={onSelect}
                    locked={!allowedThemeIds.includes(theme.id)}
                    nextPlan={nextPlan}
                />

            ))}

        </div>
    );
}