/**
 * Inbound message notification sounds.
 *
 * Each channel gets a distinct, short tone signature synthesised with the Web
 * Audio API — no audio files to ship or cache. Sounds only play after the user
 * has interacted with the page (browser autoplay policy); the AudioContext is
 * resumed on demand.
 */

let audioCtx = null;

function getCtx() {
    if (typeof window === 'undefined') return null;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    if (!audioCtx) {
        try {
            audioCtx = new AC();
        } catch {
            return null;
        }
    }
    return audioCtx;
}

// Per-channel note sequences: { f: frequency Hz, t: start offset s, d: duration s }.
const CHANNEL_TONES = {
    // WhatsApp — bright two-note rise.
    whatsapp: [
        { f: 880, t: 0, d: 0.12 },
        { f: 1175, t: 0.11, d: 0.16 },
    ],
    // Messenger — soft three-note arpeggio.
    messenger: [
        { f: 587, t: 0, d: 0.1 },
        { f: 784, t: 0.1, d: 0.1 },
        { f: 1047, t: 0.2, d: 0.14 },
    ],
    // Instagram — gentle two-note fall.
    instagram: [
        { f: 1047, t: 0, d: 0.1 },
        { f: 698, t: 0.12, d: 0.18 },
    ],
    telegram: [
        { f: 740, t: 0, d: 0.1 },
        { f: 988, t: 0.1, d: 0.14 },
    ],
    // SMS / email / fallback — single soft chime.
    default: [{ f: 784, t: 0, d: 0.16 }],
};

const PREFS_KEY = 'inbox.sound.channels';

/** Channels that have an independent sound on/off preference. */
export const SOUND_CHANNELS = [
    { key: 'whatsapp', label: 'WhatsApp' },
    { key: 'messenger', label: 'Messenger' },
    { key: 'instagram', label: 'Instagram' },
    { key: 'telegram', label: 'Telegram' },
    { key: 'sms', label: 'SMS' },
    { key: 'email', label: 'Email' },
];

function loadPrefs() {
    if (typeof localStorage === 'undefined') return {};
    try {
        return JSON.parse(localStorage.getItem(PREFS_KEY) || '{}') || {};
    } catch {
        return {};
    }
}

/** Each channel defaults to ON unless explicitly disabled. */
export function isChannelSoundEnabled(channel) {
    return loadPrefs()[channel] !== false;
}

/** Returns a map of { channelKey: boolean } for every known channel. */
export function getSoundPrefs() {
    const prefs = loadPrefs();
    const out = {};
    for (const c of SOUND_CHANNELS) out[c.key] = prefs[c.key] !== false;
    return out;
}

export function setChannelSoundEnabled(channel, enabled) {
    if (typeof localStorage === 'undefined') return;
    const prefs = loadPrefs();
    prefs[channel] = !!enabled;
    localStorage.setItem(PREFS_KEY, JSON.stringify(prefs));
}

/** Unlock audio after a real user gesture so later background alerts can play. */
export function unlockInboundSound() {
    const ctx = getCtx();
    if (ctx?.state === 'suspended') {
        ctx.resume().catch(() => {});
    }
}

/**
 * Play the notification tone for a given channel, if that channel is enabled.
 * @param {string} channel - 'whatsapp' | 'messenger' | 'instagram' | …
 */
export function playInboundSound(channel) {
    if (!isChannelSoundEnabled(channel)) return;

    const ctx = getCtx();
    if (!ctx) return;
    if (ctx.state === 'suspended') ctx.resume().catch(() => {});

    const tones = CHANNEL_TONES[channel] || CHANNEL_TONES.default;
    const now = ctx.currentTime;

    for (const note of tones) {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = note.f;

        const start = now + note.t;
        const end = start + note.d;

        // Quick attack, smooth exponential decay — avoids clicks.
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.18, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, end);

        osc.connect(gain).connect(ctx.destination);
        osc.start(start);
        osc.stop(end + 0.03);
    }
}
