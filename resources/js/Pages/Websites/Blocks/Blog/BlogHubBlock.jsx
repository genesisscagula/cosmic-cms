import { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import axios from "axios";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

export const BlogHubSchema = {
    type: "blog_hub",
    title: "Blog Hub",
    category: "Content",
    sparkGroup: "Blog-Cards",
    freeSparkCount: 3,
    purpose: "Present recent articles in an editorial blog landing page.",
    defaults: {
        layout_variant: "blog-cards-01",
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


function ModalPortal({ children }) {
    if (typeof document === "undefined") return null;
    return createPortal(
        <div className="cosmic-preview-isolation" data-cosmic-preview-isolation="true" data-cosmic-site-preview="true">
            {children}
        </div>,
        document.body
    );
}

function CosmicStatusSelect({ value, onChange }) {
    const [isOpen, setIsOpen] = useState(false);
    const rootRef = useRef(null);
    const options = [
        { value: "draft", label: "Draft", dot: "bg-amber-400", text: "text-amber-200" },
        { value: "published", label: "Published", dot: "bg-emerald-400", text: "text-emerald-200" },
    ];
    const selected = options.find((option) => option.value === value) || options[0];

    useEffect(() => {
        if (!isOpen) return undefined;

        const handlePointerDown = (event) => {
            if (!rootRef.current?.contains(event.target)) setIsOpen(false);
        };
        const handleKeyDown = (event) => {
            if (event.key === "Escape") setIsOpen(false);
        };

        document.addEventListener("pointerdown", handlePointerDown);
        document.addEventListener("keydown", handleKeyDown);
        return () => {
            document.removeEventListener("pointerdown", handlePointerDown);
            document.removeEventListener("keydown", handleKeyDown);
        };
    }, [isOpen]);

    return (
        <div ref={rootRef} className="relative">
            <button
                type="button"
                onClick={() => setIsOpen((current) => !current)}
                aria-haspopup="listbox"
                aria-expanded={isOpen}
                className="cosmic-blog-status-trigger inline-flex h-10 min-w-[142px] items-center justify-between gap-3 rounded-xl border border-white/10 bg-black/20 px-3 text-sm font-semibold text-white transition hover:border-violet-400/60 hover:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-violet-400/40"
            >
                <span className="inline-flex items-center gap-2">
                    <span className={`h-2.5 w-2.5 rounded-full ${selected.dot}`} />
                    <span className={selected.text}>{selected.label}</span>
                </span>
                <span className={`cosmic-blog-status-arrow text-[10px] leading-none text-slate-400 transition-transform ${isOpen ? "rotate-180" : ""}`}>▼</span>
            </button>

            {isOpen && (
                <div
                    role="listbox"
                    className="cosmic-blog-status-menu absolute right-0 top-full z-[1000010] mt-2 w-48 overflow-hidden rounded-xl border border-white/10 bg-[#202024] p-1.5 shadow-2xl shadow-black/60"
                >
                    {options.map((option) => {
                        const isSelected = option.value === value;
                        return (
                            <button
                                key={option.value}
                                type="button"
                                role="option"
                                aria-selected={isSelected}
                                onClick={() => {
                                    onChange(option.value);
                                    setIsOpen(false);
                                }}
                                className={`cosmic-blog-status-option flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm font-semibold transition ${
                                    isSelected
                                        ? "bg-violet-500/20 text-white"
                                        : "text-slate-200 hover:bg-white/[0.07] hover:text-white"
                                }`}
                            >
                                <span className="inline-flex items-center gap-2.5">
                                    <span className={`h-2.5 w-2.5 rounded-full ${option.dot}`} />
                                    {option.label}
                                </span>
                                {isSelected && <span className="text-violet-300">✓</span>}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function normalizeGeneratedHtml(content) {
    const source = String(content || "").trim();
    if (!source) return "";
    if (/<(?:p|h2|h3|ul|ol|blockquote)\b/i.test(source)) return source;

    return source
        .split(/\n{2,}/)
        .map((block) => block.trim())
        .filter(Boolean)
        .map((block) => {
            const escaped = block
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;");
            const isHeading = block.length <= 100 && !/[.!?]$/.test(block);
            return isHeading ? `<h2>${escaped}</h2>` : `<p>${escaped.replace(/\n/g, "<br>")}</p>`;
        })
        .join("\n");
}

function CosmicRichTextEditor({ value, onChange }) {
    const editorRef = useRef(null);

    useEffect(() => {
        if (editorRef.current && editorRef.current.innerHTML !== (value || "")) {
            editorRef.current.innerHTML = value || "";
        }
    }, [value]);

    const runCommand = (command, commandValue = null) => {
        editorRef.current?.focus();
        document.execCommand(command, false, commandValue);
        onChange(editorRef.current?.innerHTML || "");
    };

    return (
        <div className="cosmic-blog-rich-editor overflow-hidden rounded-xl border border-white/10 bg-black/20 focus-within:border-violet-400">
            <div className="cosmic-blog-editor-toolbar flex flex-wrap gap-1 border-b border-white/10 bg-white/[0.03] p-2">
                {[
                    ["Bold", "bold"],
                    ["Italic", "italic"],
                    ["Underline", "underline"],
                    ["• List", "insertUnorderedList"],
                    ["1. List", "insertOrderedList"],
                    ["Quote", "formatBlock", "blockquote"],
                    ["Undo", "undo"],
                    ["Redo", "redo"],
                ].map(([label, command, commandValue]) => (
                    <button key={label} type="button" onClick={() => runCommand(command, commandValue)} className="cosmic-blog-toolbar-button rounded-md px-2.5 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white">{label}</button>
                ))}
            </div>
            <div
                ref={editorRef}
                contentEditable
                suppressContentEditableWarning
                onInput={(event) => onChange(event.currentTarget.innerHTML)}
                data-placeholder="Article content"
                className="cosmic-rich-editor cosmic-scrollbar min-h-[280px] max-h-[420px] overflow-y-auto px-4 py-3 text-sm leading-7 text-white outline-none"
            />
        </div>
    );
}

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
    const variant = data.layout_variant || "blog-cards-01";
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
    const [viewingPost, setViewingPost] = useState(null);
    const [isAiPromptOpen, setIsAiPromptOpen] = useState(false);
    const [aiPrompt, setAiPrompt] = useState("");
    const [isGeneratingPost, setIsGeneratingPost] = useState(false);
    const [isUploadingImage, setIsUploadingImage] = useState(false);
    const [confirmation, setConfirmation] = useState(null);
    const [actionNotice, setActionNotice] = useState("");
    const imageInputRef = useRef(null);

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


    const uploadFeaturedImage = async (event) => {
        const file = event.target.files?.[0];
        if (!file) return;

        setIsUploadingImage(true);
        setPostError("");
        const formData = new FormData();
        formData.append("website_id", blogWebsiteId);
        formData.append("block_index", blockIndex ?? 0);
        formData.append("image", file);

        try {
            const response = await axios.post("/api/update-block-data", formData, {
                headers: { Accept: "application/json" },
            });
            setPostForm((current) => ({ ...current, image_url: response.data.url }));
        } catch (error) {
            setPostError(error.response?.data?.message || "Unable to upload this image.");
        } finally {
            setIsUploadingImage(false);
            event.target.value = "";
        }
    };

    const performGeneratePostWithAi = async () => {
        setConfirmation(null);
        setIsGeneratingPost(true);
        setPostError("");

        try {
            const response = await axios.post(route("blog-posts.generate", [blogWebsiteId, blogPageId]), {
                prompt: aiPrompt.trim(),
            });
            const generated = response.data.post || {};
            const generatedForm = {
                ...postForm,
                title: generated.title || postForm.title,
                category: generated.category || postForm.category,
                tags: Array.isArray(generated.tags) ? generated.tags.join(", ") : postForm.tags,
                excerpt: generated.excerpt || postForm.excerpt,
                content: normalizeGeneratedHtml(generated.content || postForm.content),
                image_url: generated.image_url || postForm.image_url,
            };

            const payload = {
                ...generatedForm,
                tags: generatedForm.tags.split(",").map((tag) => tag.trim()).filter(Boolean),
            };
            const savedResponse = editingPost
                ? await axios.put(route("blog-posts.update", [blogWebsiteId, blogPageId, editingPost.id]), payload)
                : await axios.post(route("blog-posts.store", [blogWebsiteId, blogPageId]), payload);

            const savedPost = savedResponse.data.post;
            if (editingPost) {
                onBlogPostUpdated?.(savedPost);
                setViewingPost((current) => current?.id === savedPost.id ? savedPost : current);
            } else {
                onBlogPostCreated?.(savedPost);
            }

            setIsAiPromptOpen(false);
            setAiPrompt("");
            closeComposer();
        } catch (error) {
            setPostError(error.response?.data?.message || "Cosmic AI could not write this post.");
        } finally {
            setIsGeneratingPost(false);
        }
    };

    const generatePostWithAi = () => {
        if (!aiPrompt.trim() || isGeneratingPost) return;

        if (postForm.title || postForm.content) {
            setConfirmation({
                title: "Replace current post content?",
                message: "Cosmic AI will replace the current title, excerpt, content, category, tags, and featured image before saving the post.",
                confirmLabel: "Replace and generate",
                tone: "violet",
                onConfirm: performGeneratePostWithAi,
            });
            return;
        }

        performGeneratePostWithAi();
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

            const savedPost = response.data.post;
            if (editingPost) {
                onBlogPostUpdated?.(savedPost);
                setViewingPost((current) => current?.id === savedPost.id ? savedPost : current);
            } else {
                onBlogPostCreated?.(savedPost);
            }
            closeComposer();
        } catch (error) {
            setPostError(error.response?.data?.message || "Unable to save this post.");
        } finally {
            setIsSavingPost(false);
        }
    };

    const performDeletePost = async (post) => {
        if (!post?.id) return;
        setConfirmation(null);
        setIsSavingPost(true);
        setPostError("");
        try {
            await axios.delete(route("blog-posts.destroy", [blogWebsiteId, blogPageId, post.id]));
            onBlogPostDeleted?.(post.id);
            setViewingPost((current) => current?.id === post.id ? null : current);
            if (editingPost?.id === post.id) closeComposer();
            setActionNotice("Blog post deleted successfully.");
            window.setTimeout(() => setActionNotice(""), 3000);
        } catch (error) {
            setPostError(error.response?.data?.message || "Unable to delete this post.");
        } finally {
            setIsSavingPost(false);
        }
    };

    const requestDeletePost = (post) => {
        if (!post?.id) return;
        setConfirmation({
            title: "Delete this blog post?",
            message: `“${post.title}” will be permanently deleted. This action cannot be undone.`,
            confirmLabel: "Delete post",
            tone: "danger",
            onConfirm: () => performDeletePost(post),
        });
    };

    const deletePost = () => requestDeletePost(editingPost);

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

                {viewingPost ? (
                    <article className={`${showIntro ? "mt-12" : ""} mx-auto w-full max-w-6xl`}>
                        <button type="button" onClick={() => setViewingPost(null)} className={`mb-5 text-sm font-semibold hover:underline ${theme.text}`}>← Back to all posts</button>
                        {viewingPost.image_url && <img src={viewingPost.image_url} alt={viewingPost.title} className="max-h-[620px] w-full rounded-[15px] object-cover" />}
                        <div className="mx-auto max-w-4xl py-10 sm:py-14">
                            <div className={`text-xs font-semibold uppercase tracking-[0.22em] ${theme.sub}`}>{viewingPost.category || "Article"}{viewingPost.published_at ? ` · ${new Date(viewingPost.published_at).toLocaleDateString()}` : ""}</div>
                            <h1 className={`mt-4 text-4xl font-bold leading-tight tracking-tight sm:text-5xl ${theme.text}`}>{viewingPost.title}</h1>
                            {viewingPost.excerpt && <p className={`mt-5 text-lg leading-8 ${theme.sub}`}>{viewingPost.excerpt}</p>}
                            <div className={`prose prose-slate mt-9 max-w-none text-base leading-8 [&_p]:mb-5 [&_h2]:mb-4 [&_h2]:mt-9 [&_h2]:text-3xl [&_h3]:mb-3 [&_h3]:mt-7 [&_h3]:text-2xl [&_ul]:mb-5 [&_ul]:pl-6 [&_ol]:mb-5 [&_ol]:pl-6 [&_blockquote]:my-6 ${theme.text}`} dangerouslySetInnerHTML={{ __html: viewingPost.content || viewingPost.excerpt || "This article is ready for content." }} />
                            {Array.isArray(viewingPost.tags) && viewingPost.tags.length > 0 && <div className="mt-10 flex flex-wrap gap-2">{viewingPost.tags.map((tag) => <span key={tag} className={`rounded-full border px-3 py-1 text-xs ${theme.border} ${theme.sub}`}>#{tag}</span>)}</div>}
                            {isBuilder && <button type="button" onClick={() => openComposer(viewingPost)} className={`mt-10 text-sm font-semibold hover:underline ${theme.text}`}>Edit this post</button>}
                        </div>
                    </article>
                ) : (
                    <>
                <article className={`${showIntro ? "mt-12" : ""} grid overflow-hidden rounded-3xl border ${theme.border} ${theme.card} ${variant === "blog-cards-02" ? "md:grid-cols-[.8fr_1.2fr]" : variant === "blog-cards-03" ? "md:grid-cols-1" : "md:grid-cols-2"}`}>
                    {hasSavedPosts ? (
                        <img src={featuredPost.image_url || "/storage/cms-images/background/background-1.avif"} alt={featuredPost.title || "Featured article"} className={`h-full w-full object-cover ${variant === "blog-cards-03" ? "h-[240px] sm:h-[340px] lg:h-[420px]" : "min-h-[260px]"}`} />
                    ) : (
                        <EditableImage websiteId={blogWebsiteId} blockIndex={blockIndex} src={data.featured.image_url} alt={data.featured.title} className={`h-full w-full object-cover ${variant === "blog-cards-03" ? "h-[240px] sm:h-[340px] lg:h-[420px]" : "min-h-[260px]"}`} onSave={(image_url) => updateFeatured("image_url", image_url)} />
                    )}
                    <div className="flex min-h-[260px] flex-col justify-center p-7 sm:p-10">
                        {hasSavedPosts ? (
                            <>
                                <span className={`block text-xs font-semibold uppercase tracking-[0.22em] ${theme.sub}`}>{featuredPost.category || "Featured article"}</span>
                                <h3 className={`mt-4 text-3xl font-bold tracking-tight ${theme.text}`}>{featuredPost.title}</h3>
                                {featuredPost.excerpt && <p className={`mt-4 text-base leading-7 ${theme.sub}`}>{featuredPost.excerpt}</p>}
                                {isBuilder && <div className="mt-7 flex items-center gap-4"><button type="button" onClick={() => setViewingPost(featuredPost)} className={`text-sm font-semibold hover:underline ${theme.text}`}>View post</button><button type="button" onClick={() => openComposer(featuredPost)} className={`text-sm font-semibold hover:underline ${theme.text}`}>Edit featured post</button><button type="button" onClick={() => requestDeletePost(featuredPost)} className="ml-auto text-sm font-semibold text-red-600 transition hover:text-red-700 hover:underline">Delete post</button></div>}
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

                <div className={`mt-7 grid gap-5 ${variant === "blog-cards-02" ? "lg:grid-cols-2" : variant === "blog-cards-03" ? "sm:grid-cols-2 lg:grid-cols-3" : "sm:grid-cols-2 lg:grid-cols-4"}`}>
                    {visiblePosts.slice(0, 4).map((post, index) => (
                        <article key={post.id || index} className={`overflow-hidden rounded-2xl border ${theme.border} ${theme.card}`}>
                            {hasSavedPosts ? (
                                <img src={post.image_url || "/storage/cms-images/background/background-1.avif"} alt="" className="h-44 w-full object-cover" />
                            ) : (
                                <EditableImage websiteId={blogWebsiteId} blockIndex={blockIndex} src={post.image_url} alt={post.title} className="h-44 w-full object-cover" onSave={(image_url) => updateStarterPost(index, "image_url", image_url)} />
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
                                        {isBuilder && <div className="mt-4 flex items-center gap-4"><button type="button" onClick={() => setViewingPost(post)} className={`text-sm font-semibold hover:underline ${theme.text}`}>View post</button><button type="button" onClick={() => openComposer(post)} className={`text-sm font-semibold hover:underline ${theme.text}`}>Edit post</button><button type="button" onClick={() => requestDeletePost(post)} className="ml-auto text-sm font-semibold text-red-600 transition hover:text-red-700 hover:underline">Delete post</button></div>}
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

                    </>
                )}
                {isBuilder && !viewingPost && <button type="button" onClick={() => openComposer()} className={`mt-7 rounded-xl border px-4 py-2 text-sm font-semibold transition hover:bg-white/10 ${theme.border} ${theme.text}`}>+ Add post</button>}

                {actionNotice && (
                    <ModalPortal>
                        <div className="fixed bottom-6 right-6 z-[1000001] rounded-xl border border-emerald-400/30 bg-[#18181b] px-4 py-3 text-sm font-semibold text-emerald-300 shadow-2xl">
                            ✓ {actionNotice}
                        </div>
                    </ModalPortal>
                )}

                {isAiPromptOpen && (
                    <ModalPortal>
                    <div className="fixed inset-0 z-[999999] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
                        <div className="w-full max-w-xl rounded-2xl border border-white/10 bg-[#18181b] p-6 shadow-2xl">
                            <div className="flex items-start justify-between gap-4"><div><h3 className="text-lg font-semibold text-white">Write this blog post with AI</h3><p className="mt-1 text-sm text-slate-400">Describe the topic, audience, tone, and key points you want included.</p></div><button type="button" onClick={() => setIsAiPromptOpen(false)} className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-white/10 text-2xl leading-none text-slate-400 transition hover:bg-white/10 hover:text-white">×</button></div>
                            <textarea autoFocus value={aiPrompt} onChange={(event) => setAiPrompt(event.target.value)} rows="6" placeholder="Example: Write a practical guide for homeowners choosing a construction company for a renovation..." className="mt-5 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-3 text-white outline-none focus:border-violet-400" />
                            <div className="mt-4 flex justify-end gap-2"><button type="button" onClick={() => setIsAiPromptOpen(false)} className="px-3 py-2 text-sm text-slate-300">Cancel</button><button type="button" disabled={!aiPrompt.trim() || isGeneratingPost} onClick={generatePostWithAi} className="cosmic-blog-save-button rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 disabled:opacity-50">{isGeneratingPost ? <span className="inline-flex items-center gap-2"><span className="cosmic-loading-spinner h-4 w-4 rounded-full" />Generating article...</span> : "Generate Article · 10 Credits"}</button></div>
                        </div>
                    </div>
                    </ModalPortal>
                )}

                {confirmation && (
                    <ModalPortal>
                    <div className="fixed inset-0 z-[1000000] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm" role="alertdialog" aria-modal="true">
                        <div className="w-full max-w-md rounded-2xl border border-white/10 bg-[#18181b] p-6 shadow-2xl">
                            <h3 className="text-lg font-semibold text-white">{confirmation.title}</h3>
                            <p className="mt-2 text-sm leading-6 text-slate-400">{confirmation.message}</p>
                            <div className="mt-6 flex justify-end gap-2">
                                <button type="button" onClick={() => setConfirmation(null)} className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-white/5">Cancel</button>
                                <button type="button" onClick={confirmation.onConfirm} className={`rounded-lg px-4 py-2 text-sm font-semibold text-white ${confirmation.tone === "danger" ? "bg-red-600 hover:bg-red-500" : "bg-violet-600 hover:bg-violet-500"}`}>{confirmation.confirmLabel}</button>
                            </div>
                        </div>
                    </div>
                    </ModalPortal>
                )}

                {isComposerOpen && (
                    <ModalPortal>
                    <div className="fixed inset-0 z-[999998] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="blog-post-dialog-title">
                        <form onSubmit={savePost} className="cosmic-blog-editor-modal cosmic-scrollbar max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-white/10 bg-[#18181b] p-6 shadow-2xl">
                            <div className="flex items-start justify-between gap-4">
                                <div><h3 id="blog-post-dialog-title" className="text-lg font-semibold text-white">{editingPost ? "Edit blog post" : "Add blog post"}</h3><p className="mt-1 text-sm text-slate-400">{editingPost ? "Update the article details and publish state." : "Create a draft article for this Blog Hub."}</p></div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <CosmicStatusSelect
                                        value={postForm.status}
                                        onChange={(status) => setPostForm((current) => ({ ...current, status }))}
                                    />
                                    <button type="button" onClick={closeComposer} className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 text-2xl leading-none text-slate-400 transition hover:border-white/20 hover:bg-white/10 hover:text-white" aria-label="Close">×</button>
                                </div>
                            </div>
                            <button type="button" onClick={() => setIsAiPromptOpen(true)} className="cosmic-blog-ai-button mt-5 w-full rounded-xl border border-violet-400/40 bg-violet-500/10 px-4 py-3 text-sm font-semibold text-violet-200 transition hover:bg-violet-500/20">✨ Write with AI · 10 Credits</button>
                            <div className="mt-5 grid gap-3">
                                <input required value={postForm.title} onChange={(event) => setPostForm({ ...postForm, title: event.target.value })} placeholder="Post title" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-white outline-none focus:border-violet-400" />
                                <input value={postForm.category} onChange={(event) => setPostForm({ ...postForm, category: event.target.value })} placeholder="Categories, separated by commas" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-white outline-none focus:border-violet-400" />
                                <input value={postForm.tags} onChange={(event) => setPostForm({ ...postForm, tags: event.target.value })} placeholder="Tags, separated by commas" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-white outline-none focus:border-violet-400" />
                                <div className="cosmic-blog-image-panel rounded-xl border border-white/10 bg-black/20 p-3">
                                    <input ref={imageInputRef} type="file" accept="image/*" className="hidden" onChange={uploadFeaturedImage} />
                                    {postForm.image_url ? <img src={postForm.image_url} alt="Featured preview" className="h-36 w-full rounded-lg object-cover" /> : <div className="cosmic-blog-image-empty flex h-28 items-center justify-center rounded-lg border border-dashed border-white/15 text-sm text-slate-500">No featured image selected</div>}
                                    <div className="mt-3 flex gap-2"><button type="button" disabled={isUploadingImage} onClick={() => imageInputRef.current?.click()} className="cosmic-blog-image-button rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-950 disabled:opacity-50">{isUploadingImage ? "Uploading..." : "Update Image"}</button>{postForm.image_url && <button type="button" onClick={() => setPostForm({ ...postForm, image_url: "" })} className="px-3 py-2 text-sm text-slate-300">Remove</button>}</div>
                                </div>
                                <textarea value={postForm.excerpt} onChange={(event) => setPostForm({ ...postForm, excerpt: event.target.value })} placeholder="Short excerpt" rows="3" className="rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-white outline-none focus:border-violet-400" />
                                <CosmicRichTextEditor value={postForm.content} onChange={(content) => setPostForm((current) => ({ ...current, content }))} />
                            </div>
                            {postError && <p className="mt-3 text-sm text-red-300">{postError}</p>}
                            <div className="mt-5 flex flex-wrap items-center justify-between gap-2">
                                {editingPost ? <button type="button" disabled={isSavingPost} onClick={deletePost} className="text-sm font-semibold text-red-300 hover:text-red-200 disabled:opacity-50">Delete post</button> : <span />}
                                <div className="flex gap-2"><button type="button" onClick={closeComposer} className="px-3 py-2 text-sm text-slate-300">Cancel</button><button disabled={isSavingPost} className="cosmic-blog-save-button rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 disabled:opacity-50">{isSavingPost ? "Saving..." : editingPost ? "Save post" : "Create draft post"}</button></div>
                            </div>
                        </form>
                    </div>
                    </ModalPortal>
                )}
            </div>
        </section>
    );
}
