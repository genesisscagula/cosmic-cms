export default function ThemeCard({
    theme,
    selected,
    onSelect
}) {

    return (

        <button
            type="button"
            onClick={() => onSelect(theme.id)}
            className={`
                group
                text-left
                rounded-2xl
                border
                overflow-hidden
                bg-slate-900
                transition-all
                duration-200

                hover:-translate-y-1
                hover:shadow-2xl
                hover:shadow-black/30

                ${
                    selected
                        ? `
                            border-violet-500
                            ring-2
                            ring-violet-500/30
                            shadow-xl
                            shadow-violet-950/30
                          `
                        : `
                            border-slate-800
                            hover:border-slate-600
                          `
                }
            `}
        >

            {/* Website Mini Preview */}

            <div
                className="
                    h-28
                    p-4
                    relative
                    overflow-hidden
                "
                style={{
                    backgroundColor: theme.colors[0]
                }}
            >

                


                {/* Selected overlay */}

                {selected && (

                    <div
                        className="
                            absolute
                            top-3
                            right-3
                            w-7
                            h-7
                            rounded-full
                            bg-violet-600
                            text-white
                            flex
                            items-center
                            justify-center
                            text-sm
                            font-bold
                            shadow-lg
                        "
                    >
                        ✓
                    </div>

                )}

            </div>


            {/* Theme Information */}

            <div
                className={`
                    p-4
                    border-t
                    transition

                    ${
                        selected
                            ? "bg-violet-950/20 border-violet-500/30"
                            : "bg-slate-900 border-slate-800"
                    }
                `}
            >

                <div className="flex items-start justify-between gap-3">

                    <div>

                        <div className="font-bold text-white">
                            {theme.name}
                        </div>

                        <div className="text-xs text-slate-500 mt-1">
                            {theme.category}
                        </div>

                    </div>


                    {selected && (

                        <span
                            className="
                                text-[10px]
                                uppercase
                                tracking-wider
                                font-bold
                                text-violet-400
                            "
                        >
                            Selected
                        </span>

                    )}

                </div>


                {/* Color Palette */}


            </div>

        </button>

    );
}