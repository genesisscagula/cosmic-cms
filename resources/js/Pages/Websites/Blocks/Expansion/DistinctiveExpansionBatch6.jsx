import React from "react";

import { sparkTw } from "../Shared/sparkTailwindRuntime";
const themeVars = (resolved) => {
    const primary = String(resolved || "").toLowerCase() === "primary";

    return {
        "--b6-bg": primary
            ? "var(--cosmic-local-bg-primary,var(--cosmic-bg-primary,var(--cosmic-primary,#243447)))"
            : "var(--cosmic-local-bg-surface,var(--cosmic-bg-surface,#ffffff))",
        "--b6-surface": primary
            ? "var(--cosmic-bg-primary-surface,var(--cosmic-surface,#30475E))"
            : "color-mix(in srgb,var(--cosmic-bg-surface,#ffffff) 92%,#64748b 8%)",
        "--b6-text": primary ? "var(--cosmic-on-primary,#f8fafc)" : "var(--cosmic-on-surface,#172033)",
        "--b6-muted": primary
            ? "var(--cosmic-on-primary-muted,rgba(248,250,252,.74))"
            : "var(--cosmic-on-surface-muted,#64748b)",
        "--b6-border": primary ? "rgba(248,250,252,.18)" : "rgba(15,23,42,.13)",
        "--b6-accent": "var(--cosmic-accent,#60A5FA)",
    };
};

const Text = ({ value, kind = "text", type, className = "", block }) => (
    <span
        data-cosmic-luna-display="text"
        data-luna-target={kind}
        data-cosmic-type={type}
        className={sparkTw(block, "auto_1", className)}
    >
        {value || "Click to add text"}
    </span>
);

const Action = ({ label, className = "b6-primary", block }) => label ? (
    <span data-cosmic-luna-display="button" data-luna-target="button" className={sparkTw(block, "auto_2", className)}>{label}</span>
) : null;

const Header = ({ data, block }) => (
    <div className={sparkTw(block, "auto_3", "b6-head")}>
        <Text block={block} value={data.eyebrow} kind="label" type="eyebrow" className={sparkTw(block, "auto_4", "b6-eyebrow")} />
        <Text block={block} value={data.heading} kind="heading" type="h2" className={sparkTw(block, "auto_5", "b6-heading")} />
        <Text block={block} value={data.text} type="lead" className={sparkTw(block, "auto_6", "b6-intro")} />
    </div>
);

const Item = ({ item, index, className = "", block }) => (
    <article data-cosmic-repeatable-item className={sparkTw(block, "auto_7", `b6-item ${className}`)}>
        <Text block={block} value={item?.label || String(index + 1).padStart(2, "0")} kind="label" type="small" className={sparkTw(block, "auto_8", "b6-label")} />
        {item?.value && <Text block={block} value={item.value} kind="heading" type="stat-title" className={sparkTw(block, "auto_9", "b6-value")} />}
        <Text block={block} value={item?.title} kind="heading" type="card-title" className={sparkTw(block, "auto_10", "b6-title")} />
        <Text block={block} value={item?.text} type="card-body" className={sparkTw(block, "auto_11", "b6-copy")} />
        {item?.meta && <Text block={block} value={item.meta} kind="label" type="small" className={sparkTw(block, "auto_12", "b6-meta")} />}
    </article>
);

