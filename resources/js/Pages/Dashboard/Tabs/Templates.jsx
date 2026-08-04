import { useMemo, useState } from "react";
import NewWebsiteModal from "../Components/NewWebsiteModal";
import TemplateCard, { FeaturedTemplateCard } from "../Components/TemplateCard";
import TemplateEmptyState from "../Components/TemplateEmptyState";
import TemplateToolbar from "../Components/TemplateToolbar";
import { blankTemplate, getTemplatePresentation, industryTemplates, templateCategories, templateStatusOptions, templateThemeOptions } from "../../../data/industryTemplates";

export default function Templates() {
    const [query, setQuery] = useState("");
    const [category, setCategory] = useState("All");
    const [theme, setTheme] = useState("All themes");
    const [status, setStatus] = useState("All templates");
    const [sort, setSort] = useState("featured");
    const [selectedTemplate, setSelectedTemplate] = useState(null);

    const templates = useMemo(() => industryTemplates.map(getTemplatePresentation), []);
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
                && matchesStatus(template)
                && (!search || [template.name, template.category, template.industry, template.themeId, template.description, ...template.tags].join(" ").toLowerCase().includes(search)))
            .sort(sorters[sort]);
    }, [templates, query, category, theme, status, sort]);

    const featured = templates.filter((template) => template.featured).sort((a, b) => b.usage - a.usage).slice(0, 4);
    const useTemplate = (template) => setSelectedTemplate(template);
    const action = (label, template) => console.info(label, template.id);
    const clearFilters = () => { setQuery(""); setCategory("All"); setTheme("All themes"); setStatus("All templates"); setSort("featured"); };
    const hasActiveFilters = Boolean(query) || category !== "All" || theme !== "All themes" || status !== "All templates" || sort !== "featured";

    return (
        <section className="space-y-7">
            <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-sm font-medium text-violet-300">Template library</p><h1 className="mt-2 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Templates</h1><p className="mt-2 text-sm text-slate-400">Hand-curated foundations built from reusable Cosmic Sparks.</p></div><button type="button" onClick={() => console.info("Create template")} className="inline-flex h-10 items-center justify-center rounded-xl bg-white px-4 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">Create Template</button></header>

            <TemplateToolbar query={query} onQueryChange={setQuery} category={category} onCategoryChange={setCategory} sort={sort} onSortChange={setSort} theme={theme} onThemeChange={setTheme} status={status} onStatusChange={setStatus} categories={templateCategories} themes={templateThemeOptions} statuses={templateStatusOptions} onClear={clearFilters} hasActiveFilters={hasActiveFilters} />

            {!hasActiveFilters && featured.length > 0 && <div><div className="mb-3 flex items-center justify-between"><div><h2 className="text-sm font-semibold text-white">Featured templates</h2><p className="mt-1 text-xs text-slate-500">Polished starting points selected by Cosmic</p></div><span className="rounded-full border border-violet-400/20 bg-violet-400/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-violet-200">Editor picks</span></div><div className="grid gap-4 xl:grid-cols-2">{featured.map((template) => <FeaturedTemplateCard key={template.id} template={template} onPreview={(item) => action("Preview template", item)} onUse={useTemplate} />)}</div></div>}

            <div><div className="mb-3 flex items-center justify-between"><div><h2 className="text-sm font-semibold text-white">Explore templates</h2><p className="mt-1 text-xs text-slate-500">Filter by industry, color family, or collection status.</p></div><span className="text-xs text-slate-500">{filteredTemplates.length} {filteredTemplates.length === 1 ? "template" : "templates"}</span></div>{filteredTemplates.length ? <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"><TemplateCard template={{ ...blankTemplate, sparkCount: 0 }} onPreview={(item) => action("Preview template", item)} onUse={(item) => action("Use template", item)} onSave={(item) => action("Save template", item)} onDuplicate={(item) => action("Duplicate template", item)} />{filteredTemplates.map((template) => <TemplateCard key={template.id} template={template} onPreview={(item) => action("Preview template", item)} onUse={useTemplate} onSave={(item) => action("Save template", item)} onDuplicate={(item) => action("Duplicate template", item)} />)}</div> : <TemplateEmptyState />}</div>

            <NewWebsiteModal open={Boolean(selectedTemplate)} template={selectedTemplate} onClose={() => setSelectedTemplate(null)} />
        </section>
    );
}
