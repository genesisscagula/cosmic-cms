export default function PricingCardsPreview() {

    return (

        <div className="h-40 bg-slate-900 rounded-xl border border-slate-800 flex gap-2 p-2">

            {[1, 2, 3].map((item) => (

                <div
                    key={item}
                    className={`
                        flex-1
                        rounded-lg
                        border
                        ${item === 2
                            ? "border-emerald-500 bg-slate-800"
                            : "border-slate-700 bg-slate-850"}
                        p-2
                        flex
                        flex-col
                    `}
                >

                    {/* Badge */}

                    {item === 2 && (

                        <div className="h-3 w-14 bg-emerald-500 rounded-full mx-auto mb-2"></div>

                    )}

                    {/* Title */}

                    <div className="h-2 w-12 bg-slate-600 rounded-full mx-auto"></div>

                    {/* Price */}

                    <div className="h-4 w-10 bg-slate-500 rounded-full mx-auto mt-3"></div>

                    {/* Period */}

                    <div className="h-2 w-8 bg-slate-700 rounded-full mx-auto mt-2"></div>

                    {/* Features */}

                    <div className="mt-4 space-y-2 flex-1">

                        <div className="h-2 w-full bg-slate-700 rounded-full"></div>
                        <div className="h-2 w-5/6 bg-slate-700 rounded-full"></div>
                        <div className="h-2 w-4/5 bg-slate-700 rounded-full"></div>
                        <div className="h-2 w-3/4 bg-slate-700 rounded-full"></div>

                    </div>

                    {/* Button */}

                    <div className="h-6 w-full bg-slate-600 rounded-md mt-4"></div>

                </div>

            ))}

        </div>

    );

}