function DistinctiveBatch6({ block, variant }) {
    const items = Array.isArray(block?.items) ? block.items : [];
    const data = block || {};
    const resolved = block?.resolvedTheme || block?.theme || "light";

    const body = (() => {
        if (variant === "chapters") {
            return <div className={sparkTw(block, "auto_13", "b6-chapters")}><Header block={block} data={data} /><div className={sparkTw(block, "auto_14", "b6-list")}>{items.map((item, index) => <Item block={block} key={index} item={item} index={index} />)}</div></div>;
        }

        if (variant === "orbit") {
            return <><Header block={block} data={data} /><div className={sparkTw(block, "auto_15", "b6-orbit")}><div className={sparkTw(block, "auto_16", "b6-orbit-core")}><Text block={block} value={data.core_label || "Connected offer"} kind="label" type="small" className={sparkTw(block, "auto_17", "b6-label")} /><Text block={block} value={data.core_title || data.heading} kind="heading" type="card-title" className={sparkTw(block, "auto_18", "b6-title")} /></div>{items.slice(0, 6).map((item, index) => <Item block={block} key={index} item={item} index={index} className={sparkTw(block, "auto_19", `b6-orbit-${index + 1}`)} />)}</div></>;
        }

        if (variant === "constellation") {
            return <><Header block={block} data={data} /><div className={sparkTw(block, "auto_20", "b6-constellation")}>{items.map((item, index) => <Item block={block} key={index} item={item} index={index} />)}</div></>;
        }

        if (variant === "staircase") {
            return <><Header block={block} data={data} /><div className={sparkTw(block, "auto_21", "b6-staircase")}>{items.map((item, index) => <Item block={block} key={index} item={item} index={index} />)}</div></>;
        }

        if (variant === "ledger") {
            return <><Header block={block} data={data} /><div className={sparkTw(block, "auto_22", "b6-ledger")}>{items.map((item, index) => <Item block={block} key={index} item={item} index={index} />)}</div></>;
        }

        if (variant === "tree") {
            return <><Header block={block} data={data} /><div className={sparkTw(block, "auto_23", "b6-tree")}>{items.map((item, index) => <Item block={block} key={index} item={item} index={index} />)}</div></>;
        }

        if (variant === "ticket") {
            return <div className={sparkTw(block, "auto_24", "b6-ticket")}><div><Header block={block} data={data} /><div className={sparkTw(block, "auto_25", "b6-ticket-tags")}>{items.map((item, index) => <Text block={block} key={index} value={item?.title} kind="label" type="small" className={sparkTw(block, "auto_26", "b6-ticket-tag")} />)}</div></div><div className={sparkTw(block, "auto_27", "b6-ticket-action")}><Text block={block} value={data.action_note || "Your next step"} kind="label" type="small" className={sparkTw(block, "auto_28", "b6-label")} /><Action block={block} label={data.button_label} /><Action block={block} label={data.secondary_label} className={sparkTw(block, "auto_29", "b6-secondary")} /></div></div>;
        }

        return <><Header block={block} data={data} /><div className={sparkTw(block, "auto_30", "b6-board")}>{items.map((item, index) => <Item block={block} key={index} item={item} index={index} />)}</div><div className={sparkTw(block, "auto_31", "b6-actions")}><Action block={block} label={data.button_label} /><Action block={block} label={data.secondary_label} className={sparkTw(block, "auto_32", "b6-secondary")} /></div></>;
    })();

    return <section className={sparkTw(block, "auto_33", `group/repeatable-section cosmic-b6 b6-${variant}`)} style={themeVars(resolved)}>
        <div className={sparkTw(block, "auto_34", "b6-shell")}>{body}</div>
        <style>{`
            .cosmic-b6{position:relative;overflow:hidden;container-type:inline-size;container-name:cosmic-b6;background:var(--b6-bg);color:var(--b6-text);padding:7rem 1.75rem}
            .cosmic-b6 *{box-sizing:border-box}.cosmic-b6 .b6-shell{width:100%;max-width:88rem;margin:0 auto}
            .cosmic-b6 .b6-head{max-width:52rem}.cosmic-b6 .b6-eyebrow,.cosmic-b6 .b6-label{display:block;color:var(--b6-muted);font-size:.72rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase}
            .cosmic-b6 .b6-heading{display:block;margin-top:1rem;font-size:clamp(2.45rem,5vw,4.8rem);font-weight:700;line-height:.97;letter-spacing:-.05em}
            .cosmic-b6 .b6-intro{display:block;max-width:45rem;margin-top:1.25rem;color:var(--b6-muted);font-size:1.05rem;line-height:1.8}
            .cosmic-b6 .b6-item{position:relative;min-width:0;border:1px solid var(--b6-border);background:var(--b6-surface);padding:1.5rem}
            .cosmic-b6 .b6-title,.cosmic-b6 .b6-value{display:block}.cosmic-b6 .b6-title{margin-top:.7rem;font-size:1.35rem;font-weight:700;line-height:1.12;letter-spacing:-.025em}
            .cosmic-b6 .b6-value{margin:.75rem 0 -.25rem;font-size:clamp(2.4rem,4vw,4.5rem);font-weight:700;line-height:.9;letter-spacing:-.055em;color:var(--b6-accent)}
            .cosmic-b6 .b6-copy{display:block;margin-top:.75rem;color:var(--b6-muted);line-height:1.7}.cosmic-b6 .b6-meta{display:block;margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--b6-border);color:var(--b6-muted)}
            .cosmic-b6 .b6-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:2rem}.cosmic-b6 .b6-primary,.cosmic-b6 .b6-secondary{display:inline-flex;min-height:50px;align-items:center;justify-content:center;border-radius:999px;padding:.8rem 1.5rem;font-weight:800}
            .cosmic-b6 .b6-primary{background:var(--b6-text);color:var(--b6-bg)}.cosmic-b6 .b6-secondary{border:1px solid var(--b6-border);color:var(--b6-text)}

            .b6-chapters{display:grid;grid-template-columns:.72fr 1.28fr;gap:clamp(3rem,8vw,8rem);align-items:start}.b6-chapters .b6-head{position:sticky;top:8rem}.b6-chapters .b6-list{border-top:1px solid var(--b6-border)}
            .b6-chapters .b6-item{display:grid;grid-template-columns:6rem .7fr 1.3fr;gap:1.5rem;align-items:start;border-width:0 0 1px;background:transparent;padding:2rem 0}.b6-chapters .b6-title{margin:0}.b6-chapters .b6-copy{margin:0}

            .b6-orbit{position:relative;display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;max-width:68rem;margin:4rem auto 0}.b6-orbit:before{content:"";position:absolute;inset:12% 16%;border:1px dashed var(--b6-border);border-radius:50%}.b6-orbit-core{z-index:2;grid-column:2;grid-row:2;display:grid;min-height:12rem;place-content:center;border:1px solid color-mix(in srgb,var(--b6-accent) 70%,transparent);border-radius:50%;background:var(--b6-bg);padding:2rem;text-align:center;box-shadow:0 0 0 1rem color-mix(in srgb,var(--b6-accent) 8%,transparent)}
            .b6-orbit .b6-item{z-index:2;border-radius:1.5rem}.b6-orbit .b6-orbit-1{grid-column:1;grid-row:1}.b6-orbit .b6-orbit-2{grid-column:2;grid-row:1}.b6-orbit .b6-orbit-3{grid-column:3;grid-row:1}.b6-orbit .b6-orbit-4{grid-column:1;grid-row:3}.b6-orbit .b6-orbit-5{grid-column:2;grid-row:3}.b6-orbit .b6-orbit-6{grid-column:3;grid-row:3}

            .b6-constellation{position:relative;display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-top:4rem}.b6-constellation:before{content:"";position:absolute;left:5%;right:5%;top:2.15rem;height:1px;background:linear-gradient(90deg,transparent,var(--b6-accent),transparent);opacity:.55}.b6-constellation .b6-item{border-width:0;border-radius:0;background:transparent;padding:0 1.25rem 1.5rem}.b6-constellation .b6-label{display:grid;width:4.3rem;height:4.3rem;place-items:center;border:1px solid var(--b6-border);border-radius:50%;background:var(--b6-bg);color:var(--b6-text)}

            .b6-staircase{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-top:4rem;align-items:start}.b6-staircase .b6-item{min-height:18rem;border-radius:1.5rem}.b6-staircase .b6-item:nth-child(2){margin-top:2rem}.b6-staircase .b6-item:nth-child(3){margin-top:4rem}.b6-staircase .b6-item:nth-child(4){margin-top:6rem}

            .b6-ledger{margin-top:4rem;border-top:1px solid var(--b6-border)}.b6-ledger .b6-item{display:grid;grid-template-columns:8rem .8fr 1.2fr 9rem;gap:1.5rem;align-items:center;border-width:0 0 1px;background:transparent;padding:1.35rem 0}.b6-ledger .b6-title,.b6-ledger .b6-copy,.b6-ledger .b6-meta{margin:0;padding:0;border:0}.b6-ledger .b6-meta{text-align:right}

            .b6-tree{display:grid;grid-template-columns:repeat(2,1fr);gap:1.25rem;margin-top:4rem;padding-left:4rem}.b6-tree .b6-item{border-radius:1.5rem}.b6-tree .b6-item:before{content:"";position:absolute;right:100%;top:2rem;width:4rem;border-top:1px solid var(--b6-border)}.b6-tree .b6-item:nth-child(3),.b6-tree .b6-item:nth-child(4){margin-left:3rem}

            .b6-ticket{position:relative;display:grid;grid-template-columns:1.3fr .7fr;overflow:hidden;border:1px solid var(--b6-border);border-radius:2rem;background:var(--b6-surface)}.b6-ticket:before,.b6-ticket:after{content:"";position:absolute;left:70%;z-index:2;width:2.5rem;height:2.5rem;transform:translate(-50%,-50%);border:1px solid var(--b6-border);border-radius:50%;background:var(--b6-bg)}.b6-ticket:before{top:0}.b6-ticket:after{top:100%}.b6-ticket>div{padding:clamp(2rem,5vw,4.5rem)}.b6-ticket .b6-ticket-action{display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:.8rem;border-left:1px dashed var(--b6-border)}.b6-ticket-tags{display:flex;flex-wrap:wrap;gap:.55rem;margin-top:2rem}.b6-ticket-tag{border:1px solid var(--b6-border);border-radius:999px;padding:.5rem .8rem;color:var(--b6-muted)}

            .b6-board{margin-top:4rem;overflow:hidden;border:1px solid var(--b6-border);border-radius:1.75rem}.b6-board .b6-item{display:grid;grid-template-columns:8rem .7fr 1.3fr 10rem;gap:1.5rem;align-items:center;border-width:0 0 1px;background:transparent;padding:1.35rem 1.5rem}.b6-board .b6-item:last-child{border-bottom:0}.b6-board .b6-title,.b6-board .b6-copy,.b6-board .b6-meta{margin:0;padding:0;border:0}.b6-board .b6-meta{text-align:right;color:var(--b6-accent)}

            /* Batch 1 family repair: respond to the Spark canvas width, not the browser viewport.
               This keeps About/Content and Process compositions intact inside the Section Editor,
               where the Luna sidebar reduces the real preview width even on a wide desktop. */
            @container cosmic-b6 (max-width: 1100px){
                .b6-chapters{grid-template-columns:1fr;gap:2.75rem}
                .b6-chapters .b6-head{position:static;max-width:46rem}
                .b6-chapters .b6-heading{font-size:clamp(2.5rem,7cqw,4.35rem);max-width:12ch}
                .b6-chapters .b6-item{grid-template-columns:5rem minmax(10rem,.9fr) minmax(0,1.1fr);gap:1.25rem}
                .b6-constellation{grid-template-columns:repeat(2,minmax(0,1fr));gap:1.5rem 1rem}
                .b6-constellation:before{display:none}
                .b6-constellation .b6-item{padding:0 1rem 1.25rem}
                /* Batch 2 CTA repair: the ticket must react to the actual Section Editor canvas. */
                .b6-ticket{grid-template-columns:1fr}
                .b6-ticket:before,.b6-ticket:after{display:none}
                .b6-ticket .b6-ticket-action{border-top:1px dashed var(--b6-border);border-left:0}
                .b6-ticket>div{padding:clamp(2rem,5cqw,3.5rem)}
                .b6-ticket .b6-heading{max-width:13ch;font-size:clamp(2.5rem,7cqw,4.35rem)}
            }
            @container cosmic-b6 (max-width: 720px){
                .cosmic-b6 .b6-heading{font-size:clamp(2.25rem,10cqw,3.5rem)}
                .b6-chapters .b6-item{grid-template-columns:4rem minmax(0,1fr)}
                .b6-chapters .b6-copy{grid-column:2}
                .b6-constellation{grid-template-columns:1fr;gap:1.5rem}
                .b6-constellation .b6-item{padding:0 0 1.25rem}
            }

            @media(max-width:900px){.cosmic-b6{padding:5rem 1.25rem}.b6-chapters{grid-template-columns:1fr}.b6-chapters .b6-head{position:static}.b6-orbit{grid-template-columns:repeat(2,1fr)}.b6-orbit:before{display:none}.b6-orbit-core{grid-column:1/-1;grid-row:auto;border-radius:1.75rem}.b6-orbit .b6-item{grid-column:auto!important;grid-row:auto!important}.b6-constellation,.b6-staircase{grid-template-columns:repeat(2,1fr)}.b6-staircase .b6-item{margin-top:0!important}.b6-ticket{grid-template-columns:1fr}.b6-ticket:before,.b6-ticket:after{display:none}.b6-ticket .b6-ticket-action{border-top:1px dashed var(--b6-border);border-left:0}.b6-ledger .b6-item,.b6-board .b6-item{grid-template-columns:6rem 1fr 1.4fr}.b6-ledger .b6-meta,.b6-board .b6-meta{grid-column:2/-1;text-align:left}}
            @media(max-width:640px){.cosmic-b6{padding:4rem 1rem}.b6-chapters .b6-item{grid-template-columns:4rem 1fr}.b6-chapters .b6-copy{grid-column:2}.b6-orbit,.b6-constellation,.b6-staircase,.b6-tree{grid-template-columns:1fr}.b6-tree{padding-left:1.25rem}.b6-tree .b6-item{margin-left:0!important}.b6-ledger .b6-item,.b6-board .b6-item{grid-template-columns:1fr;gap:.55rem}.b6-ledger .b6-meta,.b6-board .b6-meta{grid-column:auto}.b6-heading{overflow-wrap:anywhere}}
        `}</style>
    </section>;
}

