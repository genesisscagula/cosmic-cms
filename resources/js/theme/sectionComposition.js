const hasMedia = (block = {}) => Object.entries(block).some(([key, value]) =>
    typeof value === 'string' && value.trim() !== '' && /(image|video|background)/i.test(key)
);

const autoRole = (block = {}, index, style, pattern, previous = null) => {
    const explicit = String(block?.theme || 'auto').toLowerCase();
    if (explicit && explicit !== 'auto') return explicit;

    const type = String(block?.type || '').toLowerCase();
    let role = pattern[index % Math.max(1, pattern.length)] || 'surface';
    if (type.startsWith('hero_')) role = hasMedia(block) ? 'surface' : (style === 'premium' ? 'accent_tint' : 'white');
    else if (type.includes('cta') || type.includes('contact') || type.includes('booking')) role = style === 'clean' ? 'accent_tint' : 'neutral_dark';
    else if (type.includes('testimonial') || type.includes('logo') || type.includes('trust') || type.includes('stats')) role = style === 'premium' ? 'surface_alt' : 'surface';
    else if (type.includes('faq')) role = 'surface_alt';

    if (previous === role) role = ({white:'surface_alt',surface:'white',surface_alt:'surface',accent_tint:'white',neutral_dark:'surface',primary:'surface'})[role] || 'surface_alt';
    if (role === 'primary') role = 'accent_tint';
    return ['white','surface','surface_alt','accent_tint','neutral_dark'].includes(role) ? role : 'surface';
};

export function resolveSectionComposition(blocks = [], index, style, pattern) {
    let previous = null;
    let role = 'surface';
    for (let i = 0; i <= index; i += 1) {
        role = autoRole(blocks[i] || {}, i, style, pattern, previous);
        previous = role;
    }
    return role;
}
