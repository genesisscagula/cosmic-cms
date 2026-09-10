import { createContext, useContext } from 'react';
import { SparkFieldExtraSlots, useSparkFieldExtrasAnchor } from './SparkFieldExtrasRuntime';

// Authored marketplace previews supply their own typography through utility classes.
export const AuthoredTypographyContext = createContext(false);

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
export function EditableText({ value, className, style = undefined, cosmicType = null, fieldPath = null, onSave: _onSave, isTextArea: _isTextArea, placeholder: _placeholder, ...rest }) {
    const authoredTypography = useContext(AuthoredTypographyContext);
    const kind = lunaTextKind(className);
    const anchor = useSparkFieldExtrasAnchor({ value, mode: 'text', kind, fieldPath });
    const node = <span
        data-cosmic-luna-display="text"
        data-luna-target={kind}
        data-cosmic-field-path={anchor?.target || undefined}
        data-cosmic-field-anchor-mode={anchor?.target ? 'editable' : undefined}
        {...rest}
        data-cosmic-type={authoredTypography ? undefined : (cosmicType || rest["data-cosmic-type"] || undefined)}
        className={`${className || ''}`}
        style={style}
    >{value || 'Click to add text'}</span>;

    return <SparkFieldExtraSlots anchor={anchor}>{node}</SparkFieldExtraSlots>;
}