const common = (type, eyebrow, heading, text, items, extra = {}) => ({
    type, theme: "auto", eyebrow, heading, text,
    button_label: "Start a conversation", button_url: "#",
    secondary_label: "Explore the details", secondary_url: "#",
    items, ...extra,
});

const standardItems = [
    { label: "01", title: "A clear beginning", text: "Set context quickly so every visitor understands what matters first.", meta: "Foundation" },
    { label: "02", title: "A useful middle", text: "Connect the offer to real decisions without adding unnecessary visual noise.", meta: "Clarity" },
    { label: "03", title: "A confident close", text: "Bring the next action into focus with direct, considered language.", meta: "Action" },
    { label: "04", title: "Room to evolve", text: "Keep the structure flexible as services, priorities, and audiences change.", meta: "Adaptable" },
];

export const AboutChapterIndexPremiumBlock = (props) => <DistinctiveBatch6 {...props} variant="chapters" />;
export const AboutChapterIndexPremiumSchema = { type: "about_chapter_index_premium", title: "Chapter Index", description: "A low-image editorial chapter index with sticky narrative pacing.", defaults: common("about_chapter_index_premium", "Our point of view", "A story arranged in meaningful chapters", "Use structured narrative pacing to explain where the business began, what guides it, and where it is going.", standardItems) };

