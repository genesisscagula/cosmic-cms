import { useState } from "react";

import ThemeModal from "./ThemeModal";
import themeMetadata from "./ThemeMetadata";

export default function ThemeSelector({
    value,
    onChange
}) {

    const [open, setOpen] = useState(false);

    const currentTheme =
        themeMetadata.find(
            theme => theme.id === value
        );

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="
                    min-w-[260px]
                    flex
                    items-center
                    justify-between
                    gap-4
                    bg-slate-100
                    hover:bg-slate-200
                    border
                    border-slate-300
                    rounded-xl
                    px-4
                    py-2.5
                    transition
                "
            >

                <div className="flex items-center gap-3">

                    <span>
                        🎨
                    </span>

                    <div className="text-left">

                        <div className="text-[9px] uppercase tracking-wider text-slate-400 font-bold">
                            Theme
                        </div>

                        <div className="font-semibold text-slate-700">
                            {currentTheme?.name || value}
                        </div>

                    </div>

                </div>

                <span className="text-slate-400">
                    ▼
                </span>

            </button>


            <ThemeModal
                open={open}
                onClose={() => setOpen(false)}
                selectedTheme={value}
                onSelect={onChange}
            />

        </>
    );
}