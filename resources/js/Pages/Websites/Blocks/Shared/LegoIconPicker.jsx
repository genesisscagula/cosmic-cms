import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ICON_PATHS } from './legoIconLibrary';

const keywords = {
    'shield-check': 'security protection trust safe', settings: 'sun brightness configuration',
    truck: 'delivery shipping transport', headset: 'support help customer service',
    'check-circle': 'success complete approved', star: 'rating favorite review',
    building: 'office business company', home: 'house property', ruler: 'measure design size',
    layers: 'stack strategy services', sparkles: 'magic ai quality featured',
    phone: 'call contact telephone', mail: 'email message contact',
    'map-pin': 'location address place', clock: 'time hours schedule', users: 'team people community',
};
const title = name => name.replaceAll('-', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
function Icon({ name }) {
    return ICON_PATHS[name] ? <svg aria-hidden="true" viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d={ICON_PATHS[name]} /></svg> : null;
}

function Library({ value, onChange, onClose }) {
    const dialog = useRef(null);
    const [query, setQuery] = useState('');
    const terms = query.toLowerCase().trim().split(/\s+/).filter(Boolean);
    const names = Object.keys(ICON_PATHS).filter(name => terms.every(term => `${name} ${keywords[name] || ''}`.includes(term)));
    useEffect(() => {
        const previous = document.activeElement;
        dialog.current.showModal();
        return () => { dialog.current?.close(); previous?.focus?.(); };
    }, []);
    return createPortal(<dialog ref={dialog} className="cosmic-icon-library" aria-labelledby="cosmic-icon-library-title" onCancel={event => { event.preventDefault(); onClose(); }} onClick={event => { if (event.target === event.currentTarget) { const rect = event.currentTarget.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) onClose(); } }}>
        <header><div><h2 id="cosmic-icon-library-title">Choose an icon</h2><p>Simple outline icons for your website.</p></div><button type="button" aria-label="Close icon library" onClick={onClose}>×</button></header>
        <label className="cosmic-icon-library__search"><span>Search icons</span><input autoFocus type="search" value={query} onChange={event => setQuery(event.target.value)} placeholder="Search icons, e.g. support, team, email…" /></label>
        <p className="cosmic-icon-library__count">{names.length} icons</p>
        <div className="cosmic-icon-library__grid">{names.map(name => <button type="button" key={name} aria-pressed={value === name} onClick={() => { onChange(name); onClose(); }}><Icon name={name} /><span>{title(name)}</span></button>)}</div>
        {!names.length ? <div className="cosmic-icon-library__empty"><p>No icons found. Try another keyword.</p><button type="button" onClick={() => setQuery('')}>Clear search</button></div> : null}
        <footer><button type="button" onClick={() => { onChange(''); onClose(); }}>Remove icon</button><button type="button" onClick={onClose}>Cancel</button></footer>
    </dialog>, document.body);
}

export default function LegoIconPicker({ value, onChange }) {
    const [open, setOpen] = useState(false);
    return <div className="cosmic-icon-picker"><span>Icon</span><button type="button" className="cosmic-icon-picker__trigger" aria-haspopup="dialog" onClick={() => setOpen(true)}><Icon name={value} /><span>{value ? title(value) : 'Choose an icon'}</span><span aria-hidden="true">▦</span></button>{open ? <Library value={value} onChange={onChange} onClose={() => setOpen(false)} /> : null}</div>;
}