export const ServicesOrbitMapPremiumBlock = (props) => <DistinctiveBatch6 {...props} variant="orbit" />;
export const ServicesOrbitMapPremiumSchema = { type: "services_orbit_map_premium", title: "Services Orbit Map", description: "A connected service system arranged around one central promise.", defaults: common("services_orbit_map_premium", "How it connects", "One offer, expressed as a complete system", "Show how individual services work together instead of presenting another conventional card grid.", [...standardItems, { label: "05", title: "Measured support", text: "Keep delivery visible and decisions easy to follow.", meta: "Support" }, { label: "06", title: "Continuous refinement", text: "Improve the system as evidence and priorities change.", meta: "Growth" }], { core_label: "Connected offer", core_title: "Built around the customer" }) };

export const ProcessConstellationPremiumBlock = (props) => <DistinctiveBatch6 {...props} variant="constellation" />;
export const ProcessConstellationPremiumSchema = { type: "process_constellation_premium", title: "Process Constellation", description: "A connected process path with spacious milestones instead of dense cards.", defaults: common("process_constellation_premium", "The path forward", "A process with visible momentum", "Each stage connects to the next, giving visitors a quick mental model of how the work moves forward.", standardItems) };

export const ProofMetricStaircasePremiumBlock = (props) => <DistinctiveBatch6 {...props} variant="staircase" />;
export const ProofMetricStaircasePremiumSchema = { type: "proof_metric_staircase_premium", title: "Proof Staircase", description: "A stepped evidence composition for supplied outcomes or qualitative proof.", defaults: common("proof_metric_staircase_premium", "Evidence, in sequence", "Build confidence one proof point at a time", "Use verified figures when available, or keep the starter wording qualitative until real evidence is supplied.", standardItems.map((item, index) => ({ ...item, value: ["Clear", "Useful", "Direct", "Ready"][index] }))) };

