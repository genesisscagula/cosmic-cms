import { useMemo, useState } from "react";
import { usePage } from "@inertiajs/react";
import usePlanEntitlements from "../../../Hooks/usePlanEntitlements";
import NewWebsiteModal from "../Components/NewWebsiteModal";
import TemplateCard, { FeaturedTemplateCard } from "../Components/TemplateCard";
import TemplateEmptyState from "../Components/TemplateEmptyState";
import TemplateToolbar from "../Components/TemplateToolbar";
import TemplatePreviewModal from "../Components/TemplatePreviewModal";
import TemplateUpgradePrompt from "../Components/TemplateUpgradePrompt";
import { blankTemplate, getTemplatePresentation, industryTemplates, templateCategories, templateStatusOptions, templateThemeOptions } from "../../../data/industryTemplates";

export default function Templates() {
    const { plan, capabilities, isAgency } = usePlanEntitlements();
    const { cosmicTemplates = {} } = usePage().props;
    const [query, setQuery] = useState("");
    const [category, setCategory] = useState("All");
    const [theme, setTheme] = useState("All themes");
    const [status, setStatus] = useState("All templates");
    const [sort, setSort] = useState("featured");
    const [agencyCollection, setAgencyCollection] = useState("all");
    const [selectedTemplate, setSelectedTemplate] = useState(null);
    const [previewTemplate, setPreviewTemplate] = useState(null);
    const [upgradeTemplate, setUpgradeTemplate] = useState(null);

    const agencyCollections = cosmicTemplates?.agency_collections || [];

    const templates = useMemo(() => {
        const metadataBySlug = new Map((cosmicTemplates?.items || []).map((item) => [item.slug, item]));
        const ranks = cosmicTemplates?.access_levels || { starter: 10, growth: 20, pro: 30, agency_starter: 110, agency_growth: 120, agency_pro: 130, all: Number.MAX_SAFE_INTEGER };

        return industryTemplates.map((template, index) => {
            const presented = getTemplatePresentation(template);
            const metadata = metadataBySlug.get(template.slug) || {};
            const limit = capabilities?.template_limit ?? null;
            const lockedByCount = limit !== null && index >= Number(limit);
            const requiredLevel = metadata.minimum_plan || "pro";
            const accountLevel = capabilities?.template_access_level ?? "starter";
            const lockedByLevel = (ranks[accountLevel] ?? -1) < (ranks[requiredLevel] ?? Number.MAX_SAFE_INTEGER);
            const serverLocked = typeof metadata.locked === "boolean" ? metadata.locked : null;

            return {
                ...presented,
                ...metadata,
                catalogIndex: index,
                requiredLevel,
                locked: serverLocked ?? (lockedByCount || lockedByLevel),
                lockMessage: metadata.lock_message || `Upgrade your plan to use this ${requiredLevel.replaceAll("_", " ")} template. ${plan?.template_limit_label ? `Your plan includes ${plan.template_limit_label} templates.` : ""}`.trim(),
            };
        });
    }, [capabilities, cosmicTemplates, plan]);
    const filteredTemplates = useMemo(() => {
        const search = query.trim().toLowerCase();
        const matchesStatus = (template) => status === "All templates" || (status === "Featured" && template.featured) || (status === "New" && template.isNew) || (status === "Popular" && template.popularity);
        const sorters = {
            recent: (a, b) => b.updatedAt.localeCompare(a.updatedAt),
            used: (a, b) => b.usage - a.usage,
            name: (a, b) => a.name.localeCompare(b.name),
            featured: (a, b) => Number(b.featured) - Number(a.featured) || b.usage - a.usage,
        };

        return templates
            .filter((template) => (category === "All" || template.category === category)
                && (theme === "All themes" || template.themeId === theme)
                && (!isAgency || agencyCollection === "all" || (template.agency_collections || []).includes(agencyCollection))
                && matchesStatus(template)
                && (!search || [template.name, template.category, template.industry, template.themeId, template.description, ...template.tags].join(" ").toLowerCase().includes(search)))
            .sort(sorters[sort]);
    }, [templates, query, category, theme, status, sort, isAgency, agencyCollection]);

    const featured = templates.filter((template) => template.featured).sort((a, b) => b.usage - a.usage).slice(0, 4);
    const useTemplate = (template) => {
        if (template?.locked) {
            setUpgradeTemplate(template);
            return;
        }
        setSelectedTemplate(template);
    };
    const action = (label, template) => {
        if (label === "Preview template") {
            setPreviewTemplate(template);
            return;
        }
        console.info(label, template.id);
    };
    const clearFilters = () => { setQuery(""); setCategory("All"); setTheme("All themes"); setStatus("All templates"); setSort("featured"); setAgencyCollection("all"); };
    const hasActiveFilters = Boolean(query) || category !== "All" || theme !== "All themes" || status !== "All templates" || sort !== "featured" || agencyCollection !== "all";

    return (
        <section className="space-y-7">
            <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-sm font-medium text-violet-300">{isAgency ? "Agency template library" : "Template library"}</p><h1 className="mt-2 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Templates</h1><p className="mt-2 text-sm text-slate-400">{isAgency ? "Curated client-ready collections shared across your agency workspace." : "Hand-curated foundations built from reusable Cosmic Sparks."}</p></div><button type="button" onClick={() => console.info("Create template")} className="inline-flex h-10 items-center justify-center rounded-xl bg-white px-4 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">Create Template</button></header>

            {isAgency && agencyCollections.length > 0 && <div><div className="mb-3 flex items-end justify-between gap-4"><div><h2 className="text-sm font-semibold text-white">Agency collections</h2><p className="mt-1 text-xs text-slate-500">Switch between the curated library included with each Agency tier.</p></div><button type="button" onClick={() => setAgencyCollection("all")} className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition ${agencyCollection === "all" ? "border-white bg-white text-slate-950" : "border-white/10 text-slate-400 hover:border-white/20 hover:text-white"}`}>All templates</button></div><div className="grid gap-3 md:grid-cols-3">{agencyCollections.map((collection) => <button key={collection.key} type="button" onClick={() => setAgencyCollection(collection.key)} className={`rounded-2xl border p-4 text-left transition focus:outline-none focus:ring-2 focus:ring-violet-400 ${agencyCollection === collection.key ? "border-violet-400/60 bg-violet-400/10" : "border-white/10 bg-white/[0.025] hover:border-white/20 hover:bg-white/[0.045]"}`}><div className="flex items-center justify-between gap-3"><span className="text-sm font-semibold text-white">{collection.label}</span><span className={`rounded-full px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide ${collection.locked ? "bg-amber-300/10 text-amber-100" : "bg-emerald-400/10 text-emerald-200"}`}>{collection.locked ? "Preview" : "Included"}</span></div><p className="mt-2 line-clamp-2 text-xs leading-5 text-slate-400">{collection.description}</p><div className="mt-3 flex items-center justify-between text-[11px] text-slate-500"><span>{collection.template_count} templates</span><span className="capitalize">{collection.minimum_plan.replaceAll("_", " ")}</span></div></button>)}</div></div>}

            <TemplateToolbar query={query} onQueryChange={setQuery} category={category} onCategoryChange={setCategory} sort={sort} onSortChange={setSort} theme={theme} onThemeChange={setTheme} status={status} onStatusChange={setStatus} categories={templateCategories} themes={templateThemeOptions} statuses={templateStatusOptions} onClear={clearFilters} hasActiveFilters={hasActiveFilters} />

            {!hasActiveFilters && featured.length > 0 && <div><div className="mb-3 flex items-center justify-between"><div><h2 className="text-sm font-semibold text-white">Featured templates</h2><p className="mt-1 text-xs text-slate-500">Polished starting points selected by Cosmic</p></div><span className="rounded-full border border-violet-400/20 bg-violet-400/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-violet-200">Editor picks</span></div><div className="grid gap-4 xl:grid-cols-2">{featured.map((template) => <FeaturedTemplateCard key={template.id} template={template} onPreview={(item) => action("Preview template", item)} onUse={useTemplate} />)}</div></div>}

            <div><div className="mb-3 flex items-center justify-between"><div><h2 className="text-sm font-semibold text-white">Explore templates</h2><p className="mt-1 text-xs text-slate-500">Filter by industry, color family, or collection status.</p></div><span className="text-xs text-slate-500">{filteredTemplates.length} {filteredTemplates.length === 1 ? "template" : "templates"}</span></div>{filteredTemplates.length ? <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"><TemplateCard template={{ ...blankTemplate, sparkCount: 0 }} onPreview={(item) => action("Preview template", item)} onUse={(item) => action("Use template", item)} onSave={(item) => action("Save template", item)} onDuplicate={(item) => action("Duplicate template", item)} />{filteredTemplates.map((template) => <TemplateCard key={template.id} template={template} onPreview={(item) => action("Preview template", item)} onUse={useTemplate} onSave={(item) => action("Save template", item)} onDuplicate={(item) => action("Duplicate template", item)} />)}</div> : <TemplateEmptyState />}</div>

            <TemplateUpgradePrompt
                template={upgradeTemplate}
                currentPlanName={plan?.name || plan?.plan_label}
                onClose={() => setUpgradeTemplate(null)}
            />

            <TemplatePreviewModal
                template={previewTemplate}
                currentPlanName={plan?.name}
                onClose={() => setPreviewTemplate(null)}
                onUse={(template) => {
                    setPreviewTemplate(null);
                    useTemplate(template);
                }}
            />

            <NewWebsiteModal open={Boolean(selectedTemplate)} template={selectedTemplate} onClose={() => setSelectedTemplate(null)} />
        </section>
    );
}
