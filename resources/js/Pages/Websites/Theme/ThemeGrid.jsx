import ThemeCard from "./ThemeCard";

export default function ThemeGrid({
    themes,
    selectedTheme,
    onSelect,
    allowedThemeIds = [],
    nextPlan = null,
    hasLogo = false,
    brandMatchNeeded = false,
    onMatchBrandToLogo = null,
    brandMatchBusy = false
}) {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            {themes.map(theme => (

                <ThemeCard
                    key={theme.id}
                    theme={theme}
                    selected={selectedTheme === theme.id}
                    onSelect={onSelect}
                    // The backend permits a downgraded/grandfathered site to
                    // keep its current theme. Mirror that rule in the UI so the
                    // active theme never appears locked even if it is outside the
                    // account's current plan allowance.
                    locked={selectedTheme !== theme.id && !allowedThemeIds.includes(theme.id)}
                    nextPlan={nextPlan}
                    hasLogo={hasLogo}
                    brandMatchNeeded={brandMatchNeeded}
                    onMatchBrandToLogo={onMatchBrandToLogo}
                    brandMatchBusy={brandMatchBusy}
                />

            ))}

        </div>
    );
}