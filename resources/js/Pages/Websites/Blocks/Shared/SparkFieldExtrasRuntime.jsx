import React, {
    createContext,
    Fragment,
    useContext,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { createPortal } from 'react-dom';
import {
    getSparkFieldExtraAnchors,
    normalizeSparkExtraTargetPath,
} from './sparkExtrasContract';
import {
    canInsertLegoTypeIntoHybridSpark,
    getHybridSparkInsertionAnchors,
    hybridSparkExtraToLegoNode,
} from './hybridSparkInsertionContract';

const SparkFieldExtrasContext = createContext(null);

const normalizeVisibleText = (value) => String(value ?? '').replace(/\s+/g, ' ').trim();
const normalizeComparableUrl = (value) => String(value ?? '').trim();

const IMAGE_KEY_RE = /(image|photo|picture|avatar|logo|poster|thumbnail|media|src|cover)/i;
const BUTTON_KEY_RE = /(button|cta|action|link|label)/i;
const TEXT_FIELD_TYPES = new Set(['text', 'textarea', 'heading', 'label', 'url', 'email', 'tel']);

const descriptorMatches = (descriptor, request) => {
    const mode = String(request?.mode || 'text').toLowerCase();
    const expected = descriptor?.value;
    const key = String(descriptor?.key || '');
    const fieldType = String(descriptor?.fieldType || '').toLowerCase();

    if (mode === 'image') {
        const actual = normalizeComparableUrl(request?.value);
        return Boolean(actual)
            && normalizeComparableUrl(expected) === actual
            && (fieldType === 'image' || IMAGE_KEY_RE.test(key) || fieldType === 'text' || !fieldType);
    }

    if (mode === 'button') {
        const actual = normalizeVisibleText(request?.value);
        return Boolean(actual)
            && normalizeVisibleText(expected) === actual
            && (BUTTON_KEY_RE.test(key) || TEXT_FIELD_TYPES.has(fieldType) || !fieldType);
    }

    const actual = normalizeVisibleText(request?.value);
    if (!actual || normalizeVisibleText(expected) !== actual) return false;
    return TEXT_FIELD_TYPES.has(fieldType) || !fieldType || !['boolean', 'number', 'repeater', 'group'].includes(fieldType);
};

const scoreDescriptor = (descriptor, request) => {
    let score = 0;
    const mode = String(request?.mode || 'text').toLowerCase();
    const key = String(descriptor?.key || '');
    const fieldType = String(descriptor?.fieldType || '').toLowerCase();
    const kind = String(request?.kind || '').toLowerCase();

    if (mode === 'image') {
        if (fieldType === 'image') score += 50;
        if (IMAGE_KEY_RE.test(key)) score += 30;
    } else if (mode === 'button') {
        if (BUTTON_KEY_RE.test(key)) score += 35;
        if (/label/i.test(key)) score += 10;
    } else {
        if (kind === 'heading' && /(heading|title|headline)/i.test(key)) score += 30;
        if (kind === 'label' && /(tagline|eyebrow|label|badge|kicker)/i.test(key)) score += 25;
        if (kind === 'text' && /(text|description|desc|body|copy|content)/i.test(key)) score += 20;
        if (fieldType === 'textarea' && kind === 'text') score += 10;
        if (fieldType === 'text') score += 5;
    }

    return score;
};

const extraSpacingClass = (placement) => placement === 'before' ? 'mb-3' : 'mt-3';

const extraPortableStyle = (extra) => {
    const x = extra?.style && typeof extra.style === 'object' ? extra.style : {};
    const px = (value) => value === '' || value === null || value === undefined ? undefined : `${Number(value) || 0}px`;
    const pct = (value) => value === '' || value === null || value === undefined ? undefined : `${Number(value) || 0}%`;
    const shadow = { none: 'none', sm: '0 1px 2px rgba(15,23,42,.08)', md: '0 8px 20px rgba(15,23,42,.10)', lg: '0 14px 34px rgba(15,23,42,.12)', xl: '0 24px 56px rgba(15,23,42,.16)' };
    return {
        color: x.color || undefined,
        background: x.background || undefined,
        gap: px(x.gap), maxWidth: px(x.max_width), minHeight: px(x.min_height), width: pct(x.width),
        padding: px(x.padding), paddingLeft: px(x.padding_x), paddingRight: px(x.padding_x), paddingTop: px(x.padding_y), paddingBottom: px(x.padding_y),
        borderRadius: px(x.radius), border: x.border_width ? `${Number(x.border_width) || 1}px solid ${x.border_color || 'rgba(15,23,42,.12)'}` : undefined,
        boxShadow: shadow[x.shadow] || undefined, textAlign: x.text_align || undefined, fontSize: px(x.font_size), fontWeight: x.font_weight || undefined,
        lineHeight: x.line_height || undefined, opacity: x.opacity != null ? Math.max(0, Math.min(1, Number(x.opacity) || 0)) : undefined,
        alignSelf: x.self_align === 'start' ? 'flex-start' : x.self_align === 'end' ? 'flex-end' : x.self_align || undefined,
    };
};

const spacerClass = (size) => ({
    xs: 'h-2',
    sm: 'h-4',
    md: 'h-6',
    lg: 'h-10',
    xl: 'h-14',
    '2xl': 'h-20',
}[String(size || '').toLowerCase()] || 'h-6');

function SparkFieldExtraItem({ extra, targetPath = null, placement = null }) {
    const context = useContext(SparkFieldExtrasContext);
    const type = String(extra?.type || '').toLowerCase();
    const data = extra?.data || {};
    const portableNode = hybridSparkExtraToLegoNode(extra);
    const portableStyle = extraPortableStyle(extra);
    const common = {
        'data-cosmic-field-extra': '1',
        'data-cosmic-extra-id': extra?.id || undefined,
        'data-cosmic-extra-type': type || undefined,
        draggable: Boolean(context?.hybridBuilder && portableNode),
        onDragStart: context?.hybridBuilder && portableNode ? (event) => {
            event.stopPropagation();
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/x-cosmic-lego', JSON.stringify({
                kind: 'hybrid-extra',
                type,
                node: portableNode,
                sourceBlockIndex: context.blockIndex,
                sourceTargetPath: targetPath,
                sourcePlacement: placement,
                sourceExtraId: extra?.id || null,
            }));
        } : undefined,
    };

    if (type === 'image') {
        return <span {...common} className="cosmic-field-extra cosmic-field-extra--image block w-full overflow-hidden rounded-2xl" style={{ ...portableStyle, borderRadius: 'var(--cosmic-local-image-radius,var(--cosmic-image-radius,16px))' }}>
            {data.src ? <img
                src={data.src}
                alt={data.alt || ''}
                title={data.title || undefined}
                loading={data.loading || 'lazy'}
                className="block max-h-[34rem] w-full"
                style={{ objectFit: data.object_fit || 'cover' }}
            /> : <span
                data-cosmic-extra-empty-media="image"
                className="grid min-h-28 w-full place-items-center bg-black/5 px-4 py-8 text-center text-xs font-semibold uppercase tracking-[.12em] text-current/50"
            >Image</span>}
        </span>;
    }

    if (type === 'button') {
        return <span {...common} className="cosmic-field-extra cosmic-field-extra--button inline-flex max-w-full">
            <a
                href={data.url || '#'}
                target={data.target || '_self'}
                rel={data.rel || (data.target === '_blank' ? 'noopener noreferrer' : undefined)}
                className="inline-flex min-h-10 items-center justify-center rounded-full px-5 py-2.5 text-sm font-semibold no-underline"
                style={{ ...portableStyle,
                    borderRadius: 'var(--cosmic-local-button-radius,var(--cosmic-button-radius,999px))',
                    backgroundColor: 'var(--cosmic-button-primary-bg,var(--cosmic-brand-primary,#0f172a))',
                    color: 'var(--cosmic-button-primary-text,#fff)',
                }}
            >{data.label || 'Learn More'}</a>
        </span>;
    }

    if (type === 'heading') {
        return <span
            {...common}
            role="heading"
            aria-level={Math.max(1, Math.min(6, Number(data.level) || 2))}
            className="cosmic-field-extra cosmic-field-extra--heading block text-2xl font-semibold"
            style={{ ...portableStyle,
                fontSize: 'var(--cosmic-local-h3-size,var(--cosmic-h3-size,1.75rem))',
                lineHeight: 'var(--cosmic-local-h3-line,var(--cosmic-h3-line,1.15))',
                color: 'var(--cosmic-color-heading,currentColor)',
            }}
        >{data.text}</span>;
    }

    if (type === 'text') {
        return <span {...common} className="cosmic-field-extra cosmic-field-extra--text block text-base" style={{ ...portableStyle, fontSize: 'var(--cosmic-local-body-size,var(--cosmic-body-size,1rem))', lineHeight: 'var(--cosmic-local-body-line,var(--cosmic-body-line,1.6))', color: 'var(--cosmic-color-body,currentColor)' }}>{data.text}</span>;
    }

    if (type === 'badge') {
        return <span {...common} className="cosmic-field-extra cosmic-field-extra--badge inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[.12em]" style={{ ...portableStyle, borderColor: 'var(--cosmic-color-border,currentColor)', color: 'var(--cosmic-color-heading,currentColor)' }}>{data.text}</span>;
    }

    if (type === 'icon') {
        return <span {...common} className="cosmic-field-extra cosmic-field-extra--icon inline-flex items-center gap-2" style={portableStyle} aria-label={data.label || undefined}>
            <span aria-hidden="true" className="text-xl leading-none">{data.name || '✦'}</span>
            {data.label ? <span className="text-sm">{data.label}</span> : null}
        </span>;
    }

    if (type === 'video') {
        return <span {...common} className="cosmic-field-extra cosmic-field-extra--video block w-full overflow-hidden rounded-2xl" style={{ ...portableStyle, borderRadius: 'var(--cosmic-local-image-radius,var(--cosmic-image-radius,16px))' }}>
            {data.src ? <video
                src={data.src}
                poster={data.poster || undefined}
                autoPlay={Boolean(data.autoplay)}
                muted={Boolean(data.muted)}
                loop={Boolean(data.loop)}
                controls={Boolean(data.controls)}
                playsInline={data.plays_inline !== false}
                className="block max-h-[34rem] w-full object-cover"
            /> : <span
                data-cosmic-extra-empty-media="video"
                className="grid min-h-28 w-full place-items-center bg-black/5 px-4 py-8 text-center text-xs font-semibold uppercase tracking-[.12em] text-current/50"
            >Video</span>}
        </span>;
    }

    if (type === 'divider') {
        const vertical = data.orientation === 'vertical';
        return <span {...common} aria-hidden="true" className={vertical
            ? 'cosmic-field-extra cosmic-field-extra--divider inline-block h-10 w-px border-l'
            : 'cosmic-field-extra cosmic-field-extra--divider block h-px w-full border-t'
        } style={{ ...portableStyle, borderColor: 'var(--cosmic-color-border,currentColor)' }} />;
    }

    if (type === 'spacer') {
        return <span {...common} aria-hidden="true" className={`cosmic-field-extra cosmic-field-extra--spacer block w-full ${spacerClass(data.size)}`} style={portableStyle} />;
    }

    if (type === 'list') {
        return <ul {...common} className="cosmic-field-extra cosmic-field-extra--list block list-disc space-y-1 pl-6" style={portableStyle}>{(Array.isArray(data.items) ? data.items : []).map((item, index) => <li key={index}>{item}</li>)}</ul>;
    }

    if (type === 'quote') {
        return <blockquote {...common} className="cosmic-field-extra cosmic-field-extra--quote block border-l-2 pl-4" style={{ ...portableStyle, borderColor: 'var(--cosmic-brand-primary,currentColor)', color: 'var(--cosmic-color-body,currentColor)' }}><span>{data.text}</span>{data.cite ? <cite className="mt-2 block text-sm font-semibold not-italic">{data.cite}</cite> : null}</blockquote>;
    }

    if (type === 'stat') {
        return <span {...common} className="cosmic-field-extra cosmic-field-extra--stat block" style={portableStyle}><strong className="block text-3xl" style={{ color: 'var(--cosmic-color-heading,currentColor)' }}>{data.value}</strong>{data.label ? <span className="text-sm" style={{ color: 'var(--cosmic-color-body,currentColor)' }}>{data.label}</span> : null}</span>;
    }

    return null;
}

