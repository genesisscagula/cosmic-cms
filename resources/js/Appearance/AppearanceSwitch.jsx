import { useAppearance } from './AppearanceContext';

const options = [
    { value: 'light', label: 'Light', icon: '☀' },
    { value: 'dark', label: 'Dark', icon: '☾' },
    { value: 'system', label: 'System', icon: '◐' },
];

export default function AppearanceSwitch({ compact = false }) {
    const { mode, resolvedTheme, setMode } = useAppearance();

    if (compact) {
        const activeTheme = resolvedTheme === 'dark' ? 'dark' : 'light';
        return (
            <div className={`cosmic-appearance-toggle is-${activeTheme}`} role="group" aria-label="Appearance">
                <span className="cosmic-appearance-toggle-thumb" aria-hidden="true" />
                <button type="button" onClick={() => setMode('light')} aria-pressed={activeTheme === 'light'} className={activeTheme === 'light' ? 'is-active is-light' : 'is-light'} title="Light mode">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
                    <span className="sr-only">Light mode</span>
                </button>
                <button type="button" onClick={() => setMode('dark')} aria-pressed={activeTheme === 'dark'} className={activeTheme === 'dark' ? 'is-active is-dark' : 'is-dark'} title="Dark mode">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z"/></svg>
                    <span className="sr-only">Dark mode</span>
                </button>
            </div>
        );
    }

    return (
        <div className="cosmic-appearance-segment" role="group" aria-label="Appearance">
            {options.map((option) => (
                <button key={option.value} type="button" onClick={() => setMode(option.value)} aria-pressed={mode === option.value} className={mode === option.value ? 'is-active' : ''}>
                    <span aria-hidden="true">{option.icon}</span><span>{option.label}</span>
                </button>
            ))}
        </div>
    );
}
