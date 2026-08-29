import { forwardRef, useImperativeHandle } from "react";
import { SparkFieldExtraSlots, useSparkFieldExtrasAnchor } from './SparkFieldExtrasRuntime';

function selectLunaTarget(event, detail) {
    event.stopPropagation();
    event.currentTarget.dispatchEvent(new CustomEvent('cosmic:luna-target', { detail, bubbles:true }));
}

export const EditableImage = forwardRef(({
    className,
    src,
    isBackground = false,
    imageQuery = '',
    blockType = '',
    fieldPath = null,
    style = undefined
}, ref) => {
    useImperativeHandle(ref, () => ({ openEditor() {} }), []);
    const anchor = useSparkFieldExtrasAnchor({ value: src, mode: 'image', kind: isBackground ? 'background image' : 'image', fieldPath });
    const detail={type:isBackground?'background image':'image',currentValue:String(src||''),imageQuery:String(imageQuery||''),blockType:String(blockType||'')};
    const common = {
        'data-cosmic-luna-display': 'image',
        'data-luna-target': isBackground ? 'background image' : 'image',
        'data-cosmic-background-media': isBackground ? 'true' : undefined,
        'data-cosmic-field-path': anchor?.target || undefined,
        'data-cosmic-field-anchor-mode': anchor?.target ? 'editable' : undefined,
    };

    const node = !src
        ? <div {...common} className={`${className || ''} bg-slate-200/60`} style={style} />
        : <div {...common} className={`${isBackground ? '' : 'relative'} ${className || ''}`} style={style}>
            <img src={src} alt="" className="h-full w-full object-cover pointer-events-none" />
        </div>;

    return <SparkFieldExtraSlots anchor={anchor}>{node}</SparkFieldExtraSlots>;
});
