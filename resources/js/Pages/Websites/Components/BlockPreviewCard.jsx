export default function BlockPreviewCard({
    onAdd,
    title,
    buttonLabel,
    payload,
    buttonClass,
    preview
}) {

    return (
        <div className="border border-slate-800 bg-slate-950 p-6 rounded-2xl space-y-4">
            {preview}
            <h4 className="text-lg font-bold text-white">{title}</h4>
            <button 
                onClick={() => onAdd(payload)}
                className={`w-full ${buttonClass} text-white py-3 rounded-xl font-bold transition`}
            >
                {buttonLabel}
            </button>
        </div>

    );

}