function HybridSparkDropZone({ targetPath, placement }) {
    const context = useContext(SparkFieldExtrasContext);
    if (!context?.hybridBuilder || !targetPath) return null;

    const handleDrop = (event) => {
        event.preventDefault();
        event.stopPropagation();
        let payload = null;
        try { payload = JSON.parse(event.dataTransfer?.getData('application/x-cosmic-lego') || 'null'); } catch (_) {}
        const type = String(payload?.type || event.dataTransfer?.getData('text/plain') || '').trim();
        if (!canInsertLegoTypeIntoHybridSpark(type)) {
            context.onUnsupportedHybridType?.(type);
            return;
        }
        context.onInsertHybridExtra?.({ targetPath, placement, type, payload });
    };

    return <button
        type="button"
        className="cosmic-hybrid-spark-drop-zone"
        data-cosmic-hybrid-drop-zone="1"
        data-cosmic-hybrid-target={targetPath}
        data-cosmic-hybrid-placement={placement}
        onDragOver={(event) => {
            const hasLego = Array.from(event.dataTransfer?.types || []).includes('application/x-cosmic-lego');
            if (!hasLego) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
        }}
        onDrop={handleDrop}
        onClick={(event) => {
            event.preventDefault();
            event.stopPropagation();
            context.onSelectHybridSlot?.({ targetPath, placement });
        }}
        title="Drop a Cosmic element here"
        aria-label={`Add element ${placement} ${targetPath}`}
    ><span aria-hidden="true">＋</span><small>Drop element</small></button>;
}

