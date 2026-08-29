import { SparkFieldExtraSlots, useSparkFieldExtrasAnchor } from './SparkFieldExtrasRuntime';

function selectLunaTarget(event, detail) {
    event.stopPropagation();
    event.currentTarget.dispatchEvent(new CustomEvent('cosmic:luna-target', { detail, bubbles:true }));
}
export function EditableButton({ label, url = '#', className, style = undefined, fieldPath = null }) {
    const anchor = useSparkFieldExtrasAnchor({ value: label, mode: 'button', kind: 'button', fieldPath });
    const node = <span
        data-cosmic-luna-display="button"
        data-luna-target="button"
        data-cosmic-field-path={anchor?.target || undefined}
        data-cosmic-field-anchor-mode={anchor?.target ? 'editable' : undefined}
        className={`${className || ''}`}
        style={style}
    >{label || 'Get Started'}</span>;

    return <SparkFieldExtraSlots anchor={anchor}>{node}</SparkFieldExtraSlots>;
}
