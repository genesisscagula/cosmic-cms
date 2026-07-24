export default function BlockPreviewCard({
    onAdd,
    title,
    buttonLabel,
    payload,
    buttonClass,
    preview: Preview,
}) {

    return (
        <div className="space-y-3 rounded-xl border border-white/10 bg-black/20 p-4 transition hover:border-violet-400/30 hover:bg-white/[0.03]">
            <Preview />

            <h4 className="text-sm font-semibold text-white">
                {title}
            </h4>

            <button
                type="button"
                aria-label={`Add ${title} block`}
                onClick={() => {
                    onAdd(payload);
                }}
                className={`w-full ${buttonClass} rounded-lg py-2 text-xs font-bold text-white transition focus:outline-none focus:ring-2 focus:ring-violet-300`}
            >
                {buttonLabel}
            </button>

        </div>
    );

}