function SparkFieldExtraPlacement({ targetPath, placement, items }) {
    return <Fragment>
        <HybridSparkDropZone targetPath={targetPath} placement={placement} />
        <SparkFieldExtraList targetPath={targetPath} placement={placement} items={items} />
    </Fragment>;
}

export function SparkFieldExtraList({ targetPath, placement, items }) {
    if (!Array.isArray(items) || !items.length) return null;
    return <span
        data-cosmic-field-extra-list="1"
        data-cosmic-field-extra-for={targetPath || undefined}
        data-cosmic-field-extra-placement={placement}
        className={`cosmic-field-extra-list cosmic-field-extra-list--${placement} block w-full ${extraSpacingClass(placement)}`}
    >
        {items.map((extra) => <SparkFieldExtraItem key={extra.id} extra={extra} targetPath={targetPath} placement={placement} />)}
    </span>;
}

export function SparkFieldExtraSlots({ anchor, children }) {
    if (!anchor?.target) return children;
    return <Fragment>
        <SparkFieldExtraPlacement targetPath={anchor.target} placement="before" items={anchor.slots?.before} />
        {children}
        <SparkFieldExtraPlacement targetPath={anchor.target} placement="after" items={anchor.slots?.after} />
    </Fragment>;
}

export function useSparkFieldExtrasAnchor({ value, mode = 'text', kind = '', fieldPath = null } = {}) {
    const context = useContext(SparkFieldExtrasContext);
    const retainedTarget = useRef(null);
    if (!context?.resolveAnchor) return null;

    const resolved = context.resolveAnchor({
        value,
        mode,
        kind,
        fieldPath: fieldPath || retainedTarget.current,
    });
    retainedTarget.current = resolved?.target || null;
    return resolved;
}

