import React from 'react';

/**
 * MarkdownLite — a tiny, dependency-free Markdown renderer for chat/LLM output.
 *
 * It covers the subset of Markdown that chat models actually emit: headings,
 * ordered/unordered lists, bold, italic, inline code, links and paragraphs.
 *
 * It is XSS-safe by construction: text is placed into React text nodes (which
 * React escapes) and we never use dangerouslySetInnerHTML. Link hrefs are
 * restricted to http(s)/mailto.
 */

const SAFE_HREF = /^(https?:|mailto:)/i;

// Parse inline tokens (**bold**, *italic*, `code`, [text](url)) into React nodes.
function parseInline(text, keyPrefix) {
    const nodes = [];
    let remaining = String(text);
    let key = 0;

    const patterns = [
        { type: 'bold', re: /\*\*([^*]+)\*\*/ },
        { type: 'bold', re: /__([^_]+)__/ },
        { type: 'code', re: /`([^`]+)`/ },
        { type: 'link', re: /\[([^\]]+)\]\(([^)\s]+)\)/ },
        { type: 'italic', re: /\*([^*\n]+)\*/ },
    ];

    let guard = 0;
    while (remaining.length > 0 && guard++ < 500) {
        let earliest = null;
        for (const p of patterns) {
            const m = p.re.exec(remaining);
            if (m && (earliest === null || m.index < earliest.m.index)) {
                earliest = { p, m };
            }
        }

        if (!earliest) {
            nodes.push(remaining);
            break;
        }

        const { p, m } = earliest;
        if (m.index > 0) nodes.push(remaining.slice(0, m.index));
        const k = `${keyPrefix}-${key++}`;

        if (p.type === 'bold') {
            nodes.push(<strong key={k} className="font-semibold">{m[1]}</strong>);
        } else if (p.type === 'code') {
            nodes.push(
                <code key={k} className="rounded bg-neutral-200/70 dark:bg-neutral-800 px-1 py-0.5 font-mono text-[0.85em]">
                    {m[1]}
                </code>
            );
        } else if (p.type === 'link') {
            const href = SAFE_HREF.test(m[2]) ? m[2] : undefined;
            nodes.push(
                href
                    ? <a key={k} href={href} target="_blank" rel="noopener noreferrer" className="text-brand-600 dark:text-brand-400 underline underline-offset-2 break-words">{m[1]}</a>
                    : <span key={k}>{m[1]}</span>
            );
        } else {
            nodes.push(<em key={k}>{m[1]}</em>);
        }

        remaining = remaining.slice(m.index + m[0].length);
    }

    return nodes;
}

const isHeading = (l) => /^#{1,6}\s+/.test(l.trim());
const isOl = (l) => /^\s*\d+[.)]\s+/.test(l);
const isUl = (l) => /^\s*[-*+]\s+/.test(l);

export default function MarkdownLite({ content, className = '' }) {
    if (content === null || content === undefined || content === '') return null;

    const lines = String(content).replace(/\r\n/g, '\n').split('\n');
    const blocks = [];
    let i = 0;

    while (i < lines.length) {
        const trimmed = lines[i].trim();

        if (trimmed === '') { i++; continue; }

        const h = /^(#{1,6})\s+(.*)$/.exec(trimmed);
        if (h) { blocks.push({ type: 'h', level: h[1].length, text: h[2] }); i++; continue; }

        if (isOl(lines[i])) {
            const items = [];
            while (i < lines.length && isOl(lines[i])) {
                items.push(lines[i].replace(/^\s*\d+[.)]\s+/, ''));
                i++;
            }
            blocks.push({ type: 'ol', items });
            continue;
        }

        if (isUl(lines[i])) {
            const items = [];
            while (i < lines.length && isUl(lines[i])) {
                items.push(lines[i].replace(/^\s*[-*+]\s+/, ''));
                i++;
            }
            blocks.push({ type: 'ul', items });
            continue;
        }

        const paraLines = [];
        while (
            i < lines.length &&
            lines[i].trim() !== '' &&
            !isHeading(lines[i]) &&
            !isOl(lines[i]) &&
            !isUl(lines[i])
        ) {
            paraLines.push(lines[i].trim());
            i++;
        }
        blocks.push({ type: 'p', text: paraLines.join('\n') });
    }

    return (
        <div className={`space-y-2 ${className}`}>
            {blocks.map((b, bi) => {
                if (b.type === 'h') {
                    return (
                        <p key={bi} className={`font-semibold text-neutral-900 dark:text-neutral-100 ${b.level <= 2 ? 'text-[1.05em]' : ''}`}>
                            {parseInline(b.text, `h${bi}`)}
                        </p>
                    );
                }
                if (b.type === 'ol') {
                    return (
                        <ol key={bi} className="list-decimal space-y-1 pl-5 marker:text-neutral-400">
                            {b.items.map((it, ii) => <li key={ii} className="pl-1">{parseInline(it, `o${bi}-${ii}`)}</li>)}
                        </ol>
                    );
                }
                if (b.type === 'ul') {
                    return (
                        <ul key={bi} className="list-disc space-y-1 pl-5 marker:text-neutral-400">
                            {b.items.map((it, ii) => <li key={ii} className="pl-1">{parseInline(it, `u${bi}-${ii}`)}</li>)}
                        </ul>
                    );
                }
                return (
                    <p key={bi} className="whitespace-pre-wrap break-words">
                        {parseInline(b.text, `p${bi}`)}
                    </p>
                );
            })}
        </div>
    );
}
