import { resolveHeroPreviewTheme } from "./HeroPreviewShell";

export default function ContentPreviewShell({
    previewVariant = "primary",
    websiteTheme = "midnight",
    pattern = "cards",
    imageSide = "left",
    badge = "Section",
}) {
    const theme = resolveHeroPreviewTheme(websiteTheme, previewVariant);

    const Header = () => (
        <div className="mb-3">
            <div className="flex items-center justify-between gap-3">
                <span
                    className="rounded-full border px-2 py-1 text-[7px] font-bold uppercase tracking-[0.16em]"
                    style={{ borderColor: theme.border, backgroundColor: `${theme.surface}66`, color: theme.muted }}
                >
                    {badge}
                </span>
                <span className="h-1.5 w-10 rounded-full" style={{ backgroundColor: theme.accent }} />
            </div>
            <div className="mt-2 h-3 w-3/5 rounded" style={{ backgroundColor: theme.text, opacity: .9 }} />
            <div className="mt-2 h-1.5 w-4/5 rounded" style={{ backgroundColor: theme.muted, opacity: .35 }} />
        </div>
    );

    const Card = ({ active = false }) => (
        <div
            className="rounded-lg border p-2.5"
            style={{
                borderColor: active ? theme.accent : theme.border,
                backgroundColor: active ? `${theme.accent}22` : theme.surface,
            }}
        >
            <div className="h-5 w-5 rounded-md" style={{ backgroundColor: active ? theme.accent : `${theme.text}18` }} />
            <div className="mt-3 h-2 w-4/5 rounded" style={{ backgroundColor: theme.text, opacity: .78 }} />
            <div className="mt-2 h-1.5 w-full rounded" style={{ backgroundColor: theme.muted, opacity: .28 }} />
            <div className="mt-1 h-1.5 w-2/3 rounded" style={{ backgroundColor: theme.muted, opacity: .18 }} />
        </div>
    );


    if (pattern === "stats") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <Header />
                <div className="grid grid-cols-4 overflow-hidden rounded-lg border" style={{ borderColor: theme.border }}>
                    {[0,1,2,3].map((item) => (
                        <div key={item} className="p-3" style={{ backgroundColor: item % 2 ? `${theme.surface}CC` : theme.surface }}>
                            <div className="h-3 w-10 rounded" style={{ backgroundColor: theme.text, opacity: .86 }} />
                            <div className="mt-2 h-1.5 w-full rounded" style={{ backgroundColor: theme.muted, opacity: .28 }} />
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (pattern === "timeline") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <Header />
                <div className="grid grid-cols-4 gap-2">
                    {[0,1,2,3].map((item) => (
                        <div key={item} className="rounded-lg border p-2.5" style={{ backgroundColor: theme.surface, borderColor: theme.border }}>
                            <div className="text-[9px] font-bold" style={{ color: theme.accent }}>0{item + 1}</div>
                            <div className="mt-3 h-2 w-4/5 rounded" style={{ backgroundColor: theme.text, opacity: .76 }} />
                            <div className="mt-2 h-1.5 w-full rounded" style={{ backgroundColor: theme.muted, opacity: .24 }} />
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (pattern === "testimonials") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <Header />
                <div className="grid grid-cols-3 gap-2">
                    {[0,1,2].map((item) => (
                        <div key={item} className="rounded-lg border p-2.5" style={{ backgroundColor: theme.surface, borderColor: theme.border }}>
                            <div className="text-[8px] tracking-[0.08em]" style={{ color: theme.accent }}>★★★★★</div>
                            <div className="mt-3 h-1.5 w-full rounded" style={{ backgroundColor: theme.muted, opacity: .34 }} />
                            <div className="mt-1.5 h-1.5 w-4/5 rounded" style={{ backgroundColor: theme.muted, opacity: .22 }} />
                            <div className="mt-4 flex items-center gap-2">
                                <div className="h-5 w-5 rounded-full" style={{ backgroundColor: `${theme.accent}44` }} />
                                <div className="h-1.5 w-10 rounded" style={{ backgroundColor: theme.text, opacity: .5 }} />
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (pattern === "accordion") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <Header />
                <div className="space-y-2">
                    {[0,1,2].map((item) => (
                        <div key={item} className="flex items-center justify-between rounded-lg border px-3 py-2.5" style={{ backgroundColor: theme.surface, borderColor: theme.border }}>
                            <div className="h-1.5 w-3/5 rounded" style={{ backgroundColor: theme.text, opacity: .56 }} />
                            <span className="text-xs font-bold" style={{ color: theme.accent }}>+</span>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (pattern === "team") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <Header />
                <div className="grid grid-cols-4 gap-2">
                    {[0,1,2,3].map((item) => (
                        <div key={item} className="overflow-hidden rounded-lg border" style={{ backgroundColor: theme.surface, borderColor: theme.border }}>
                            <div className="h-12" style={{ backgroundColor: `${theme.accent}24` }} />
                            <div className="p-2">
                                <div className="h-1.5 w-4/5 rounded" style={{ backgroundColor: theme.text, opacity: .62 }} />
                                <div className="mt-1.5 h-1.5 w-3/5 rounded" style={{ backgroundColor: theme.muted, opacity: .25 }} />
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (pattern === "contact") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <div className="flex h-full gap-3">
                    <div className="flex w-[42%] flex-col justify-center"><Header /></div>
                    <div className="flex flex-1 flex-col gap-2 rounded-lg border p-3" style={{ backgroundColor: theme.surface, borderColor: theme.border }}>
                        <div className="grid grid-cols-2 gap-2"><div className="h-5 rounded" style={{ backgroundColor: `${theme.text}14` }} /><div className="h-5 rounded" style={{ backgroundColor: `${theme.text}14` }} /></div>
                        <div className="h-5 rounded" style={{ backgroundColor: `${theme.text}14` }} />
                        <div className="h-9 rounded" style={{ backgroundColor: `${theme.text}14` }} />
                        <div className="mt-auto h-6 rounded" style={{ backgroundColor: theme.accent }} />
                    </div>
                </div>
            </div>
        );
    }

    if (pattern === "details") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <div className="grid h-full grid-cols-2 gap-3">
                    <div className="flex flex-col justify-center"><Header /></div>
                    <div className="grid grid-cols-2 gap-2">
                        {[0,1,2,3].map((item) => (
                            <div key={item} className="rounded-lg border p-2" style={{ backgroundColor: theme.surface, borderColor: theme.border }}>
                                <div className="mb-2 h-1.5 w-8 rounded" style={{ backgroundColor: theme.accent }} />
                                <div className="h-2 w-full rounded" style={{ backgroundColor: theme.text, opacity: .54 }} />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        );
    }

    if (pattern === "collection") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <Header />
                <div className="grid grid-cols-3 gap-2">
                    {[0,1,2].map((item) => (
                        <div key={item} className="rounded-lg border p-2.5" style={{ backgroundColor: item === 0 ? `${theme.accent}20` : theme.surface, borderColor: theme.border }}>
                            <div className="mb-2 h-7 rounded" style={{ backgroundColor: `${theme.text}10` }} />
                            <div className="h-2 w-4/5 rounded" style={{ backgroundColor: theme.text, opacity: .62 }} />
                            <div className="mt-2 h-1.5 w-3/5 rounded" style={{ backgroundColor: theme.muted, opacity: .24 }} />
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (pattern === "list") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <Header />
                <div className="space-y-2">
                    {[0,1,2].map((item) => (
                        <div key={item} className="flex items-center justify-between rounded-lg border px-3 py-2.5" style={{ backgroundColor: theme.surface, borderColor: theme.border }}>
                            <div className="h-2 w-2/5 rounded" style={{ backgroundColor: theme.text, opacity: .6 }} />
                            <div className="h-2 w-12 rounded" style={{ backgroundColor: theme.accent, opacity: .65 }} />
                        </div>
                    ))}
                </div>
            </div>
        );
    }


    if (pattern === "map") {
        return (
            <div
                className="relative h-full min-h-[158px] overflow-hidden rounded-xl border p-3"
                style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}
            >
                <div
                    className="absolute inset-0 opacity-25"
                    style={{
                        backgroundImage: `linear-gradient(${theme.muted}33 1px, transparent 1px), linear-gradient(90deg, ${theme.muted}33 1px, transparent 1px)`,
                        backgroundSize: "20px 20px",
                    }}
                />
                <div className="relative flex h-full flex-col justify-between">
                    <div>
                        <span
                            className="rounded-full border px-2 py-1 text-[7px] font-bold uppercase tracking-[0.16em]"
                            style={{ borderColor: theme.border, backgroundColor: `${theme.surface}77`, color: theme.muted }}
                        >
                            Location
                        </span>
                        <div className="mt-3 h-3 w-2/5 rounded" style={{ backgroundColor: theme.text, opacity: .82 }} />
                    </div>

                    <div className="grid h-9 w-9 place-items-center rounded-full border text-sm font-bold shadow-sm"
                        style={{ backgroundColor: theme.accent, borderColor: theme.border, color: "#FFFFFF" }}>
                        •
                    </div>

                    <div className="rounded-lg border p-2.5" style={{ backgroundColor: theme.surface, borderColor: theme.border }}>
                        <div className="h-2 w-3/4 rounded" style={{ backgroundColor: theme.text, opacity: .62 }} />
                        <div className="mt-2 h-1.5 w-full rounded" style={{ backgroundColor: theme.muted, opacity: .26 }} />
                    </div>
                </div>
            </div>
        );
    }

    if (pattern === "feature") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <div className={`flex h-full gap-3 ${imageSide === "right" ? "flex-row-reverse" : ""}`}>
                    <div className="relative w-1/2 overflow-hidden rounded-lg border" style={{ backgroundColor: theme.surface, borderColor: theme.border }}>
                        <div className="absolute inset-3 rounded-md" style={{ backgroundColor: `${theme.accent}1F` }} />
                        <span className="absolute bottom-3 left-3 h-1.5 w-10 rounded-full" style={{ backgroundColor: theme.accent }} />
                    </div>
                    <div className="flex w-1/2 flex-col justify-center px-2">
                        <Header />
                        <div className="mt-1 h-6 w-16 rounded-full" style={{ backgroundColor: theme.accent }} />
                    </div>
                </div>
            </div>
        );
    }

    if (pattern === "bento") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <Header />
                <div className="grid h-[92px] grid-cols-5 gap-2">
                    <div className="col-span-3 rounded-lg border p-2.5" style={{ backgroundColor: theme.surface, borderColor: theme.border }}>
                        <div className="h-2 w-8 rounded" style={{ backgroundColor: theme.accent }} />
                        <div className="mt-5 h-2 w-3/4 rounded" style={{ backgroundColor: theme.text, opacity: .75 }} />
                        <div className="mt-2 h-1.5 w-full rounded" style={{ backgroundColor: theme.muted, opacity: .22 }} />
                    </div>
                    <div className="col-span-2 grid grid-rows-2 gap-2">
                        <Card active />
                        <div className="grid grid-cols-2 gap-2">
                            <div className="rounded-lg border" style={{ backgroundColor: theme.surface, borderColor: theme.border }} />
                            <div className="rounded-lg border" style={{ backgroundColor: `${theme.accent}22`, borderColor: theme.border }} />
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    if (pattern === "comparison") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <Header />
                <div className="grid grid-cols-4 overflow-hidden rounded-lg border" style={{ borderColor: theme.border }}>
                    <div style={{ backgroundColor: theme.surface }} />
                    {[0,1,2].map((col) => (
                        <div key={col} className="p-2" style={{ backgroundColor: col === 1 ? `${theme.accent}22` : theme.surface }}>
                            <div className="h-1.5 w-2/3 rounded" style={{ backgroundColor: theme.muted, opacity: .5 }} />
                            <div className="mt-2 h-2.5 w-1/2 rounded" style={{ backgroundColor: theme.text, opacity: .75 }} />
                        </div>
                    ))}
                    {[0,1,2].map((row) => (
                        <div key={row} className="contents">
                            {[0,1,2,3].map((col) => (
                                <div key={col} className="h-5 border-t p-1" style={{ borderColor: theme.border, backgroundColor: col === 2 ? `${theme.accent}14` : theme.surface }}>
                                    <div className="h-1 w-3/4 rounded" style={{ backgroundColor: theme.muted, opacity: .28 }} />
                                </div>
                            ))}
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (pattern === "hover") {
        return (
            <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
                <Header />
                <div className="grid grid-cols-3 gap-2">
                    {[0,1,2,3,4,5].map((item) => <Card key={item} active={item === 1} />)}
                </div>
            </div>
        );
    }

    return (
        <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ backgroundColor: theme.background, borderColor: theme.border, color: theme.text }}>
            <Header />
            <div className="grid grid-cols-3 gap-2">
                {[0,1,2].map((item) => <Card key={item} active={item === 1} />)}
            </div>
        </div>
    );
}