const descriptorDomMode = (descriptor) => {
    const key = String(descriptor?.key || '');
    const fieldType = String(descriptor?.fieldType || '').toLowerCase();
    if (fieldType === 'image' || IMAGE_KEY_RE.test(key)) return 'image';
    if (BUTTON_KEY_RE.test(key)) return 'button';
    return 'text';
};

const candidateTextNodes = (root, mode) => {
    if (mode === 'button') {
        return Array.from(root.querySelectorAll('[data-luna-target="button"],a,button,[role="button"]'));
    }
    return Array.from(root.querySelectorAll('[data-cosmic-luna-display="text"],[data-luna-target="heading"],[data-luna-target="label"],[data-luna-target="text"],h1,h2,h3,h4,h5,h6,p,li,dt,dd,figcaption,blockquote,span'));
};

const findFallbackDomAnchor = (root, descriptor, usedNodes) => {
    const mode = descriptorDomMode(descriptor);
    const expected = mode === 'image'
        ? normalizeComparableUrl(descriptor.value)
        : normalizeVisibleText(descriptor.value);
    if (!expected) return null;

    if (mode === 'image') {
        const images = Array.from(root.querySelectorAll('img[src]'));
        const image = images.find((node) => !usedNodes.has(node) && normalizeComparableUrl(node.getAttribute('src')) === expected);
        if (!image) return null;
        const explicit = image.closest('[data-luna-target="image"],[data-cosmic-luna-display="image"]');
        const anchor = explicit && root.contains(explicit) ? explicit : image;
        usedNodes.add(image);
        usedNodes.add(anchor);
        return anchor;
    }

    const matches = candidateTextNodes(root, mode)
        .filter((node) => !usedNodes.has(node))
        .filter((node) => !node.closest('[data-cosmic-field-extra="1"],[data-cosmic-field-extra-list="1"]'))
        .filter((node) => normalizeVisibleText(node.textContent) === expected)
        .sort((a, b) => {
            const aExplicit = a.hasAttribute('data-luna-target') || a.hasAttribute('data-cosmic-luna-display') ? 1 : 0;
            const bExplicit = b.hasAttribute('data-luna-target') || b.hasAttribute('data-cosmic-luna-display') ? 1 : 0;
            if (aExplicit !== bExplicit) return bExplicit - aExplicit;
            const aChildren = a.children.length;
            const bChildren = b.children.length;
            if (aChildren !== bChildren) return aChildren - bChildren;
            return 0;
        });

    const anchor = matches[0] || null;
    if (anchor) usedNodes.add(anchor);
    return anchor;
};

