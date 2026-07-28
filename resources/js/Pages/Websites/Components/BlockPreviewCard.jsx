export default function BlockPreviewCard({
    onAdd,
    title,
    buttonLabel,
    payload,
    buttonClass,
    preview: Preview,
}) {

    return (
        <div className="flex h-full min-h-[15.5rem] flex-col rounded-xl border border-white/10 bg-black/20 p-4 transition hover:border-violet-400/30 hover:bg-white/[0.03]">
            <div className="h-40 shrink-0 overflow-hidden rounded-xl [&>*]:h-full">
                <Preview />
            </div>

            <h4 className="mt-3 min-h-5 text-sm font-semibold leading-5 text-white">
                {title}
            </h4>

            <button
                type="button"
                aria-label={`Add ${title} block`}
                onClick={() => {
                    onAdd(payload);
                }}
                className={`mt-3 w-full ${buttonClass} rounded-lg py-2 text-xs font-bold text-white transition focus:outline-none focus:ring-2 focus:ring-violet-300`}
            >
                {buttonLabel}
            </button>

        </div>
    );

}
