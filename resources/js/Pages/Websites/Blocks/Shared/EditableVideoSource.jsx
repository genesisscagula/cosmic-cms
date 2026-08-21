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

export function EditableVideoSource() { return null; }
