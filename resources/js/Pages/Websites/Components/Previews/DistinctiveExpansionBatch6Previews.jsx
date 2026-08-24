import React from "react";

const palette = { background: "#f8fafc", surface: "#ffffff", text: "#172033", muted: "#64748b", border: "#dbe3ec", accent: "#3b82f6" };

function Preview({ variant }) {
    const Header = () => <div className="mb-3"><div className="mb-2 h-1.5 w-12 rounded" style={{ background: palette.accent }} /><div className="h-3 w-2/3 rounded" style={{ background: palette.text }} /><div className="mt-2 h-1.5 w-1/2 rounded" style={{ background: palette.muted, opacity: .35 }} /></div>;
    const Card = ({ index = 0 }) => <div className="rounded-lg border p-2.5" style={{ borderColor: palette.border, background: palette.surface }}><div className="mb-2 h-1.5 w-6 rounded" style={{ background: palette.accent, opacity: .8 }} /><div className="h-2 rounded" style={{ background: palette.text, opacity: .7 }} /><div className="mt-2 h-1.5 rounded" style={{ width: `${70 - index * 5}%`, background: palette.muted, opacity: .25 }} /></div>;

    let content;
    if (variant === "chapters" || variant === "ledger" || variant === "board") {
        content = <div className="grid grid-cols-[.7fr_1.3fr] gap-3"><Header /><div className="space-y-1.5">{[0,1,2,3].map(i => <Card key={i} index={i} />)}</div></div>;
    } else if (variant === "orbit") {
        content = <><Header /><div className="grid grid-cols-3 gap-1.5"><Card /><Card /><Card /><div /><div className="grid place-items-center rounded-full border text-[7px] font-bold" style={{ borderColor: palette.accent }}>CORE</div><div /><Card /><Card /><Card /></div></>;
    } else if (variant === "ticket") {
        content = <div className="grid h-full grid-cols-[1.25fr_.75fr] overflow-hidden rounded-xl border" style={{ borderColor: palette.border }}><div className="p-3"><Header /><div className="flex gap-1"><span className="h-4 w-12 rounded-full border" style={{ borderColor: palette.border }} /><span className="h-4 w-12 rounded-full border" style={{ borderColor: palette.border }} /></div></div><div className="grid place-items-center border-l border-dashed" style={{ borderColor: palette.border }}><div className="h-6 w-16 rounded-full" style={{ background: palette.text }} /></div></div>;
    } else {
        content = <><Header /><div className="grid grid-cols-4 items-start gap-2">{[0,1,2,3].map(i => <div key={i} style={{ marginTop: variant === "staircase" ? `${i * 7}px` : 0 }}><Card index={i} /></div>)}</div></>;
    }

    return <div className="h-full min-h-[158px] overflow-hidden rounded-xl border p-3" style={{ background: palette.background, borderColor: palette.border, color: palette.text }}>{content}</div>;
}

export const AboutChapterIndexPremiumPreview = () => <Preview variant="chapters" />;
export const ServicesOrbitMapPremiumPreview = () => <Preview variant="orbit" />;
export const ProcessConstellationPremiumPreview = () => <Preview variant="constellation" />;
export const ProofMetricStaircasePremiumPreview = () => <Preview variant="staircase" />;
export const TrustEvidenceLedgerPremiumPreview = () => <Preview variant="ledger" />;
export const FaqDecisionTreePremiumPreview = () => <Preview variant="tree" />;
export const CtaTicketPremiumPreview = () => <Preview variant="ticket" />;
export const ContactAvailabilityBoardPremiumPreview = () => <Preview variant="board" />;
