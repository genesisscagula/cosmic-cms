const AVATAR_COUNT = 6;

export function getCosmicAvatar(index = 0) {
    const numericIndex = Number(index);
    const safeIndex = Number.isFinite(numericIndex) ? numericIndex : 0;
    const avatarNumber = ((safeIndex % AVATAR_COUNT) + AVATAR_COUNT) % AVATAR_COUNT + 1;
    return `/storage/cms-images/avatars/avatar-${avatarNumber}.jpg`;
}

export function normalizeTestimonialAvatar(value, index = 0) {
    const fallback = getCosmicAvatar(index);
    const raw = typeof value === "string" ? value.trim() : "";

    if (!raw) {
        return fallback;
    }

    // Batch hotfix: early AI page generation saved testimonial placeholders as
    // /cosmic-images/avatar/avatar-N.svg even though the real avatar library
    // lives in storage/app/public/cms-images/avatars/avatar-N.jpg. Keep old pages
    // working without requiring a database migration.
    const legacyMatch = raw.match(/(?:^|\/)(?:cosmic-images\/avatar|cms-images\/avatars)\/avatar-(\d+)\.(?:svg|jpg|jpeg|png)(?:[?#].*)?$/i);
    if (legacyMatch) {
        const avatarNumber = Math.max(1, Math.min(AVATAR_COUNT, Number(legacyMatch[1]) || 1));
        return `/storage/cms-images/avatars/avatar-${avatarNumber}.jpg`;
    }

    // Normalize an accidentally pasted local Laravel storage file URL/path.
    const storageMatch = raw.replace(/\\/g, "/").match(/storage\/app\/public\/cms-images\/avatars\/avatar-(\d+)\.(?:jpg|jpeg|png)(?:[?#].*)?$/i);
    if (storageMatch) {
        const avatarNumber = Math.max(1, Math.min(AVATAR_COUNT, Number(storageMatch[1]) || 1));
        return `/storage/cms-images/avatars/avatar-${avatarNumber}.jpg`;
    }

    return raw;
}

export function resolveTestimonialAvatar(item = {}, index = 0) {
    return normalizeTestimonialAvatar(item.avatar || item.avatar_url, index);
}
