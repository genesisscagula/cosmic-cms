import { useAppearance } from './AppearanceContext';

const options = [
    { value: 'light', label: 'Light', icon: '☀' },
    { value: 'dark', label: 'Dark', icon: '☾' },
    { value: 'system', label: 'System', icon: '◐' },
];

export default function AppearanceSwitch({ compact = false }) {
    const { mode, setMode } = useAppearance();

    if (compact) {
        const current = options.find((option) => option.value === mode) ?? options[0];
        const next = options[(options.findIndex((option) => option.value === mode) + 1) % options.length];
        return (
            <button type="button" onClick={() => setMode(next.value)} className="cosmic-appearance-button cosmic-flat-icon" title={`Appearance: ${current.label}. Switch to ${next.label}.`}>
                <span aria-hidden="true">{current.icon}</span><span className="sr-only">Appearance: {current.label}</span>
            </button>
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
