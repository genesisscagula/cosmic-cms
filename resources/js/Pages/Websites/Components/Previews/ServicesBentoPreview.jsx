export default function ServicesBentoPreview() {

    return (

        <div className="h-40 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">

            {/* Header */}

            <div className="px-3 pt-3 pb-2">

                <div className="h-1.5 w-14 bg-slate-600 rounded-full mb-2" />

                <div className="h-3 w-40 bg-slate-500 rounded-full mb-2" />

                <div className="h-2 w-28 bg-slate-700 rounded-full" />

            </div>

            {/* Service Rows */}

            <div className="px-3 pb-3 space-y-2">

                {[1,2].map((item) => (

                    <div
                        key={item}
                        className="flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-2 py-2"
                    >

                        {/* Icon */}

                        <div className="w-6 h-6 rounded-md bg-slate-600 shrink-0" />

                        {/* Text */}

                        <div className="flex-1">

                            <div className="h-2 w-20 bg-slate-500 rounded-full mb-1" />

                            <div className="h-1.5 w-28 bg-slate-700 rounded-full" />

                        </div>

                        {/* Arrow */}

                        <div className="w-3 h-3 border-t border-r border-slate-500 rotate-45 mr-1" />

                    </div>

                ))}

            </div>

        </div>

    );

}