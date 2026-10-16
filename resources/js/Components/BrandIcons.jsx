/**
 * Brand SVG paths are from the Simple Icons project (CC0 / public domain).
 * https://github.com/simple-icons/simple-icons — follow each vendor’s brand guidelines in production.
 */
import brandIconData from './brandIconData.json';

/** Human-readable labels for inbox / campaign channel keys */
export const CHANNEL_LABELS = {
    whatsapp: 'WhatsApp',
    instagram: 'Instagram',
    messenger: 'Messenger',
    telegram: 'Telegram',
    sms: 'SMS',
    email: 'Email',
    webchat: 'Chat do site',
};

function SvgBrand({ name, className }) {
    const data = brandIconData[name];
    if (!data) return null;
    const useCurrentColor = name === 'twitter' || name === 'tiktok';
    const fill = useCurrentColor ? 'currentColor' : `#${data.hex}`;
    return (
        <svg
            role="img"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            className={[
                className ?? 'h-4 w-4',
                useCurrentColor ? 'text-neutral-900 dark:text-neutral-100' : '',
            ]
                .filter(Boolean)
                .join(' ')}
            aria-hidden
        >
            <path fill={fill} d={data.path} />
        </svg>
    );
}

/** WhatsApp, Instagram, Messenger, SMS (Twilio mark), Email (envelope icon) */
export function ChannelBrandIcon({ channel, className }) {
    if (channel === 'webchat') {
        return <svg viewBox="0 0 24 24" fill="none" className={className ?? 'h-4 w-4'} aria-hidden><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z" stroke="currentColor" strokeWidth="2" strokeLinejoin="round"/><path d="M8 9h8M8 13h5" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></svg>;
    }
    const key =
        channel === 'whatsapp'
            ? 'whatsapp'
            : channel === 'instagram'
              ? 'instagram'
              : channel === 'messenger'
                ? 'messenger'
                : channel === 'telegram'
                  ? 'telegram'
                : channel === 'sms'
                  ? 'sms'
                  : channel === 'email'
                    ? 'email'
                    : null;
    if (!key) return null;
    return <SvgBrand name={key} className={className} />;
}

/** Facebook, Instagram, LinkedIn, X, YouTube, TikTok */
export function SocialBrandIcon({ network, className }) {
    const map = {
        facebook: 'facebook',
        instagram: 'instagram',
        linkedin: 'linkedin',
        twitter: 'twitter',
        youtube: 'youtube',
        tiktok: 'tiktok',
    };
    const name = map[network];
    if (!name) return null;
    return <SvgBrand name={name} className={className} />;
}

/**
 * Resolve a free-text integration name (e.g. "WhatsApp Business", "Google Sheets")
 * to a brand key in brandIconData.json, or null when no official logo exists.
 */
const BRAND_ALIASES = {
    whatsappbusiness: 'whatsapp',
    facebookmessenger: 'messenger',
    instagramdirect: 'instagram',
    instagramdm: 'instagram',
    smscampaigns: 'sms',
    anthropicclaude: 'anthropic',
    claude: 'anthropic',
    gemini: 'googlegemini',
    googlesheet: 'googlesheets',
    x: 'twitter',
    xtwitter: 'twitter',
    twitterx: 'twitter',
};

export function resolveBrandKey(name) {
    const norm = (name || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    const key = BRAND_ALIASES[norm] || norm;
    return brandIconData[key] ? key : null;
}

/**
 * Logo tile for an integration. Renders the official brand SVG (on a white tile)
 * when one is available, otherwise a clean monogram fallback. Use for integration
 * grids / logo strips.
 */
export function BrandMark({ name, tileClassName = 'h-10 w-10 rounded-xl', glyphClassName = 'h-6 w-6', monogramClassName = 'text-sm' }) {
    const key = resolveBrandKey(name);
    const tile = `inline-flex items-center justify-center bg-white ring-1 ring-neutral-200 dark:ring-neutral-700 ${tileClassName}`;
    if (key) {
        return (
            <span className={tile} aria-label={name}>
                <SvgBrand name={key} className={glyphClassName} />
            </span>
        );
    }
    const letter = (name || '?').trim().charAt(0).toUpperCase();
    return (
        <span className={`${tile} font-bold text-neutral-700 ${monogramClassName}`} aria-label={name}>
            {letter}
        </span>
    );
}
