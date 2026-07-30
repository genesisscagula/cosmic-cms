import { useState } from "react";
import axios from "axios";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

export const BlogHubSchema = {
    type: "blog_hub",
    title: "Blog Hub",
    category: "Content",
    purpose: "Present recent articles in an editorial blog landing page.",
    defaults: {
        eyebrow: "Latest insights",
        heading: "Ideas for building a better business",
        text: "Practical notes, useful perspectives, and updates from our team.",
        featured: {
            category: "Featured article",
            title: "A clearer way to plan your next project",
            excerpt: "Thoughtful guidance for turning a good idea into a focused, useful website.",
            image_url: "/storage/cms-images/background/background-1.avif",
            cta_label: "Read article",
        },
        posts: [
            { category: "Strategy", title: "Start with the problem worth solving", excerpt: "A simple framework for making your first website decisions clearer.", image_url: "/storage/cms-images/background/background-2.avif" },
            { category: "Design", title: "Consistency earns customer trust", excerpt: "A focused visual system helps every page feel more credible.", image_url: "/storage/cms-images/background/background-3.avif" },
            { category: "Updates", title: "What a publish-ready website needs", excerpt: "The details that help you go from draft to a confident launch.", image_url: "/storage/cms-images/background/background-5.avif" },
            { category: "Growth", title: "Make your next update easier to manage", excerpt: "Keep content and customer questions organized.", image_url: "/storage/cms-images/background/background-1.avif" },
        ],
    },
};

const emptyPostForm = {
    title: "",
    excerpt: "",
    content: "",
    category: "",
    tags: "",
    image_url: "",
    status: "draft",
};

