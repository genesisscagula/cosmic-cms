export default function CosmicBrandMark({ className = '', size = 'md', label = 'Cosmic CMS' }) {
    const sizeClass = size === 'sm' ? 'h-8 w-8 rounded-lg text-sm' : size === 'lg' ? 'h-11 w-11 rounded-2xl text-lg' : 'h-9 w-9 rounded-xl text-base';

    return (
        <span
            aria-label={label}
            role="img"
            className={`cosmic-brand-mark inline-flex shrink-0 items-center justify-center bg-gradient-to-br from-emerald-600 to-teal-500 font-black text-white ${sizeClass} ${className}`}
        >
            <span aria-hidden="true">✦</span>
        </span>
    );
}
