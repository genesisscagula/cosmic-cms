import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import MediaPickerModal from '../../../Components/Media/MediaPickerModal';

const safeMediaUrl = value => /^(https?:\/\/|\/(?!\/))/i.test(String(value || '').trim());

export default function SectionBackgroundControl({ block, surface, websiteId, onUpdate, trialMode = false }) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState({});
    const [picker, setPicker] = useState(null);
    const [error, setError] = useState('');
    const trigger = useRef(null);
    const panel = useRef(null);
    const close = () => { setOpen(false); setPicker(null); trigger.current?.focus(); };
    useEffect(() => { if (open) panel.current?.focus(); }, [open]);
    const start = () => {
        setDraft({ type: block.universal_background_type || 'image', image: block.universal_background_image_url || '', video: block.universal_background_video_url || '', poster: block.universal_background_video_poster_url || '', opacity: block.universal_background_overlay_opacity ?? 72, position: block.universal_background_position || 'center center', size: block.universal_background_size || 'cover' });
        setError(''); setOpen(true);
    };
    const patch = fields => setDraft(current => ({ ...current, ...fields }));
    const apply = () => {
        const url = String(draft[draft.type] || '').trim();
        if (!safeMediaUrl(url) || (draft.poster && !safeMediaUrl(draft.poster))) { setError('Use an http(s) URL or a media path starting with /.'); return; }
        onUpdate({ universal_background_enabled: true, universal_background_type: draft.type, universal_background_image_url: draft.type === 'image' ? url : '', universal_background_video_url: draft.type === 'video' ? url : '', universal_background_video_poster_url: draft.poster.trim(), universal_background_overlay_mode: 'surface', universal_background_overlay: '', universal_background_overlay_opacity: Number(draft.opacity), universal_background_position: draft.position, universal_background_size: draft.size });
        close();
    };
    return <>
        <button ref={trigger} type="button" className="cosmic-section-background-trigger" onClick={start}>Background{block.universal_background_enabled ? ' · On' : ''}</button>
        {open && createPortal(<div className="cosmic-section-background-backdrop" onMouseDown={event => { if (event.target === event.currentTarget) close(); }}>
            <div ref={panel} role="dialog" aria-modal="true" aria-label="Section background" tabIndex={-1} className="cosmic-section-background-panel" onKeyDown={event => {
                if (picker) return;
                if (event.key === 'Escape') { event.stopPropagation(); close(); }
                if (event.key === 'Tab') {
                    const items = [...event.currentTarget.querySelectorAll('button:not(:disabled),input,select')];
                    const first = items[0], last = items[items.length - 1];
                    if (event.shiftKey && (document.activeElement === first || document.activeElement === event.currentTarget)) { event.preventDefault(); last?.focus(); }
                    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
                }
            }}>
                <header><h2>Section background</h2><button type="button" aria-label="Close background settings" onClick={close}>×</button></header>
                <p>The overlay follows this section’s {surface} surface and your theme colors.</p>
                <label>Media type<select value={draft.type} onChange={event => patch({ type: event.target.value })}><option value="image">Image</option><option value="video">Video</option></select></label>
                <label>{draft.type === 'image' ? 'Image URL' : 'Video URL'}<input value={draft[draft.type]} onChange={event => patch({ [draft.type]: event.target.value })} placeholder={draft.type === 'image' ? 'https://… or /storage/…' : 'MP4, WebM, YouTube or Vimeo URL'} /></label>
                {!trialMode && <button type="button" onClick={() => setPicker(draft.type)}>Choose or upload {draft.type}</button>}
                {draft.type === 'video' && <><label>Poster image URL (optional)<input value={draft.poster} onChange={event => patch({ poster: event.target.value })} /></label>{!trialMode && <button type="button" onClick={() => setPicker('poster')}>Choose poster image</button>}<p>Video plays muted and loops behind the content.</p></>}
                <label>Overlay strength · {draft.opacity}%<input type="range" min="0" max="100" value={draft.opacity} onChange={event => patch({ opacity: event.target.value })} /></label>
                <label>Position<select value={draft.position} onChange={event => patch({ position: event.target.value })}>{['center center', 'center top', 'center bottom', 'left center', 'right center'].map(value => <option key={value} value={value}>{value}</option>)}</select></label>
                <label>Fit<select value={draft.size} onChange={event => patch({ size: event.target.value })}><option value="cover">Cover</option><option value="contain">Contain</option></select></label>
                {error && <p role="alert">{error}</p>}
                <footer><button type="button" onClick={() => { onUpdate({ universal_background_enabled: false }); close(); }}>Remove background</button><button type="button" onClick={apply}>Apply background</button></footer>
            </div>
        </div>, document.body)}
        {picker && <MediaPickerModal open websiteId={websiteId} kind={picker === 'video' ? 'video' : 'image'} onClose={() => setPicker(null)} onSelect={asset => { if (asset?.url) patch({ [picker]: asset.url }); setPicker(null); }} />}
    </>;
}
