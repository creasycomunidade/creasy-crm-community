const ALLOWED_TAGS = new Set([
    'A', 'BLOCKQUOTE', 'BR', 'CODE', 'DEL', 'DIV', 'EM', 'H1', 'H2', 'H3',
    'H4', 'H5', 'H6', 'HR', 'IMG', 'LI', 'OL', 'P', 'PRE', 'SPAN', 'STRONG',
    'TABLE', 'TBODY', 'TD', 'TH', 'THEAD', 'TR', 'U', 'UL',
]);

const DROP_WITH_CONTENTS = new Set([
    'SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'FORM', 'INPUT', 'BUTTON',
    'TEXTAREA', 'SELECT', 'OPTION', 'SVG', 'MATH', 'TEMPLATE', 'LINK', 'META', 'BASE',
]);

const GLOBAL_ATTRIBUTES = new Set(['class']);
const TAG_ATTRIBUTES = {
    A: new Set(['href', 'title', 'target', 'rel']),
    IMG: new Set(['src', 'alt', 'title', 'width', 'height', 'loading']),
    TD: new Set(['colspan', 'rowspan']),
    TH: new Set(['colspan', 'rowspan', 'scope']),
};

function safeUrl(value, allowedProtocols) {
    const trimmed = String(value || '').trim();
    if (!trimmed || trimmed.startsWith('/') || trimmed.startsWith('#')) return true;

    try {
        const parsed = new URL(trimmed, window.location.origin);
        return allowedProtocols.includes(parsed.protocol);
    } catch {
        return false;
    }
}

export function sanitizeHtml(value) {
    if (!value || typeof window === 'undefined' || typeof DOMParser === 'undefined') return '';

    const document = new DOMParser().parseFromString(String(value), 'text/html');
    const elements = Array.from(document.body.querySelectorAll('*')).reverse();

    for (const element of elements) {
        if (DROP_WITH_CONTENTS.has(element.tagName)) {
            element.remove();
            continue;
        }

        if (!ALLOWED_TAGS.has(element.tagName)) {
            element.replaceWith(...element.childNodes);
            continue;
        }

        const tagAttributes = TAG_ATTRIBUTES[element.tagName] || new Set();
        for (const attribute of Array.from(element.attributes)) {
            const name = attribute.name.toLowerCase();
            if (!GLOBAL_ATTRIBUTES.has(name) && !tagAttributes.has(name)) {
                element.removeAttribute(attribute.name);
            }
        }

        if (element.hasAttribute('href') && !safeUrl(element.getAttribute('href'), ['http:', 'https:', 'mailto:', 'tel:'])) {
            element.removeAttribute('href');
        }
        if (element.hasAttribute('src') && !safeUrl(element.getAttribute('src'), ['http:', 'https:'])) {
            element.removeAttribute('src');
        }
        if (element.tagName === 'A' && element.getAttribute('target') === '_blank') {
            element.setAttribute('rel', 'noopener noreferrer');
        }
    }

    return document.body.innerHTML;
}
