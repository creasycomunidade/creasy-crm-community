import {
    Heading,
    Image as ImageIcon,
    Minus,
    MousePointerClick,
    StretchVertical,
    Type,
} from 'lucide-react';

// ─── Block model ──────────────────────────────────────────────────────────────
//
// A block is { id, type, ...props }. Types: heading | paragraph | button |
// image | divider | spacer. Text-bearing blocks (heading, paragraph, button)
// store *inline HTML* in `text` so the inline rich-text editor can keep bold,
// italic, links and {{tokens}} round-tripping through the same field that
// blocksToHtml() interpolates straight into the tag.

export function uid() {
    return Math.random().toString(36).slice(2, 9);
}

export const BLOCK_TYPES = [
    { type: 'heading', label: 'Heading', Icon: Heading },
    { type: 'paragraph', label: 'Text', Icon: Type },
    { type: 'button', label: 'Button', Icon: MousePointerClick },
    { type: 'image', label: 'Image', Icon: ImageIcon },
    { type: 'divider', label: 'Divider', Icon: Minus },
    { type: 'spacer', label: 'Spacer', Icon: StretchVertical },
];

export function defaultBlock(type) {
    switch (type) {
        case 'heading':
            return { id: uid(), type: 'heading', level: 2, text: '', align: 'left', color: '#111111' };
        case 'paragraph':
            return { id: uid(), type: 'paragraph', text: '', align: 'left' };
        case 'button':
            return { id: uid(), type: 'button', text: 'Click Here', url: '#', align: 'center', color: '#2563eb', textColor: '#ffffff' };
        case 'image':
            return { id: uid(), type: 'image', src: '', alt: '', align: 'center', width: '', url: '' };
        case 'divider':
            return { id: uid(), type: 'divider', color: '#e5e7eb' };
        case 'spacer':
            return { id: uid(), type: 'spacer', height: 24 };
        default:
            return { id: uid(), type: 'paragraph', text: '', align: 'left' };
    }
}

export function isTextBlock(type) {
    return type === 'heading' || type === 'paragraph' || type === 'button';
}

// ─── Blocks → HTML ──────────────────────────────────────────────────────────

export function blocksToHtml(blocks) {
    return blocks
        .map((b) => {
            switch (b.type) {
                case 'heading': {
                    const align = b.align && b.align !== 'left' ? `text-align:${b.align};` : '';
                    const color = b.color || '#111';
                    return `<h${b.level} style="margin:0 0 12px;font-family:sans-serif;color:${color};${align}">${b.text}</h${b.level}>`;
                }
                case 'paragraph': {
                    const align = b.align && b.align !== 'left' ? `text-align:${b.align};` : '';
                    return `<p style="margin:0 0 12px;font-family:sans-serif;font-size:15px;line-height:1.6;color:#333;${align}">${b.text}</p>`;
                }
                case 'button': {
                    const align = b.align ?? 'center';
                    const bg = b.color ?? '#2563eb';
                    const fg = b.textColor ?? '#ffffff';
                    return `<div style="text-align:${align};margin:16px 0;"><a href="${b.url || '#'}" style="display:inline-block;padding:12px 24px;background:${bg};color:${fg};text-decoration:none;border-radius:6px;font-family:sans-serif;font-size:15px;font-weight:600;">${b.text}</a></div>`;
                }
                case 'divider':
                    return `<hr style="border:none;border-top:1px solid ${b.color || '#e5e7eb'};margin:20px 0;" />`;
                case 'spacer': {
                    const h = b.height ?? 20;
                    return `<div style="height:${h}px;line-height:${h}px;font-size:1px;">&nbsp;</div>`;
                }
                case 'image': {
                    if (!b.src) return '';
                    const align = b.align ?? 'center';
                    const width = b.width ? `width:${b.width};` : '';
                    const img = `<img src="${b.src}" alt="${b.alt ?? ''}" style="${width}max-width:100%;border-radius:4px;display:inline-block;" />`;
                    const inner = b.url ? `<a href="${b.url}" style="text-decoration:none;">${img}</a>` : img;
                    return `<div style="text-align:${align};margin:12px 0;">${inner}</div>`;
                }
                default:
                    return '';
            }
        })
        .join('\n');
}

// ─── HTML → Blocks ────────────────────────────────────────────────────────────
//
// A real DOM walk rather than line/regex matching, so it copes with whatever the
// AI generator, a paste, or the HTML tab throws at it: pretty-printed or
// single-line markup, <ul>/<li> lists, <h4>–<h6>, <strong>/<em> runs with no
// wrapping <p>, and buttons/images nested inside centring <div>s. Each top-level
// node maps to the closest block; unknown containers are flattened into their
// inner blocks so nothing is lost.

const INLINE_TAGS = new Set([
    'span', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'del', 'ins',
    'small', 'mark', 'code', 'sub', 'sup', 'font', 'label', 'time', 'abbr',
    'q', 'cite', 'big', 'tt', 'var', 'kbd', 'samp', 'wbr',
]);

function styleProp(style, prop) {
    const m = (style || '').match(new RegExp(`(?:^|[;\\s])${prop}\\s*:\\s*([^;]+)`, 'i'));
    return m ? m[1].trim() : '';
}

