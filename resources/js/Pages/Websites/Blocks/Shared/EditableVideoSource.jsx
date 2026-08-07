import { useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";

export function getVideoEmbedUrl(videoUrl) {
    if (!videoUrl || videoUrl === "#" || videoUrl.startsWith("/")) return null;

    try {
        const parsed = new URL(videoUrl);
        const host = parsed.hostname.toLowerCase().replace(/^www\./, "");
        let id = null;

        if (host === "youtu.be") {
            id = parsed.pathname.split("/").filter(Boolean)[0];
        } else if (host === "youtube.com" || host === "m.youtube.com") {
            id = parsed.searchParams.get("v") || parsed.pathname.match(/\/(?:embed|shorts)\/([^/?]+)/)?.[1];
        }

        if (id && /^[A-Za-z0-9_-]{6,}$/.test(id)) {
            return `https://www.youtube-nocookie.com/embed/${id}?autoplay=1&mute=1&loop=1&playlist=${id}&controls=1&playsinline=1&rel=0&modestbranding=1`;
        }

        if (host === "vimeo.com" || host.endsWith(".vimeo.com")) {
            id = parsed.pathname.match(/\/(\d+)/)?.[1];
            if (id) return `https://player.vimeo.com/video/${id}?autoplay=1&muted=1&loop=1&title=0&byline=0&portrait=0`;
        }
    } catch {
        // Relative/self-hosted MP4 URLs are handled by the native video element.
    }

    return null;
}

export function EditableVideoSource({ value, posterImageUrl, isOpen, onClose, onSave, title = "Edit background video" }) {
    const [videoUrl, setVideoUrl] = useState(value === "#" ? "" : value || "");

    useEffect(() => {
        if (isOpen) setVideoUrl(value === "#" ? "" : value || "");
    }, [isOpen, value]);

    const previewUrl = videoUrl.trim();
    const embedUrl = useMemo(() => getVideoEmbedUrl(previewUrl), [previewUrl]);

    if (!isOpen) return null;

    const close = () => {
        setVideoUrl(value === "#" ? "" : value || "");
        onClose();
    };

    return createPortal(
        <div className="fixed inset-0 z-[999999] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm" onMouseDown={close}>
            <div role="dialog" aria-modal="true" className="w-full max-w-3xl overflow-hidden rounded-2xl border border-white/10 bg-[#17181c] text-left shadow-2xl" onMouseDown={(event) => event.stopPropagation()}>
                <div className="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4 sm:px-6">
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-violet-300">Video media</p>
                        <h3 className="mt-1 text-lg font-semibold text-white">{title}</h3>
                        <p className="mt-1 text-xs leading-5 text-slate-400">Use a direct MP4, YouTube, or Vimeo URL. Published background video stays muted, looping, and inline.</p>
                    </div>
                    <button type="button" onClick={close} className="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/10 hover:text-white" aria-label="Close video dialog">×</button>
                </div>

                <div className="space-y-4 p-5 sm:p-6">
                    <label className="block">
                        <span className="text-xs font-medium text-slate-300">Video URL</span>
                        <input autoFocus type="url" value={videoUrl} onChange={(event) => setVideoUrl(event.target.value)} placeholder="https://www.youtube.com/watch?v=... or /storage/...mp4" className="mt-2 w-full rounded-xl border border-white/10 bg-black/25 px-3 py-3 text-sm text-white outline-none placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" />
                    </label>

                    <div className="relative aspect-video overflow-hidden rounded-xl border border-white/10 bg-black">
                        {embedUrl ? (
                            <iframe key={embedUrl} src={embedUrl} title="Video preview" allow="autoplay; fullscreen; picture-in-picture" className="h-full w-full border-0" />
                        ) : previewUrl ? (
                            <video key={previewUrl} autoPlay muted loop controls playsInline poster={posterImageUrl} className="h-full w-full object-cover">
                                <source src={previewUrl} type="video/mp4" />
                            </video>
                        ) : posterImageUrl ? (
                            <img src={posterImageUrl} alt="Video poster preview" className="h-full w-full object-cover opacity-65" />
                        ) : (
                            <div className="flex h-full items-center justify-center px-6 text-center text-sm text-slate-500">Paste a video URL to preview it here.</div>
                        )}
                    </div>

                    <p className="text-xs leading-5 text-slate-500">The poster image is used as the mobile and no-video fallback. Edit it separately from the poster thumbnail in the Spark.</p>

                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={close} className="rounded-lg px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white">Cancel</button>
                        <button type="button" disabled={!previewUrl} onClick={() => { onSave(previewUrl); onClose(); }} className="rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-50">Save video</button>
                    </div>
                </div>
            </div>
        </div>,
        document.body
    );
}
