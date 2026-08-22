function lunaTextKind(className = '') {
    const classes = String(className || '');
    if (/text-(?:4xl|5xl|6xl|7xl|8xl|9xl)|text-\[.*rem\]/.test(classes)) return 'heading';
    if (/text-(?:2xl|3xl)/.test(classes)) return 'heading';
    if (/uppercase|tracking-\[/.test(classes)) return 'label';
    if (/text-(?:xs|sm)/.test(classes)) return 'label';
    return 'text';
}
function selectLunaTarget(event, detail) {
    event.stopPropagation();
    event.currentTarget.dispatchEvent(new CustomEvent('cosmic:luna-target', { detail, bubbles:true }));
}
export function EditableText({ value, className, style = undefined, cosmicType = null, onSave: _onSave, isTextArea: _isTextArea, placeholder: _placeholder, ...rest }) {
    const kind = lunaTextKind(className);
    return <span
        data-cosmic-luna-display="text"
        data-luna-target={kind}
        data-cosmic-type={cosmicType || rest["data-cosmic-type"] || undefined}
        {...rest}
        className={`${className || ''}`}
        style={style}
    >{value || 'Click to add text'}</span>;
}