export function BlogHubBlock({
    block,
    onUpdate,
    globalTheme,
    blogPosts = [],
    onBlogPostCreated,
    onBlogPostUpdated,
    onBlogPostDeleted,
    blockIndex,
    blogWebsiteId,
    blogPageId,
}) {
    // Blog reading surfaces stay neutral and readable across every website
    // theme. The surrounding page blocks still use the selected website theme.
    const theme = {
        bg: "bg-[#fcfcfb]",
        card: "bg-white",
        text: "text-slate-900",
        sub: "text-slate-600",
        border: "border-slate-200",
    };
    const data = {
        ...BlogHubSchema.defaults,
        ...block,
        featured: { ...BlogHubSchema.defaults.featured, ...(block.featured || {}) },
        posts: Array.isArray(block.posts) && block.posts.length ? block.posts : BlogHubSchema.defaults.posts,
    };
    const isBuilder = blockIndex !== undefined;
    // Existing Blog Hub blocks included their own intro. New Posts / updates
    // pages use Blog Mini Hero instead, while old saved pages keep this intro.
    const showIntro = block.show_intro !== false;
    const hasSavedPosts = blogPosts.length > 0;
    const featuredPost = hasSavedPosts
        ? blogPosts.find((post) => post.is_featured) || blogPosts[0]
        : data.featured;
    const visiblePosts = hasSavedPosts
        ? blogPosts.filter((post) => post.id !== featuredPost.id).slice(0, 4)
        : data.posts;
    const [isComposerOpen, setIsComposerOpen] = useState(false);
    const [editingPost, setEditingPost] = useState(null);
    const [isSavingPost, setIsSavingPost] = useState(false);
    const [postError, setPostError] = useState("");
    const [postForm, setPostForm] = useState(emptyPostForm);

    const updateFeatured = (key, value) => onUpdate({ featured: { ...data.featured, [key]: value } });
    const updateStarterPost = (index, key, value) => onUpdate({
        posts: data.posts.map((post, postIndex) => postIndex === index ? { ...post, [key]: value } : post),
    });

    const closeComposer = () => {
        setIsComposerOpen(false);
        setEditingPost(null);
        setPostError("");
        setPostForm(emptyPostForm);
    };

    const openComposer = (post = null) => {
        setEditingPost(post);
        setPostError("");
        setPostForm(post ? {
            title: post.title || "",
            excerpt: post.excerpt || "",
            content: post.content || "",
            category: post.category || "",
            tags: Array.isArray(post.tags) ? post.tags.join(", ") : "",
            image_url: post.image_url || "",
            status: post.status || "draft",
        } : emptyPostForm);
        setIsComposerOpen(true);
    };

    const savePost = async (event) => {
        event.preventDefault();
        setIsSavingPost(true);
        setPostError("");

        const payload = {
            ...postForm,
            tags: postForm.tags.split(",").map((tag) => tag.trim()).filter(Boolean),
        };

        try {
            const response = editingPost
                ? await axios.put(route("blog-posts.update", [blogWebsiteId, blogPageId, editingPost.id]), payload)
                : await axios.post(route("blog-posts.store", [blogWebsiteId, blogPageId]), payload);

            if (editingPost) onBlogPostUpdated?.(response.data.post);
            else onBlogPostCreated?.(response.data.post);
            closeComposer();
        } catch (error) {
            setPostError(error.response?.data?.message || "Unable to save this post.");
        } finally {
            setIsSavingPost(false);
        }
    };

    const deletePost = async () => {
        if (!editingPost || !window.confirm(`Delete “${editingPost.title}”? This cannot be undone.`)) return;

        setIsSavingPost(true);
        setPostError("");
        try {
            await axios.delete(route("blog-posts.destroy", [blogWebsiteId, blogPageId, editingPost.id]));
            onBlogPostDeleted?.(editingPost.id);
            closeComposer();
        } catch (error) {
            setPostError(error.response?.data?.message || "Unable to delete this post.");
        } finally {
            setIsSavingPost(false);
        }
    };

    return (
        <section className={`px-6 py-16 sm:px-8 lg:px-12 lg:py-24 ${theme.bg} transition-colors duration-500`}>
            <div className="mx-auto max-w-7xl">
                {showIntro && (
                    <div className="max-w-3xl">
                        <EditableText value={data.eyebrow} className={`block text-xs font-semibold uppercase tracking-[0.28em] ${theme.sub}`} onSave={(eyebrow) => onUpdate({ eyebrow })} />
                        <EditableText value={data.heading} className={`mt-4 block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${theme.text}`} onSave={(heading) => onUpdate({ heading })} />
                        <EditableText value={data.text} isTextArea className={`mt-5 block max-w-2xl text-base leading-7 ${theme.sub}`} onSave={(text) => onUpdate({ text })} />
                    </div>
                )}

                <article className={`${showIntro ? "mt-12" : ""} grid overflow-hidden rounded-3xl border ${theme.border} ${theme.card} md:grid-cols-2`}>
                    {hasSavedPosts ? (
                        <img src={featuredPost.image_url || "/storage/cms-images/background/background-1.avif"} alt={featuredPost.title || "Featured article"} className="h-full min-h-[260px] w-full object-cover" />
                    ) : (
                        <EditableImage src={data.featured.image_url} alt={data.featured.title} className="h-full min-h-[260px] w-full object-cover" onSave={(image_url) => updateFeatured("image_url", image_url)} />
                    )}
                    <div className="flex min-h-[260px] flex-col justify-center p-7 sm:p-10">
                        {hasSavedPosts ? (
                            <>
                                <span className={`block text-xs font-semibold uppercase tracking-[0.22em] ${theme.sub}`}>{featuredPost.category || "Featured article"}</span>
                                <h3 className={`mt-4 text-3xl font-bold tracking-tight ${theme.text}`}>{featuredPost.title}</h3>
                                {featuredPost.excerpt && <p className={`mt-4 text-base leading-7 ${theme.sub}`}>{featuredPost.excerpt}</p>}
                                {isBuilder && <button type="button" onClick={() => openComposer(featuredPost)} className={`mt-7 w-fit text-sm font-semibold hover:underline ${theme.text}`}>Edit featured post</button>}
                            </>
                        ) : (
                            <>
                                <EditableText value={data.featured.category} className={`block text-xs font-semibold uppercase tracking-[0.22em] ${theme.sub}`} onSave={(category) => updateFeatured("category", category)} />
                                <EditableText value={data.featured.title} className={`mt-4 block text-3xl font-bold tracking-tight ${theme.text}`} onSave={(title) => updateFeatured("title", title)} />
                                <EditableText value={data.featured.excerpt} isTextArea className={`mt-4 block text-base leading-7 ${theme.sub}`} onSave={(excerpt) => updateFeatured("excerpt", excerpt)} />
                                <EditableText value={data.featured.cta_label} className={`mt-7 block text-sm font-semibold ${theme.text}`} onSave={(cta_label) => updateFeatured("cta_label", cta_label)} />
                            </>
                        )}
                    </div>
                </article>

                <div className="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {visiblePosts.slice(0, 4).map((post, index) => (
                        <article key={post.id || index} className={`overflow-hidden rounded-2xl border ${theme.border} ${theme.card}`}>
                            {hasSavedPosts ? (
                                <img src={post.image_url || "/storage/cms-images/background/background-1.avif"} alt="" className="h-44 w-full object-cover" />
                            ) : (
                                <EditableImage src={post.image_url} alt={post.title} className="h-44 w-full object-cover" onSave={(image_url) => updateStarterPost(index, "image_url", image_url)} />
                            )}
                            <div className="p-5">
                                {hasSavedPosts ? (
                                    <>
                                        <div className="flex items-center justify-between gap-2">
                                            <span className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${theme.sub}`}>{post.category || "Article"}</span>
                                            {isBuilder && <span className={`rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase ${post.status === "published" ? "border-emerald-400/30 text-emerald-400" : "border-amber-400/30 text-amber-300"}`}>{post.status}</span>}
                                        </div>
                                        <h3 className={`mt-3 text-lg font-bold leading-snug ${theme.text}`}>{post.title}</h3>
                                        {post.excerpt && <p className={`mt-3 text-sm leading-6 ${theme.sub}`}>{post.excerpt}</p>}
                                        {isBuilder && <button type="button" onClick={() => openComposer(post)} className={`mt-4 text-sm font-semibold hover:underline ${theme.text}`}>Edit post</button>}
                                    </>
                                ) : (
                                    <>
                                        <EditableText value={post.category || "Article"} className={`block text-[11px] font-semibold uppercase tracking-[0.2em] ${theme.sub}`} onSave={(category) => updateStarterPost(index, "category", category)} />
                                        <EditableText value={post.title} className={`mt-3 block text-lg font-bold leading-snug ${theme.text}`} onSave={(title) => updateStarterPost(index, "title", title)} />
                                        <EditableText value={post.excerpt || ""} isTextArea className={`mt-3 block text-sm leading-6 ${theme.sub}`} onSave={(excerpt) => updateStarterPost(index, "excerpt", excerpt)} />
                                    </>
                                )}
                            </div>
                        </article>
                    ))}
                </div>

                {isBuilder && <button type="button" onClick={() => openComposer()} className={`mt-7 rounded-xl border px-4 py-2 text-sm font-semibold transition hover:bg-white/10 ${theme.border} ${theme.text}`}>+ Add post</button>}

                {isComposerOpen && (
                    <div className="fixed inset-0 z-[1000] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="blog-post-dialog-title">
                        <form onSubmit={savePost} className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-white/10 bg-[#18181b] p-5 shadow-2xl">
                            <div className="flex items-start justify-between gap-4">
                                <div><h3 id="blog-post-dialog-title" className="text-lg font-semibold text-white">{editingPost ? "Edit blog post" : "Add blog post"}</h3><p className="mt-1 text-sm text-slate-400">{editingPost ? "Update the article details and publish state." : "Create a draft article for this Blog Hub."}</p></div>
                                <button type="button" onClick={closeComposer} className="text-slate-400 hover:text-white" aria-label="Close">×</button>
                            </div>
                            <div className="mt-5 grid gap-3">
                                <input required value={postForm.title} onChange={(event) => setPostForm({ ...postForm, title: event.target.value })} placeholder="Post title" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-white outline-none focus:border-violet-400" />
                                <input value={postForm.category} onChange={(event) => setPostForm({ ...postForm, category: event.target.value })} placeholder="Category" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-white outline-none focus:border-violet-400" />
                                <input value={postForm.tags} onChange={(event) => setPostForm({ ...postForm, tags: event.target.value })} placeholder="Tags, separated by commas" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-white outline-none focus:border-violet-400" />
                                <input value={postForm.image_url} onChange={(event) => setPostForm({ ...postForm, image_url: event.target.value })} placeholder="Image URL (optional)" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-white outline-none focus:border-violet-400" />
                                <textarea value={postForm.excerpt} onChange={(event) => setPostForm({ ...postForm, excerpt: event.target.value })} placeholder="Short excerpt" rows="3" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-white outline-none focus:border-violet-400" />
                                <textarea value={postForm.content} onChange={(event) => setPostForm({ ...postForm, content: event.target.value })} placeholder="Article content" rows="7" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-white outline-none focus:border-violet-400" />
                                <label className="grid gap-1 text-sm font-medium text-slate-200">Post status<select value={postForm.status} onChange={(event) => setPostForm({ ...postForm, status: event.target.value })} className="rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-white outline-none focus:border-violet-400"><option value="draft">Draft</option><option value="published">Published</option></select></label>
                            </div>
                            {postError && <p className="mt-3 text-sm text-red-300">{postError}</p>}
                            <div className="mt-5 flex flex-wrap items-center justify-between gap-2">
                                {editingPost ? <button type="button" disabled={isSavingPost} onClick={deletePost} className="text-sm font-semibold text-red-300 hover:text-red-200 disabled:opacity-50">Delete post</button> : <span />}
                                <div className="flex gap-2"><button type="button" onClick={closeComposer} className="px-3 py-2 text-sm text-slate-300">Cancel</button><button disabled={isSavingPost} className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 disabled:opacity-50">{isSavingPost ? "Saving..." : editingPost ? "Save post" : "Create draft post"}</button></div>
                            </div>
                        </form>
                    </div>
                )}
            </div>
        </section>
    );
}
