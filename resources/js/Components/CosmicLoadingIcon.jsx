export default function CosmicLoadingIcon({ className = 'h-4 w-4', label = 'Waiting for Luna' }) {
    return (
        <span
            className={`cosmic-loading-icon cosmic-loading-icon--white inline-block shrink-0 rounded-full ${className}`}
            role="status"
            aria-label={label}
        />
    );
}
