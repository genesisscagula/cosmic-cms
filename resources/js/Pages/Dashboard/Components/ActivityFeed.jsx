const activityIcons = {
    edit: "✎",
    website: "◎",
    publish: "↗",
    theme: "◐",
    ai: "✦",
    credits: "✦",
    billing: "$",
    warning: "!",
    workspace: "◇",
};

export default function ActivityFeed({ activity }) {
    if (!activity.length) {
        return <div className="rounded-xl border border-dashed border-white/15 px-4 py-8 text-center text-sm text-slate-500">Workspace activity will appear here.</div>;
    }

    return (
        <div className="rounded-2xl border border-white/10 bg-white/[0.035]">
            {activity.map((item, index) => (
                <div key={item.id} className={`flex gap-3 px-4 py-3 ${index ? "border-t border-white/10" : ""}`}>
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.06] text-xs text-violet-300" aria-hidden="true">
                        {activityIcons[item.type] || "•"}
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm text-slate-300">
                            <span className="font-medium text-white">{item.actor}</span> {item.action} <span className="font-medium text-white">{item.target}</span>
                        </p>
                        <p className="mt-1 text-xs text-slate-500">{item.time}</p>
                    </div>
                </div>
            ))}
        </div>
    );
}