export const TrustEvidenceLedgerPremiumBlock = (props) => <DistinctiveBatch6 {...props} variant="ledger" />;
export const TrustEvidenceLedgerPremiumSchema = { type: "trust_evidence_ledger_premium", title: "Evidence Ledger", description: "A disciplined ledger connecting promises to supporting evidence.", defaults: common("trust_evidence_ledger_premium", "Why trust it", "Claims are stronger when the evidence is visible", "Pair each important promise with the detail, policy, process, or supplied proof that supports it.", standardItems) };

export const FaqDecisionTreePremiumBlock = (props) => <DistinctiveBatch6 {...props} variant="tree" />;
export const FaqDecisionTreePremiumSchema = { type: "faq_decision_tree_premium", title: "FAQ Decision Tree", description: "A branching FAQ structure organized around visitor decisions.", defaults: common("faq_decision_tree_premium", "Choose your path", "Answers organized around the decision in front of you", "Guide visitors through the most useful questions without turning the section into a long undifferentiated accordion.", standardItems) };

export const CtaTicketPremiumBlock = (props) => <DistinctiveBatch6 {...props} variant="ticket" />;
export const CtaTicketPremiumSchema = { type: "cta_ticket_premium", title: "Ticket CTA", description: "A memorable perforated-ticket call to action with compact supporting points.", defaults: common("cta_ticket_premium", "Your next step", "Turn interest into a clear invitation", "Make the final action feel intentional, concise, and easy to understand.", standardItems.slice(0, 3), { action_note: "Ready when you are" }) };

export const ContactAvailabilityBoardPremiumBlock = (props) => <DistinctiveBatch6 {...props} variant="board" />;
export const ContactAvailabilityBoardPremiumSchema = { type: "contact_availability_board_premium", title: "Contact Availability Board", description: "A structured contact board that avoids inventing hours or availability.", defaults: common("contact_availability_board_premium", "Ways to connect", "Choose the contact path that fits your next step", "Share real contact routes and set clear expectations without claiming unsupplied response times or availability.", [
    { label: "Email", title: "Send an enquiry", text: "Share the context and the outcome you are looking for.", meta: "By request" },
    { label: "Call", title: "Talk it through", text: "Use the supplied business number when a conversation is more useful.", meta: "By request" },
    { label: "Visit", title: "Plan a visit", text: "Confirm the supplied location details before travelling.", meta: "Plan ahead" },
    { label: "Project", title: "Start with a brief", text: "Outline the scope, priorities, and preferred next step.", meta: "Send details" },
]) };
