import { useMemo, useState } from "react";

import themeMetadata from "./ThemeMetadata";
import ThemeGrid from "./ThemeGrid";

export default function ThemeModal({
    open,
    onClose,
    selectedTheme,
    onSelect
}) {

    const [prompt, setPrompt] = useState("");
    const [search, setSearch] = useState("");

    const filteredThemes = useMemo(() => {

        const keyword = search.toLowerCase().trim();

        if (!keyword) {
            return themeMetadata;
        }

        return themeMetadata.filter(theme => (
            theme.name.toLowerCase().includes(keyword) ||
            theme.category.toLowerCase().includes(keyword) ||
            theme.id.toLowerCase().includes(keyword)
        ));

    }, [search]);

    if (!open) {
        return null;
    }

    const handleThemeSelect = (themeId) => {
        onSelect(themeId);
    };

    const handleAISuggest = () => {

        if (!prompt.trim()) {
            return;
        }

        // AI API will be connected next.
        console.log("AI Theme Prompt:", prompt);
    };

    const selectedThemeData = themeMetadata.find(
        theme => theme.id === selectedTheme
    );

    return (

        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4">

            {/* Overlay */}
            <button
                type="button"
                aria-label="Close theme modal"
                onClick={onClose}
                className="absolute inset-0 bg-black/70 backdrop-blur-sm"
            />

            {/* Modal */}
            <div
                className="
                    relative
                    w-full
                    max-w-6xl
                    max-h-[90vh]
                    bg-slate-950
                    border
                    border-slate-700
                    rounded-3xl
                    shadow-2xl
                    overflow-hidden
                    flex
                    flex-col
                    text-white
                "
            >

                {/* Header */}
                <div
                    className="
                        px-8
                        py-6
                        border-b
                        border-slate-800
                        bg-slate-900
                        flex
                        items-center
                        justify-between
                    "
                >

                    <div>

                        <div className="flex items-center gap-3">

                            <span className="text-2xl">
                                🎨
                            </span>

                            <h2 className="text-2xl font-extrabold text-white">
                                Design Assistant
                            </h2>

                        </div>

                        <p className="text-sm text-slate-400 mt-2">
                            Choose a theme or let Cosmic AI help find the perfect style.
                        </p>

                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="
                            w-11
                            h-11
                            rounded-xl
                            flex
                            items-center
                            justify-center
                            text-xl
                            text-slate-400
                            hover:text-white
                            hover:bg-slate-800
                            transition
                        "
                    >
                        ✕
                    </button>

                </div>


                {/* Scrollable Content */}
                <div className=" overflow-y-auto
                    [&::-webkit-scrollbar]:w-2
                    [&::-webkit-scrollbar-track]:bg-transparent
                    [&::-webkit-scrollbar-thumb]:rounded-full
                    [&::-webkit-scrollbar-thumb]:bg-slate-700
                    hover:[&::-webkit-scrollbar-thumb]:bg-slate-500">

                    {/* AI Advisor */}
                    <div
                        className="
                            px-8
                            py-8
                            border-b
                            border-slate-800
                            bg-slate-900/60
                        "
                    >

                        <div className="max-w-4xl mx-auto">

                            <div className="text-center mb-6">

                                <div
                                    className="
                                        text-xs
                                        uppercase
                                        tracking-[0.28em]
                                        font-bold
                                        text-violet-400
                                    "
                                >
                                    ✨ AI Theme Advisor
                                </div>

                                <h3 className="text-2xl font-extrabold text-white mt-3">
                                    What style are you looking for?
                                </h3>

                                <p className="text-sm text-slate-400 mt-2">
                                    Describe your business and AI will recommend the best themes.
                                </p>

                            </div>


                            {/* AI Prompt */}
                            <div
                                className="
                                    bg-slate-950
                                    rounded-2xl
                                    border
                                    border-slate-700
                                    shadow-xl
                                    p-2
                                    flex
                                    gap-2
                                    focus-within:border-violet-500
                                    focus-within:ring-2
                                    focus-within:ring-violet-500/10
                                    transition
                                "
                            >

                                <input
                                    type="text"
                                    value={prompt}
                                    onChange={(e) =>
                                        setPrompt(e.target.value)
                                    }
                                    onKeyDown={(e) => {
                                        if (e.key === "Enter") {
                                            handleAISuggest();
                                        }
                                    }}
                                    placeholder="e.g. Generate a modern elegant theme for a dental clinic..."
                                    className="
                                        flex-1
                                        bg-transparent
                                        border-0
                                        rounded-xl
                                        px-4
                                        py-3
                                        text-sm
                                        text-white
                                        placeholder:text-slate-500
                                        focus:ring-0
                                        focus:outline-none
                                    "
                                />

                                <button
                                    type="button"
                                    onClick={handleAISuggest}
                                    className="
                                        px-7
                                        py-3
                                        rounded-xl
                                        bg-gradient-to-r
                                        from-violet-600
                                        to-blue-600
                                        text-white
                                        font-bold
                                        text-sm
                                        shadow-lg
                                        shadow-violet-950/30
                                        hover:from-violet-500
                                        hover:to-blue-500
                                        hover:scale-[1.02]
                                        active:scale-[0.98]
                                        transition
                                    "
                                >
                                    ✨ Suggest Themes
                                </button>

                            </div>

                        </div>

                    </div>


                    {/* Explore Themes */}
                    <div className="p-8 bg-slate-950">

                        <div
                            className="
                                flex
                                flex-col
                                sm:flex-row
                                sm:items-center
                                justify-between
                                gap-4
                                mb-6
                            "
                        >

                            <div>

                                <h3 className="font-extrabold text-white text-lg">
                                    Explore Themes
                                </h3>

                                <p className="text-sm text-slate-400 mt-1">
                                    Select a theme to instantly preview it on your website.
                                </p>

                            </div>


                            {/* Search */}
                            <div className="relative">

                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) =>
                                        setSearch(e.target.value)
                                    }
                                    placeholder="Search themes..."
                                    className="
                                        w-full
                                        sm:w-72
                                        rounded-xl
                                        border
                                        border-slate-700
                                        bg-slate-900
                                        px-4
                                        py-3
                                        text-sm
                                        text-white
                                        placeholder:text-slate-500
                                        focus:outline-none
                                        focus:border-violet-500
                                        focus:ring-2
                                        focus:ring-violet-500/10
                                        transition
                                    "
                                />

                            </div>

                        </div>


                        <ThemeGrid
                            themes={filteredThemes}
                            selectedTheme={selectedTheme}
                            onSelect={handleThemeSelect}
                        />


                        {filteredThemes.length === 0 && (

                            <div
                                className="
                                    py-20
                                    text-center
                                    border
                                    border-dashed
                                    border-slate-800
                                    rounded-2xl
                                    text-slate-500
                                "
                            >
                                No themes found.
                            </div>

                        )}

                    </div>

                </div>


                {/* Footer */}
                <div
                    className="
                        px-8
                        py-4
                        border-t
                        border-slate-800
                        bg-slate-900
                        flex
                        items-center
                        justify-between
                    "
                >

                    <div className="flex items-center gap-3">

                        <span className="text-xs text-slate-500">
                            SELECTED THEME
                        </span>

                        {selectedThemeData?.colors && (

                            <div className="flex gap-1">

                                {selectedThemeData.colors.map(
                                    (color, index) => (

                                        <span
                                            key={index}
                                            className="
                                                w-3
                                                h-3
                                                rounded-full
                                                border
                                                border-white/10
                                            "
                                            style={{
                                                backgroundColor: color
                                            }}
                                        />

                                    )
                                )}

                            </div>

                        )}

                        <span className="text-sm font-bold text-white">
                            {selectedThemeData?.name || selectedTheme}
                        </span>

                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="
                            px-7
                            py-2.5
                            rounded-xl
                            bg-gradient-to-r
                            from-violet-600
                            to-blue-600
                            text-white
                            font-bold
                            text-sm
                            shadow-lg
                            hover:from-violet-500
                            hover:to-blue-500
                            transition
                        "
                    >
                        Done
                    </button>

                </div>

            </div>

        </div>

    );
}