function colorFromStyle(style, prop) {
    const v = styleProp(style, prop);
    if (!v) return '';
    const c = v.match(/#[0-9a-f]{3,8}|rgba?\([^)]*\)|hsla?\([^)]*\)/i);
    if (c) return c[0];
    return /^[a-z]+$/i.test(v) ? v : ''; // bare keyword like "white"
}

function styleAlign(style) {
    const a = styleProp(style, 'text-align').toLowerCase();
    return a === 'center' || a === 'right' ? a : 'left';
}

function isButtonLink(a) {
    const s = (a.getAttribute('style') || '').toLowerCase();
    return /background(-color)?\s*:/.test(s) || /padding\s*:/.test(s) || /display\s*:\s*(inline-)?block/.test(s);
}

function escapeText(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function buttonBlockFrom(a, align) {
    const s = a.getAttribute('style') || '';
    return {
        id: uid(),
        type: 'button',
        text: a.innerHTML.trim() || 'Button',
        url: a.getAttribute('href') || '#',
        align: align || 'center',
        color: colorFromStyle(s, 'background-color') || colorFromStyle(s, 'background') || '#2563eb',
        textColor: colorFromStyle(s, 'color') || '#ffffff',
    };
}

function imageBlockFrom(img, href, align) {
    const s = img.getAttribute('style') || '';
    const w = styleProp(s, 'width');
    return {
        id: uid(),
        type: 'image',
        src: img.getAttribute('src') || '',
        alt: img.getAttribute('alt') || '',
        align: align || styleAlign(s),
        width: w || (img.getAttribute('width') ? `${img.getAttribute('width')}px` : ''),
        url: href || '',
    };
}

function walkNodes(parent, blocks) {
    let buf = [];
    const flush = () => {
        if (!buf.length) return;
        const html = buf.map((n) => (n.nodeType === 3 ? escapeText(n.textContent) : n.outerHTML)).join('').trim();
        buf = [];
        if (html) blocks.push({ id: uid(), type: 'paragraph', text: html, align: 'left' });
    };

    parent.childNodes.forEach((node) => {
        if (node.nodeType === 3) {
            if (node.textContent.trim()) buf.push(node);
            return;
        }
        if (node.nodeType !== 1) return; // comments, etc.
        const tag = node.tagName.toLowerCase();
        const style = node.getAttribute('style') || '';

        if (/^h[1-6]$/.test(tag)) {
            flush();
            blocks.push({ id: uid(), type: 'heading', level: Math.min(3, parseInt(tag[1], 10)), text: node.innerHTML.trim(), align: styleAlign(style), color: colorFromStyle(style, 'color') || '#111111' });
        } else if (tag === 'p') {
            flush();
            const t = node.innerHTML.trim();
            if (t) blocks.push({ id: uid(), type: 'paragraph', text: t, align: styleAlign(style) });
        } else if (tag === 'hr') {
            flush();
            blocks.push({ id: uid(), type: 'divider', color: colorFromStyle(style, 'border-top') || colorFromStyle(style, 'border') || '#e5e7eb' });
        } else if (tag === 'ul' || tag === 'ol') {
            flush();
            node.querySelectorAll(':scope > li').forEach((li) => {
                const t = li.innerHTML.trim();
                if (t) blocks.push({ id: uid(), type: 'paragraph', text: `• ${t}`, align: 'left' });
            });
        } else if (tag === 'img') {
            flush();
            blocks.push(imageBlockFrom(node, '', styleAlign(style)));
        } else if (tag === 'br') {
            flush();
        } else if (tag === 'a') {
            const img = node.querySelector('img');
            if (img) {
                flush();
                blocks.push(imageBlockFrom(img, node.getAttribute('href'), 'center'));
            } else if (isButtonLink(node)) {
                flush();
                blocks.push(buttonBlockFrom(node, 'left'));
            } else {
                buf.push(node); // inline link inside running text
            }
        } else if (INLINE_TAGS.has(tag)) {
            buf.push(node);
        } else {
            // Block-level container (div, section, table cells, center, blockquote…).
            flush();
            const heightStyle = styleProp(style, 'height');
            const hasChild = node.querySelector('img,a,h1,h2,h3,h4,h5,h6,p,ul,ol,hr');
            if (heightStyle && !node.textContent.trim() && !hasChild) {
                blocks.push({ id: uid(), type: 'spacer', height: parseInt(heightStyle, 10) || 20 });
                return;
            }
            const imgs = node.querySelectorAll('img');
            const buttonA = [...node.querySelectorAll('a')].find((a) => isButtonLink(a));
            const visibleText = node.textContent.replace(/\s+/g, '');
            if (imgs.length === 1 && !visibleText) {
                blocks.push(imageBlockFrom(imgs[0], node.querySelector('a')?.getAttribute('href'), styleAlign(style)));
            } else if (buttonA && node.textContent.trim() === buttonA.textContent.trim()) {
                blocks.push(buttonBlockFrom(buttonA, styleAlign(style)));
            } else {
                walkNodes(node, blocks); // flatten generic containers into their inner blocks
            }
        }
    });
    flush();
}

export function htmlToBlocks(html) {
    if (!html || !html.trim()) return [];
    if (typeof DOMParser === 'undefined') {
        // No DOM available (e.g. SSR) — keep the markup in a single editable block.
        return [{ id: uid(), type: 'paragraph', text: html, align: 'left' }];
    }
    const doc = new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html');
    const blocks = [];
    walkNodes(doc.body, blocks);
    if (blocks.length === 0) {
        blocks.push({ id: uid(), type: 'paragraph', text: html, align: 'left' });
    }
    return blocks;
}