const appendPortalMarker = (anchorNode, descriptor, placement) => {
    const parent = anchorNode?.parentNode;
    if (!parent) return null;

    const marker = document.createElement('span');
    marker.setAttribute('data-cosmic-field-extra-portal', '1');
    marker.setAttribute('data-cosmic-field-extra-for', descriptor.target);
    marker.setAttribute('data-cosmic-field-extra-placement', placement);
    marker.style.display = 'contents';

    if (placement === 'before') parent.insertBefore(marker, anchorNode);
    else parent.insertBefore(marker, anchorNode.nextSibling);
    return marker;
};

export function SparkFieldExtrasProvider({ block, schema, children, builderMode = false, blockIndex = null, onInsertHybridExtra = null, onSelectHybridSlot = null, onUnsupportedHybridType = null }) {
    const descriptors = useMemo(() => {
        if (!builderMode) return getSparkFieldExtraAnchors(block, schema);
        const hybrid = getHybridSparkInsertionAnchors(block, schema);
        if (!hybrid.length) return getSparkFieldExtraAnchors(block, schema);
        const byTarget = new Map(hybrid.map((item) => [item.target, item]));
        getSparkFieldExtraAnchors(block, schema).forEach((item) => byTarget.set(item.target, { ...byTarget.get(item.target), ...item }));
        return Array.from(byTarget.values());
    }, [block, schema, builderMode]);
    const claimedThisRenderRef = useRef(new Set());
    claimedThisRenderRef.current = new Set();
    const hostRef = useRef(null);
    const [fallbackAnchors, setFallbackAnchors] = useState([]);

    const contextValue = useMemo(() => ({
        hybridBuilder: Boolean(builderMode && typeof onInsertHybridExtra === 'function'),
        blockIndex,
        onInsertHybridExtra,
        onSelectHybridSlot,
        onUnsupportedHybridType,
        resolveAnchor(request) {
            const explicit = normalizeSparkExtraTargetPath(request?.fieldPath);
            if (explicit) {
                const descriptor = descriptors.find((item) => item.target === explicit);
                if (descriptor && descriptorMatches(descriptor, request)) {
                    claimedThisRenderRef.current.add(descriptor.target);
                    return descriptor;
                }
            }

            const matches = descriptors
                .filter((descriptor) => !claimedThisRenderRef.current.has(descriptor.target))
                .filter((descriptor) => descriptorMatches(descriptor, request))
                .sort((a, b) => scoreDescriptor(b, request) - scoreDescriptor(a, request));
            const descriptor = matches[0] || null;
            if (descriptor) claimedThisRenderRef.current.add(descriptor.target);
            return descriptor;
        },
    // Claims reset when the provider rerenders. Component refs retain their
    // resolved target between local child rerenders.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }), [descriptors, builderMode, blockIndex, onInsertHybridExtra, onSelectHybridSlot, onUnsupportedHybridType]);

    const descriptorSignature = useMemo(() => descriptors.map((descriptor) => [
        descriptor.target,
        descriptor.slots?.before?.map((extra) => extra.id).join(','),
        descriptor.slots?.after?.map((extra) => extra.id).join(','),
        String(descriptor.value ?? ''),
    ].join('|')).join('||'), [descriptors]);

    useLayoutEffect(() => {
        const root = hostRef.current;
        if (!root || !descriptors.length) {
            setFallbackAnchors([]);
            return undefined;
        }

        const markers = [];
        const portals = [];
        const annotatedNodes = [];
        const usedNodes = new Set();
        const explicitPaths = new Set(
            Array.from(root.querySelectorAll('[data-cosmic-field-path]'))
                .map((node) => node.getAttribute('data-cosmic-field-path'))
                .filter(Boolean),
        );

        descriptors.forEach((descriptor) => {
            if (explicitPaths.has(descriptor.target)) return;
            const anchorNode = findFallbackDomAnchor(root, descriptor, usedNodes);
            if (!anchorNode) return;
            anchorNode.setAttribute('data-cosmic-field-path', descriptor.target);
            anchorNode.setAttribute('data-cosmic-field-anchor-mode', 'fallback');
            annotatedNodes.push({ node: anchorNode, target: descriptor.target });

            if (builderMode || descriptor.slots?.before?.length) {
                const marker = appendPortalMarker(anchorNode, descriptor, 'before');
                if (marker) {
                    markers.push(marker);
                    portals.push({ marker, descriptor, placement: 'before' });
                }
            }
            if (builderMode || descriptor.slots?.after?.length) {
                const marker = appendPortalMarker(anchorNode, descriptor, 'after');
                if (marker) {
                    markers.push(marker);
                    portals.push({ marker, descriptor, placement: 'after' });
                }
            }
        });

        setFallbackAnchors(portals);
        return () => {
            markers.forEach((marker) => marker.remove());
            annotatedNodes.forEach(({ node, target }) => {
                if (node?.getAttribute?.('data-cosmic-field-anchor-mode') !== 'fallback') return;
                if (node?.getAttribute?.('data-cosmic-field-path') !== target) return;
                node.removeAttribute('data-cosmic-field-path');
                node.removeAttribute('data-cosmic-field-anchor-mode');
            });
        };
    // descriptorSignature captures target/value/extra-id changes without
    // serializing the whole Spark block on every Builder render.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [descriptorSignature]);

    if (!descriptors.length) return children;

    return <SparkFieldExtrasContext.Provider value={contextValue}>
        <div ref={hostRef} data-cosmic-field-extras-host="1" style={{ display: 'contents' }}>
            {children}
            {fallbackAnchors.map(({ marker, descriptor, placement }) => createPortal(
                <SparkFieldExtraPlacement
                    targetPath={descriptor.target}
                    placement={placement}
                    items={descriptor.slots?.[placement]}
                />,
                marker,
                `${descriptor.target}:${placement}`,
            ))}
        </div>
    </SparkFieldExtrasContext.Provider>;
}
