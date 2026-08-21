function selectLunaTarget(event, detail) {
    event.stopPropagation();
    event.currentTarget.dispatchEvent(new CustomEvent('cosmic:luna-target', { detail, bubbles:true }));
}
export function EditableButton({ label, url = '#', className, style = undefined }) {
    return <span
        data-cosmic-luna-display="button"
        data-luna-target="button"
        className={`${className || ''}`}
        style={style}
    >{label || 'Get Started'}</span>;
}
