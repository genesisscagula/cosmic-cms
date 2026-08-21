function selectLunaTarget(event, detail) {
    event.stopPropagation();
    event.currentTarget.dispatchEvent(new CustomEvent('cosmic:luna-target', { detail, bubbles:true }));
}
export function EditableButton({ label, url = '#', className, style = undefined }) {
    return <span
        data-cosmic-luna-display="button"
        data-luna-target="button"
        className={`${className || ''} cursor-pointer`}
        style={style}
        onClick={(event)=>selectLunaTarget(event,{type:'button',currentValue:String(label||''),url:String(url||'#')})}
    >{label || 'Get Started'}</span>;
}
