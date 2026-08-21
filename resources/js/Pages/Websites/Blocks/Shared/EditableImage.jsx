import { forwardRef, useImperativeHandle } from "react";

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
    style = undefined
}, ref) => {
    useImperativeHandle(ref, () => ({ openEditor() {} }), []);
    const detail={type:isBackground?'background image':'image',currentValue:String(src||''),imageQuery:String(imageQuery||''),blockType:String(blockType||'')};
    if (!src) {
        return <div data-cosmic-luna-display="image" data-luna-target="image" className={`${className || ''} bg-slate-200/60`} style={style} />;
    }
    return (
        <div data-cosmic-luna-display="image" data-luna-target="image" className={`${isBackground ? '' : 'relative'} ${className || ''}`} style={style}>
            <img src={src} alt="" className="h-full w-full object-cover pointer-events-none" />
        </div>
    );
});
